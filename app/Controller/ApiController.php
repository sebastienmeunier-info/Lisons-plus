<?php
namespace App\Controller;

use App\Core\App;
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
        $meta = $service->lookup($isbn);
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
}
