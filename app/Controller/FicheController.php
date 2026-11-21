<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Model\Fiche;

class FicheController
{
    /** Liste des fiches publiquement partagées. */
    public function partages(Request $req): Response
    {
        $app = App::get();
        $fiches = $app->store('fiches')->query(fn ($f) => !empty($f['publique']));
        $livres = $app->store('livres')->all();
        $html = $app->view()->render('fiches/partages', [
            'fiches' => $fiches,
            'livres' => $livres,
            'page' => 'partages',
        ]);
        return Response::html($html);
    }

    public function nouvelle(Request $req, array $params): Response
    {
        $app = App::get();
        $livre = $app->store('livres')->find($params['id']);
        if (!$livre) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $html = $app->view()->render('fiches/form', [
            'fiche' => ['livre_id' => $livre['id']],
            'livre' => $livre,
            'action' => url('/livre/' . $livre['id'] . '/fiche'),
            'titre_page' => 'Nouvelle fiche de lecture',
            'page' => 'fiche',
        ]);
        return Response::html($html);
    }

    public function creer(Request $req, array $params): Response
    {
        $app = App::get();
        $livre = $app->store('livres')->find($params['id']);
        if (!$livre) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $fiche = Fiche::depuisFormulaire($req->all(), $livre['id']);
        $erreurs = Fiche::valide($fiche);
        if ($erreurs) {
            return Response::html($app->view()->render('fiches/form', [
                'fiche' => $fiche,
                'livre' => $livre,
                'action' => url('/livre/' . $livre['id'] . '/fiche'),
                'titre_page' => 'Nouvelle fiche de lecture',
                'erreurs' => $erreurs,
                'page' => 'fiche',
            ]), 422);
        }
        $id = uuid_short();
        $fiche['id'] = $id;
        if ($fiche['publique']) {
            $fiche['token'] = token_partage();
        }
        $app->store('fiches')->save($id, $fiche);
        return Response::redirect(url('/livre/' . $livre['id'] . '#fiche-' . $id));
    }

    public function editer(Request $req, array $params): Response
    {
        $app = App::get();
        $fiche = $app->store('fiches')->find($params['fiche']);
        if (!$fiche) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Fiche introuvable.'], null), 404);
        }
        $livre = $app->store('livres')->find($fiche['livre_id'] ?? '');
        $html = $app->view()->render('fiches/form', [
            'fiche' => $fiche,
            'livre' => $livre,
            'action' => url('/fiche/' . $fiche['id'] . '/maj'),
            'titre_page' => 'Modifier la fiche',
            'page' => 'fiche',
        ]);
        return Response::html($html);
    }

    public function maj(Request $req, array $params): Response
    {
        $app = App::get();
        $store = $app->store('fiches');
        $existant = $store->find($params['fiche']);
        if (!$existant) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Fiche introuvable.'], null), 404);
        }
        $fiche = Fiche::depuisFormulaire($req->all(), $existant['livre_id']);
        $fiche['id'] = $existant['id'];
        $fiche['date_creation'] = $existant['date_creation'];
        $fiche['token'] = $existant['token'] ?? null;
        // (Dé)publication : génère un token à la première publication
        if ($fiche['publique'] && empty($fiche['token'])) {
            $fiche['token'] = token_partage();
        }
        if (!$fiche['publique']) {
            $fiche['token'] = null;
        }
        $erreurs = Fiche::valide($fiche);
        if ($erreurs) {
            return Response::html($app->view()->render('fiches/form', [
                'fiche' => $fiche,
                'livre' => $app->store('livres')->find($fiche['livre_id'] ?? ''),
                'action' => url('/fiche/' . $fiche['id'] . '/maj'),
                'titre_page' => 'Modifier la fiche',
                'erreurs' => $erreurs,
                'page' => 'fiche',
            ]), 422);
        }
        $store->save($existant['id'], $fiche);
        $url = $fiche['livre_id'] ? url('/livre/' . $fiche['livre_id'] . '#fiche-' . $fiche['id']) : url('/partages');
        return Response::redirect($url);
    }

    public function supprimer(Request $req, array $params): Response
    {
        $app = App::get();
        $fiche = $app->store('fiches')->find($params['fiche']);
        $app->store('fiches')->delete($params['fiche']);
        $url = $fiche && !empty($fiche['livre_id']) ? url('/livre/' . $fiche['livre_id']) : url('/partages');
        return Response::redirect($url);
    }

    /** Bascule rapide public/privé depuis la page du livre. */
    public function basculerPublication(Request $req, array $params): Response
    {
        $app = App::get();
        $store = $app->store('fiches');
        $fiche = $store->find($params['fiche']);
        if ($fiche) {
            $fiche['publique'] = empty($fiche['publique']);
            if ($fiche['publique'] && empty($fiche['token'])) {
                $fiche['token'] = token_partage();
            }
            if (!$fiche['publique']) {
                $fiche['token'] = null;
            }
            $fiche['date_modif'] = date('Y-m-d H:i:s');
            $store->save($fiche['id'], $fiche);
        }
        $url = !empty($fiche['livre_id']) ? url('/livre/' . $fiche['livre_id']) : url('/partages');
        return Response::redirect($url . '#fiche-' . $params['fiche']);
    }

    /** Page publique de partage (aucune authentification). */
    public function publique(Request $req, array $params): Response
    {
        $app = App::get();
        $fiche = null;
        foreach ($app->store('fiches')->all() as $f) {
            if (($f['token'] ?? '') === $params['token'] && !empty($f['publique'])) {
                $fiche = $f;
                break;
            }
        }
        if (!$fiche) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Cette fiche n\'est pas (ou plus) partagée publiquement.'], null), 404);
        }
        $livre = $app->store('livres')->find($fiche['livre_id'] ?? '');
        $html = $app->view()->render('fiches/publique', [
            'fiche' => $fiche,
            'livre' => $livre,
            'page' => 'partage',
        ], 'layout_public');
        return Response::html($html);
    }
}
