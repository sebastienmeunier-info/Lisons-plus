<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Model\Livre;
use App\Service\IsbnService;

class LivreController
{
    private function isbn(): IsbnService
    {
        return App::get()->service('isbn', fn ($app) => new IsbnService($app->config()));
    }

    /** Formulaire d'ajout (avec recherche ISBN). */
    public function nouveau(Request $req): Response
    {
        $app = App::get();
        $html = $app->view()->render('livres/form', [
            'livre' => [],
            'action' => url('/livres'),
            'titre_page' => 'Ajouter un livre',
            'statuts' => $app->config()['statuts'],
            'isbn_saisie' => (string) $req->input('isbn', ''),
            'page' => 'nouveau',
        ]);
        return Response::html($html);
    }

    /** Création d'un livre (POST). */
    public function creer(Request $req): Response
    {
        $app = App::get();
        $meta = null;
        $isbnSaisi = (string) $req->input('isbn', '');
        if ($isbnSaisi !== '') {
            $meta = $this->isbn()->lookup($isbnSaisi);
            if ($meta === null) {
                return $this->reponseErreur($req, 'ISBN invalide ou introuvable dans Open Library. Vérifie la saisie ou remplis les champs à la main.', $isbnSaisi);
            }
            // Couverture
            if (!empty($meta['couverture_id'])) {
                $coverUrl = $this->isbn()->couvertureUrl((int) $meta['couverture_id']);
                if ($coverUrl) {
                    $meta['couverture'] = $this->isbn()->telechargerCouverture($coverUrl);
                    $meta['couverture_url'] = $coverUrl;
                }
            }
        }
        $livre = Livre::depuisFormulaire($req->all(), $app->config()['statuts'], $meta);
        $erreurs = Livre::valide($livre);
        if ($erreurs) {
            return $this->reponseErreur($req, implode(' ', $erreurs), $isbnSaisi, $livre);
        }
        $id = uuid_short();
        $livre['id'] = $id;
        $app->store('livres')->save($id, $livre);
        return Response::redirect(url('/livre/' . $id));
    }

    /** Page détail d'un livre + ses fiches. */
    public function voir(Request $req, array $params): Response
    {
        $app = App::get();
        $livre = $app->store('livres')->find($params['id']);
        if (!$livre) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $fiches = $app->store('fiches')->query(fn ($f) => ($f['livre_id'] ?? null) === $livre['id']);
        $html = $app->view()->render('livres/voir', [
            'livre' => $livre,
            'fiches' => $fiches,
            'statuts' => $app->config()['statuts'],
            'page' => 'livre',
        ]);
        return Response::html($html);
    }

    public function editer(Request $req, array $params): Response
    {
        $app = App::get();
        $livre = $app->store('livres')->find($params['id']);
        if (!$livre) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $html = $app->view()->render('livres/form', [
            'livre' => $livre,
            'action' => url('/livre/' . $livre['id'] . '/maj'),
            'titre_page' => 'Modifier : ' . $livre['titre'],
            'statuts' => $app->config()['statuts'],
            'isbn_saisie' => $livre['isbn'] ?? '',
            'page' => 'livre',
        ]);
        return Response::html($html);
    }

    public function maj(Request $req, array $params): Response
    {
        $app = App::get();
        $store = $app->store('livres');
        $existant = $store->find($params['id']);
        if (!$existant) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $livre = Livre::depuisFormulaire($req->all(), $app->config()['statuts'], $existant);
        $livre['id'] = $existant['id'];
        $livre['date_ajout'] = $existant['date_ajout'] ?? date('Y-m-d');
        // Conserve la couverture existante si aucune nouvelle saisie
        if (($livre['couverture'] ?? '') === '') {
            $livre['couverture'] = $existant['couverture'] ?? '';
        }
        $erreurs = Livre::valide($livre);
        if ($erreurs) {
            return Response::html($app->view()->render('livres/form', [
                'livre' => array_merge($livre, ['id' => $existant['id']]),
                'action' => url('/livre/' . $existant['id'] . '/maj'),
                'titre_page' => 'Modifier : ' . ($livre['titre'] ?: 'Livre'),
                'statuts' => $app->config()['statuts'],
                'isbn_saisie' => $livre['isbn'] ?? '',
                'erreurs' => $erreurs,
                'page' => 'livre',
            ]), 422);
        }
        $store->save($existant['id'], $livre);
        return Response::redirect(url('/livre/' . $existant['id']));
    }

    public function supprimer(Request $req, array $params): Response
    {
        $app = App::get();
        $app->store('livres')->delete($params['id']);
        // Supprime aussi les fiches associées
        foreach ($app->store('fiches')->query(fn ($f) => ($f['livre_id'] ?? null) === $params['id']) as $id => $f) {
            $app->store('fiches')->delete($id);
        }
        return Response::redirect(url('/'));
    }

    private function reponseErreur(Request $req, string $message, string $isbnSaisi, ?array $livre = null): Response
    {
        $app = App::get();
        return Response::html($app->view()->render('livres/form', [
            'livre' => $livre ?? [],
            'action' => url('/livres'),
            'titre_page' => 'Ajouter un livre',
            'statuts' => $app->config()['statuts'],
            'isbn_saisie' => $isbnSaisi,
            'erreurs' => [$message],
            'page' => 'nouveau',
        ]), 422);
    }
}
