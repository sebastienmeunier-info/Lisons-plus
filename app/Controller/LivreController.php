<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\Livre;
use App\Service\IsbnService;

class LivreController
{
    private function isbn(): IsbnService
    {
        return App::get()->service('isbn', fn ($app) => new IsbnService($app->config()));
    }

    /** Vrai si l'utilisateur courant peut voir ce livre (propriétaire ou admin). */
    private function peutVoir(?array $livre, array $user): bool
    {
        if (!$livre) {
            return false;
        }
        return ($livre['user_id'] ?? '') === $user['id'] || App::get()->auth()->hasRole($user, 'administrateur');
    }

    public function nouveau(Request $req): Response
    {
        $app = App::get();
        $html = $app->view()->render('livres/form', [
            'livre' => [],
            'action' => url('/livres'),
            'titre_page' => 'Ajouter un livre',
            'statuts' => $app->config()['statuts'],
            'isbn_saisie' => (string) $req->input('isbn', ''),
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $app->auth()->currentUser(),
            'page' => 'nouveau',
        ]);
        return Response::html($html);
    }

    public function creer(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $meta = null;
        $isbnSaisi = (string) $req->input('isbn', '');
        if ($isbnSaisi !== '') {
            try {
                $meta = $this->isbn()->lookup($isbnSaisi);
            } catch (\Throwable $e) {
                $app->log('livre creer lookup ' . $isbnSaisi . ' : ' . $e->getMessage());
                $meta = null;
            }
            if ($meta === null) {
                return $this->reponseErreur($req, 'ISBN invalide, introuvable dans Open Library ou service indisponible. Vérifie la saisie ou remplis les champs à la main.', $isbnSaisi);
            }
            if (!empty($meta['couverture_id'])) {
                try {
                    $coverUrl = $this->isbn()->couvertureUrl((int) $meta['couverture_id']);
                    if ($coverUrl) {
                        $meta['couverture'] = $this->isbn()->telechargerCouverture($coverUrl);
                        $meta['couverture_url'] = $coverUrl;
                    }
                } catch (\Throwable $e) {
                    $app->log('livre creer couverture : ' . $e->getMessage());
                }
            }
        }
        $livre = Livre::depuisFormulaire($req->all(), $app->config()['statuts'], $meta);
        $livre['user_id'] = $user['id'];
        $erreurs = Livre::valide($livre);
        if ($erreurs) {
            return $this->reponseErreur($req, implode(' ', $erreurs), $isbnSaisi, $livre);
        }
        $id = uuid_short();
        $livre['id'] = $id;
        $app->store('livres')->save($id, $livre);
        return Response::redirect(url('/livre/' . $id));
    }

    public function voir(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $livre = $app->store('livres')->find($params['id']);
        if (!$this->peutVoir($livre, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $fiches = $app->store('fiches')->query(fn ($f) => ($f['livre_id'] ?? null) === $livre['id'] && ($f['user_id'] ?? '') === $user['id']);
        $html = $app->view()->render('livres/voir', [
            'livre' => $livre,
            'fiches' => $fiches,
            'liens_partage' => liens_partage_livre($livre),
            'statuts' => $app->config()['statuts'],
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $user,
            'page' => 'livre',
        ]);
        return Response::html($html);
    }

    public function editer(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $livre = $app->store('livres')->find($params['id']);
        if (!$this->peutVoir($livre, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $html = $app->view()->render('livres/form', [
            'livre' => $livre,
            'action' => url('/livre/' . $livre['id'] . '/maj'),
            'titre_page' => 'Modifier : ' . $livre['titre'],
            'statuts' => $app->config()['statuts'],
            'isbn_saisie' => $livre['isbn'] ?? '',
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $user,
            'page' => 'livre',
        ]);
        return Response::html($html);
    }

    public function maj(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $store = $app->store('livres');
        $existant = $store->find($params['id']);
        if (!$this->peutVoir($existant, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $livre = Livre::depuisFormulaire($req->all(), $app->config()['statuts'], $existant);
        $livre['id'] = $existant['id'];
        $livre['user_id'] = $existant['user_id'] ?? $user['id'];
        $livre['date_ajout'] = $existant['date_ajout'] ?? date('Y-m-d');
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
                'csrfToken' => $app->auth()->csrfToken(),
                'user' => $user,
                'page' => 'livre',
            ]), 422);
        }
        $store->save($existant['id'], $livre);
        return Response::redirect(url('/livre/' . $existant['id']));
    }

    public function supprimer(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $existant = $app->store('livres')->find($params['id']);
        if (!$this->peutVoir($existant, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $app->store('livres')->delete($params['id']);
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
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $app->auth()->currentUser(),
            'page' => 'nouveau',
        ]), 422);
    }
}
