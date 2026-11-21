<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Model\Livre;
use App\Service\IsbnService;

class ApiController
{
    /** GET /api/isbn/{isbn} : métadonnées Open Library pour pré-remplir le formulaire. */
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

    /** GET /api/livres?q= : recherche dans l'inventaire (JSON). */
    public function recherche(Request $req): Response
    {
        $app = App::get();
        $q = (string) $req->input('q', '');
        $resultats = $app->store('livres')->search($q, Livre::champsRecherche());
        // Version allégée pour l'autocomplétion
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
}
