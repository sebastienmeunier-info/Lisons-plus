<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Model\Livre;

class HomeController
{
    /**
     * Racine « double face » :
     * - connecté  : ma bibliothèque personnelle (filtres q/statut/tag) ;
     * - visiteur  : page publique. Avec des paramètres de champ
     *   (?isbn=…&auteur=…&tag=…) : liste publique des fiches de lecture
     *   correspondantes — c'est le partage « par champ » de Lisons-plus.
     */
    public function index(Request $req): Response
    {
        $app = App::get();
        // Partage d'une fiche par query string : /?partage={token} (public)
        $params = query_params($req);
        if (!empty($params['partage'])) {
            return (new FicheController())->publique($req, ['token' => (string) $params['partage']]);
        }
        $user = $app->auth()->currentUser();
        if ($user) {
            return $this->prive($req, $user);
        }
        return $this->public($req);
    }

    /** Bibliothèque personnelle de l'utilisateur connecté. */
    private function prive(Request $req, array $user): Response
    {
        $app = App::get();
        $store = $app->store('livres');

        $q = (string) $req->input('q', '');
        $statut = (string) $req->input('statut', '');
        $tag = (string) $req->input('tag', '');
        $tri = (string) $req->input('tri', 'ajout');

        $perso = $store->query(fn ($l) => ($l['user_id'] ?? '') === $user['id']);
        $livres = $q !== '' ? array_intersect_key($store->search($q, Livre::champsRecherche()), $perso) : $perso;

        $livres = array_filter($livres, function (array $l) use ($statut, $tag): bool {
            if ($statut !== '' && ($l['statut'] ?? '') !== $statut) {
                return false;
            }
            if ($tag !== '' && !in_array($tag, $l['tags'] ?? [], true)) {
                return false;
            }
            return true;
        });
        $livres = $this->trier($livres, $tri);

        $stats = [
            'total' => count($perso),
            'lus' => count(array_filter($perso, fn ($l) => ($l['statut'] ?? '') === 'lu')),
            'en_cours' => count(array_filter($perso, fn ($l) => ($l['statut'] ?? '') === 'en_cours')),
            'a_lire' => count(array_filter($perso, fn ($l) => ($l['statut'] ?? '') === 'a_lire')),
        ];

        $tags = [];
        foreach ($perso as $l) {
            foreach ($l['tags'] ?? [] as $t) {
                $tags[$t] = ($tags[$t] ?? 0) + 1;
            }
        }
        ksort($tags);

        $html = $app->view()->render('accueil', [
            'user' => $user,
            'livres' => $livres,
            'stats' => $stats,
            'tags' => $tags,
            'q' => $q,
            'statut' => $statut,
            'tag' => $tag,
            'tri' => $tri,
            'statuts' => $app->config()['statuts'],
            'page' => 'accueil',
        ]);
        return Response::html($html);
    }

    /**
     * /partage — liste PUBLIQUE de toutes les fiches partagées (tous les
     * utilisateurs, y compris l'utilisateur connecté). C'est la page
     * d'accueil des visiteurs non connectés ; elle reste accessible une fois
     * connecté. Les paramètres ?isbn/?auteur/?tag/?q=… filtrent le partage.
     */
    public function partage(Request $req): Response
    {
        return $this->public($req);
    }

    /**
     * Page publique. Les paramètres de champ (isbn, auteur, tag, serie,
     * editeur, titre, statut, q) filtrent les fiches de lecture publiées.
     */
    private function public(Request $req): Response
    {
        $app = App::get();
        $params = query_params($req);
        unset($params['page']);

        // Critères de partage reconnus
        $criteres = [];
        foreach (['isbn', 'auteur', 'tag', 'serie', 'editeur', 'titre', 'statut', 'q'] as $c) {
            if (isset($params[$c]) && trim((string) $params[$c]) !== '') {
                $criteres[$c] = trim((string) $params[$c]);
            }
        }

        $livres = $app->store('livres')->all();
        $fiches = $app->store('fiches')->all();
        $users = $app->store('users')->all();

        // Livres correspondant aux critères (champs publics uniquement)
        $match = function (array $l) use ($criteres): bool {
            foreach ($criteres as $c => $v) {
                $hay = \mb_strtolower($v);
                switch ($c) {
                    case 'isbn':
                        $isbnLivre = strtoupper(preg_replace('/[^0-9Xx]/', '', $l['isbn'] ?? '') ?? '');
                        $isbnRecherche = strtoupper(preg_replace('/[^0-9Xx]/', '', $v) ?? '');
                        if ($isbnLivre === '' || !str_contains($isbnLivre, $isbnRecherche)) {
                            return false;
                        }
                        break;
                    case 'auteur':
                        $ok = false;
                        foreach ($l['auteurs'] ?? [] as $a) {
                            if (\mb_str_contains(\mb_strtolower($a), $hay)) { $ok = true; break; }
                        }
                        if (!$ok) { return false; }
                        break;
                    case 'tag':
                        $ok = false;
                        foreach ($l['tags'] ?? [] as $t) {
                            if (\mb_strtolower($t) === $hay) { $ok = true; break; }
                        }
                        if (!$ok) { return false; }
                        break;
                    case 'statut':
                        if (($l['statut'] ?? '') !== $v) { return false; }
                        break;
                    case 'q':
                    case 'titre':
                        $haystack = \mb_strtolower(implode(' ', array_merge(
                            [$l['titre'] ?? '', $l['serie'] ?? '', $l['editeur'] ?? '', $l['isbn'] ?? ''],
                            $l['auteurs'] ?? [],
                            $l['tags'] ?? [],
                            $l['sujets'] ?? []
                        )));
                        if (!\mb_str_contains($haystack, $hay)) { return false; }
                        break;
                    default: // serie, editeur
                        if (!\mb_str_contains(\mb_strtolower($l[$c] ?? ''), $hay)) { return false; }
                }
            }
            return true;
        };

        if ($criteres === []) {
            // Landing publique : dernières fiches partagées
            $publiques = array_filter($fiches, fn ($f) => !empty($f['publique']) && !empty($f['token']));
            uasort($publiques, fn ($a, $b) => strcmp($b['date_creation'] ?? '', $a['date_creation'] ?? ''));
            $publiques = array_slice($publiques, 0, 12, true);
            $html = $app->view()->render('public/accueil', [
                'fiches' => $publiques,
                'livres' => $livres,
                'users' => $users,
                'criteres' => [],
                'page' => 'accueil_public',
            ], 'layout_public');
            return Response::html($html);
        }

        $livresMatch = array_filter($livres, $match);
        $idsLivres = array_keys($livresMatch);
        // Fiches publiques de ces livres
        $fichesMatch = array_filter($fiches, fn ($f) => !empty($f['publique']) && !empty($f['token']) && in_array($f['livre_id'] ?? '', $idsLivres, true));
        uasort($fichesMatch, fn ($a, $b) => strcmp($b['date_modif'] ?? '', $a['date_modif'] ?? ''));

        $html = $app->view()->render('public/resultats', [
            'criteres' => $criteres,
            'livres' => $livresMatch,
            'fiches' => $fichesMatch,
            'users' => $users,
            'page' => 'accueil_public',
        ], 'layout_public');
        return Response::html($html);
    }

    private function trier(array $livres, string $tri): array
    {
        $livres = array_values($livres);
        usort($livres, match ($tri) {
            'titre' => fn ($a, $b) => strcasecmp($a['titre'] ?? '', $b['titre'] ?? ''),
            'note' => fn ($a, $b) => ($b['note'] ?? 0) <=> ($a['note'] ?? 0),
            'lecture' => fn ($a, $b) => strcmp($b['date_lecture'] ?? '0000', $a['date_lecture'] ?? '0000'),
            default => fn ($a, $b) => strcmp($b['date_ajout'] ?? '', $a['date_ajout'] ?? ''),
        });
        return $livres;
    }
}
