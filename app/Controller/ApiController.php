<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Model\Livre;
use App\Service\IsbnService;

class ApiController
{
    /** GET /api/isbn/{isbn} : métadonnées Open Library (public, sans données perso). */
    public function isbn(Request $req, array $params): Response
    {
        $app = App::get();
        /** @var IsbnService $service */
        $service = $app->service('isbn', fn ($a) => new IsbnService($a->config()));
        $isbn = $params['isbn'] ?? '';
        $normalise = $service->normaliser($isbn);
        if ($normalise === null) {
            return Response::json(['ok' => false, 'erreur' => 'ISBN invalide (10 ou 13 chiffres, checksum faux).'], 422);
        }
        if ((string) $req->input('diagnostic', '') === '1') {
            return Response::json(['ok' => true, 'diagnostic' => $this->diagnosticIsbn($app, $service, $isbn)]);
        }
        try {
            $meta = $service->lookup($isbn);
        } catch (\Throwable $e) {
            $app->log('api/isbn ' . $isbn . ' : ' . $e->getMessage());
            return Response::json(['ok' => false, 'erreur' => 'Service ISBN indisponible pour le moment (réseau ou configuration serveur). Réessaie ou remplis les champs à la main.'], 502);
        }
        if ($meta === null) {
            return Response::json(['ok' => false, 'erreur' => 'Aucun résultat pour cet ISBN dans Open Library.'], 404);
        }
        if (!empty($meta['couverture_id'])) {
            $meta['couverture_url'] = $service->couvertureUrl((int) $meta['couverture_id']);
        }
        unset($meta['couverture_id']);
        return Response::json(['ok' => true, 'isbn' => $normalise, 'meta' => $meta]);
    }

    /**
     * GET /api/livres?q= : recherche JSON.
     * - connecté : dans sa bibliothèque ;
     * - visiteur : uniquement les livres ayant au moins une fiche publique,
     *   avec des champs publics (pas de statut/note/commentaire personnels).
     */
    public function recherche(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $q = (string) $req->input('q', '');

        if ($user) {
            $perso = $app->store('livres')->query(fn ($l) => ($l['user_id'] ?? '') === $user['id']);
            $resultats = $q !== '' ? array_intersect_key($app->store('livres')->search($q, Livre::champsRecherche()), $perso) : $perso;
            $out = array_map(fn ($l) => [
                'id' => $l['id'],
                'titre' => $l['titre'],
                'auteurs' => $l['auteurs'] ?? [],
                'annee' => $l['annee'] ?? null,
                'statut' => $l['statut'] ?? null,
                'note' => $l['note'] ?? 0,
                'couverture' => $l['couverture'] ?? null,
            ], array_slice($resultats, 0, 20, true));
            return Response::json(['ok' => true, 'total' => count($resultats), 'livres' => array_values($out)]);
        }

        // Visiteur : livres ayant au moins une fiche publique
        $idsLivresPublics = [];
        foreach ($app->store('fiches')->query(fn ($f) => !empty($f['publique'])) as $f) {
            $idsLivresPublics[$f['livre_id'] ?? ''] = true;
        }
        $tous = $app->store('livres')->all();
        $publics = array_filter($tous, fn ($l) => isset($idsLivresPublics[$l['id']]));
        if ($q !== '') {
            $publics = array_intersect_key($app->store('livres')->search($q, ['titre', 'auteurs', 'editeur', 'serie', 'tags', 'sujets', 'isbn']), $publics);
        }
        $out = array_map(fn ($l) => [
            'id' => $l['id'],
            'titre' => $l['titre'],
            'auteurs' => $l['auteurs'] ?? [],
            'annee' => $l['annee'] ?? null,
            'couverture' => $l['couverture'] ?? null,
            'isbn' => $l['isbn'] ?? null,
        ], array_slice($publics, 0, 20, true));
        return Response::json(['ok' => true, 'total' => count($publics), 'livres' => array_values($out)]);
    }

    /**
     * Diagnostic public (?/api/isbn/{isbn}?diagnostic=1) : rejoue chaque étape
     * de la récupération de couverture côté serveur et rapporte ce qui échoue
     * (transport HTTP, dossiers, appels Open Library, téléchargements).
     */
    private function diagnosticIsbn(App $app, IsbnService $service, string $isbn): array
    {
        $cfg = $app->config();
        $root = rtrim((string) ($cfg['paths']['root'] ?? ''), '/');
        $coversDir = rtrim((string) $cfg['paths']['uploads'], '/') . '/' . $cfg['upload']['covers_dir'];
        $dataDir = (string) ($cfg['paths']['data'] ?? '');

        $diag = [
            'transport' => Http::transportActuel(),
            'ext_curl' => function_exists('curl_init'),
            'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
            'php' => PHP_VERSION,
            'root' => $root,
            'data_dir' => $dataDir,
            'data_dir_writable' => $dataDir !== '' && is_dir($dataDir) ? is_writable($dataDir) : null,
            'covers_dir' => $coversDir,
            'covers_dir_exists' => is_dir($coversDir),
            'covers_dir_writable' => is_dir($coversDir) ? is_writable($coversDir) : null,
            'etapes' => [],
        ];
        if (is_dir($coversDir)) {
            $diag['covers_dir_perms'] = substr(sprintf('%o', (int) fileperms($coversDir)), -4);
        }

        // Formes d'ISBN testées (identiques à IsbnService::reparer)
        $brut = \App\Model\Livre::nettoyerIsbn($isbn);
        $formes = [$brut];
        if (strlen($brut) === 10) {
            $formes[] = '978' . substr($brut, 0, 9); // forme 13 approximative, le checksum est recalculé ci-dessous
            $core = '978' . substr($brut, 0, 9);
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $core[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $formes[1] = $core . ((10 - ($sum % 10)) % 10);
        }

        // 1) Sondes HTTP brutes (corps uniquement) + téléchargement réel
        foreach ($formes as $forme) {
            $url = $service->couvertureUrlIsbn($forme);
            $t0 = microtime(true);
            $body = Http::get($url, 25);
            $sonde = [
                'url' => $url,
                'get_secondes' => round(microtime(true) - $t0, 2),
                'get_taille' => $body === null ? null : strlen($body),
                'get_magic' => $body === null || $body === '' ? null : bin2hex(substr($body, 0, 4)),
            ];
            $t0 = microtime(true);
            $path = null;
            $err = '';
            try {
                $path = $service->telechargerCouverture($url);
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }
            $dl = [
                'download_secondes' => round(microtime(true) - $t0, 2),
                'download_ok' => $path !== null,
                'erreur' => $err !== '' ? $err : null,
            ];
            if ($path !== null) {
                $f = $root . '/' . $path;
                $dl['taille'] = is_file($f) ? filesize($f) : null;
                $dl['perms'] = is_file($f) ? substr(sprintf('%o', (int) fileperms($f)), -4) : null;
                @unlink($f); // ménage : la vraie réparation la retéléchargera
            }
            $diag['etapes'][] = ['forme' => $forme, 'cdn' => $sonde + $dl];
        }

        // 2) Métadonnées (lookup) pour chaque forme
        $t0 = microtime(true);
        try {
            $meta = $service->lookup($isbn);
        } catch (\Throwable $e) {
            $meta = null;
            $diag['lookup_erreur'] = $e->getMessage();
        }
        $diag['lookup'] = [
            'secondes' => round(microtime(true) - $t0, 2),
            'trouve' => $meta !== null,
            'titre' => $meta['titre'] ?? null,
            'couverture_id' => $meta['couverture_id'] ?? null,
        ];
        if ($meta !== null && !empty($meta['couverture_id'])) {
            $url = $service->couvertureUrl((int) $meta['couverture_id']);
            $t0 = microtime(true);
            $path = null;
            $err = '';
            try {
                $path = $service->telechargerCouverture($url);
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }
            $diag['etapes'][] = [
                'forme' => 'cover_id:' . $meta['couverture_id'],
                'cdn' => [
                    'url' => $url,
                    'download_secondes' => round(microtime(true) - $t0, 2),
                    'download_ok' => $path !== null,
                    'erreur' => $err !== '' ? $err : null,
                    'taille' => $path !== null && is_file($root . '/' . $path) ? filesize($root . '/' . $path) : null,
                ],
            ];
            if ($path !== null) {
                @unlink($root . '/' . $path);
            }
        }
        return $diag;
    }
}
