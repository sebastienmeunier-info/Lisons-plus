<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Model\Livre;

class HomeController
{
    public function index(Request $req): Response
    {
        $app = App::get();
        $store = $app->store('livres');

        $q = (string) $req->input('q', '');
        $statut = (string) $req->input('statut', '');
        $tag = (string) $req->input('tag', '');
        $tri = (string) $req->input('tri', 'ajout'); // ajout | titre | note | lecture

        $livres = $q !== '' ? $store->search($q, Livre::champsRecherche()) : $store->all();

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

        // Statistiques
        $tous = $store->all();
        $stats = [
            'total' => count($tous),
            'lus' => count(array_filter($tous, fn ($l) => ($l['statut'] ?? '') === 'lu')),
            'en_cours' => count(array_filter($tous, fn ($l) => ($l['statut'] ?? '') === 'en_cours')),
            'a_lire' => count(array_filter($tous, fn ($l) => ($l['statut'] ?? '') === 'a_lire')),
        ];

        // Tous les tags existants (pour le filtre)
        $tags = [];
        foreach ($tous as $l) {
            foreach ($l['tags'] ?? [] as $t) {
                $tags[$t] = ($tags[$t] ?? 0) + 1;
            }
        }
        ksort($tags);

        $html = $app->view()->render('accueil', [
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
