<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\Fiche;

class FicheController
{
    private function peutVoir(?array $fiche, array $user): bool
    {
        if (!$fiche) {
            return false;
        }
        return ($fiche['user_id'] ?? '') === $user['id'] || App::get()->auth()->hasRole($user, 'administrateur');
    }

    /** Liste des fiches publiquement partagées par l'utilisateur connecté. */
    public function partages(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $fiches = $app->store('fiches')->query(fn ($f) => !empty($f['publique']) && ($f['user_id'] ?? '') === $user['id']);
        $livres = $app->store('livres')->all();
        $html = $app->view()->render('fiches/partages', [
            'fiches' => $fiches,
            'livres' => $livres,
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $user,
            'page' => 'partages',
        ]);
        return Response::html($html);
    }

    public function nouvelle(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $livre = $app->store('livres')->find($params['id']);
        if (!$livre || ($livre['user_id'] ?? '') !== $user['id']) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $html = $app->view()->render('fiches/form', [
            'fiche' => ['livre_id' => $livre['id']],
            'livre' => $livre,
            'action' => url('/livre/' . $livre['id'] . '/fiche'),
            'titre_page' => 'Nouvelle fiche de lecture',
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $user,
            'page' => 'fiche',
        ]);
        return Response::html($html);
    }

    public function creer(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $livre = $app->store('livres')->find($params['id']);
        if (!$livre || ($livre['user_id'] ?? '') !== $user['id']) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $fiche = Fiche::depuisFormulaire($req->all(), $livre['id']);
        $fiche['user_id'] = $user['id'];
        $erreurs = Fiche::valide($fiche);
        if ($erreurs) {
            return Response::html($app->view()->render('fiches/form', [
                'fiche' => $fiche,
                'livre' => $livre,
                'action' => url('/livre/' . $livre['id'] . '/fiche'),
                'titre_page' => 'Nouvelle fiche de lecture',
                'erreurs' => $erreurs,
                'csrfToken' => $app->auth()->csrfToken(),
                'user' => $user,
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
        $user = $app->auth()->currentUser();
        $fiche = $app->store('fiches')->find($params['fiche']);
        if (!$this->peutVoir($fiche, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Fiche introuvable.'], null), 404);
        }
        $livre = $app->store('livres')->find($fiche['livre_id'] ?? '');
        $html = $app->view()->render('fiches/form', [
            'fiche' => $fiche,
            'livre' => $livre,
            'action' => url('/fiche/' . $fiche['id'] . '/maj'),
            'titre_page' => 'Modifier la fiche',
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $user,
            'page' => 'fiche',
        ]);
        return Response::html($html);
    }

    public function maj(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $store = $app->store('fiches');
        $existant = $store->find($params['fiche']);
        if (!$this->peutVoir($existant, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Fiche introuvable.'], null), 404);
        }
        $fiche = Fiche::depuisFormulaire($req->all(), $existant['livre_id']);
        $fiche['id'] = $existant['id'];
        $fiche['user_id'] = $existant['user_id'] ?? $user['id'];
        $fiche['date_creation'] = $existant['date_creation'];
        $fiche['token'] = $existant['token'] ?? null;
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
                'csrfToken' => $app->auth()->csrfToken(),
                'user' => $user,
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
        $user = $app->auth()->currentUser();
        $fiche = $app->store('fiches')->find($params['fiche']);
        if (!$this->peutVoir($fiche, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Fiche introuvable.'], null), 404);
        }
        $app->store('fiches')->delete($params['fiche']);
        $url = $fiche && !empty($fiche['livre_id']) ? url('/livre/' . $fiche['livre_id']) : url('/partages');
        return Response::redirect($url);
    }

    public function basculerPublication(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $store = $app->store('fiches');
        $fiche = $store->find($params['fiche']);
        if (!$this->peutVoir($fiche, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Fiche introuvable.'], null), 404);
        }
        $fiche['publique'] = empty($fiche['publique']);
        if ($fiche['publique'] && empty($fiche['token'])) {
            $fiche['token'] = token_partage();
        }
        if (!$fiche['publique']) {
            $fiche['token'] = null;
        }
        $fiche['date_modif'] = date('Y-m-d H:i:s');
        $store->save($fiche['id'], $fiche);
        $url = !empty($fiche['livre_id']) ? url('/livre/' . $fiche['livre_id']) : url('/partages');
        return Response::redirect($url . '#fiche-' . $params['fiche']);
    }

    /** Page publique de partage d'une fiche (aucune authentification). */
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
        $auteur = $app->store('users')->find($fiche['user_id'] ?? '');
        $html = $app->view()->render('fiches/publique', [
            'fiche' => $fiche,
            'livre' => $livre,
            'auteur_fiche' => $auteur,
            'liens_partage' => $livre ? liens_partage_livre($livre) : [],
            'page' => 'partage',
        ], 'layout_public');
        return Response::html($html);
    }
}
