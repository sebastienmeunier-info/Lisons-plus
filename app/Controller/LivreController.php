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
        // Auto-réparation : pas de couverture affichable mais un ISBN existe
        // → on tente de récupérer la couverture (cache 7 j, donc quasi gratuit).
        if (couverture_src($livre) === null) {
            $this->reparerCouverture($livre, (string) ($livre['isbn'] ?? ''), true);
        }
        $fiches = $app->store('fiches')->query(fn ($f) => ($f['livre_id'] ?? null) === $livre['id'] && ($f['user_id'] ?? '') === $user['id']);
        $html = $app->view()->render('livres/voir', [
            'livre' => $livre,
            'fiches' => $fiches,
            'liens_partage' => liens_partage_livre($livre),
            'statuts' => $app->config()['statuts'],
            'message' => (string) $req->input('message', ''),
            'erreur' => (string) $req->input('erreur', ''),
            'csrfToken' => $app->auth()->csrfToken(),
            'user' => $user,
            'page' => 'livre',
        ]);
        return Response::html($html);
    }

    /**
     * POST /livre/{id}/couverture — recharge la couverture via l'ISBN saisi
     * (utile pour les livres ajoutés à la main, sans ISBN, à l'époque où la
     * recherche ISBN ne fonctionnait pas encore côté serveur).
     */
    public function rechargerCouverture(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $livre = $app->store('livres')->find($params['id']);
        if (!$this->peutVoir($livre, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        $isbnSaisi = (string) $req->input('isbn', '');
        if (\App\Model\Livre::nettoyerIsbn($isbnSaisi !== '' ? $isbnSaisi : (string) ($livre['isbn'] ?? '')) === '') {
            return Response::redirect(View::url('/livre/' . $livre['id'] . '?erreur=' . rawurlencode('Saisis d’abord l’ISBN du livre dans le champ prévu à cet effet.')));
        }
        $msg = $this->reparerCouverture($livre, $isbnSaisi, true);
        $cible = '/livre/' . $livre['id'] . ($msg !== null
            ? '?message=' . rawurlencode($msg)
            : '?erreur=' . rawurlencode('Aucune couverture trouvée pour cet ISBN (vérifie la saisie ou réessaie dans quelques secondes).'));
        return Response::redirect(View::url($cible));
    }

    /**
     * Tente de récupérer la couverture d'un livre via Open Library.
     * Met à jour $livre (couverture, couverture_url, isbn) et sauvegarde si $save.
     * @return string|null message de succès, ou null si rien n'a pu être fait
     */
    private function reparerCouverture(array &$livre, string $isbnSaisi, bool $save): ?string
    {
        $app = App::get();
        $isbn = \App\Model\Livre::nettoyerIsbn($isbnSaisi !== '' ? $isbnSaisi : (string) ($livre['isbn'] ?? ''));
        if ($isbn === '') {
            return null;
        }
        if (($livre['isbn'] ?? '') === '') {
            $livre['isbn'] = $isbn;
        }
        // 1) Tentative RAPIDE : CDN des couvertures directement par ISBN
        //    (1 appel, 404 propre si aucune couverture — ?default=false)
        $cdnUrl = $this->isbn()->couvertureUrlIsbn($isbn);
        try {
            $path = $this->isbn()->telechargerCouverture($cdnUrl);
        } catch (\Throwable $e) {
            $app->log('reparerCouverture CDN : ' . $e->getMessage());
            $path = null;
        }
        if ($path) {
            $livre['couverture'] = $path;
            $livre['couverture_url'] = str_replace('?default=false', '', $cdnUrl);
            if ($save) {
                $app->store('livres')->save($livre['id'], $livre);
            }
            return 'Couverture récupérée.';
        }
        // 2) Repli : métadonnées complètes (cachées 7 j) + couverture par id
        try {
            $meta = $this->isbn()->lookup($isbn);
        } catch (\Throwable $e) {
            $app->log('reparerCouverture ' . $isbn . ' : ' . $e->getMessage());
            return null;
        }
        if (!is_array($meta)) {
            return null;
        }
        $coverUrl = !empty($meta['couverture_id'])
            ? $this->isbn()->couvertureUrl((int) $meta['couverture_id'])
            : null;
        if ($coverUrl === null) {
            return null; // métadonnées trouvées mais vraiment aucune couverture
        }
        if (empty($livre['couverture_url'])) {
            $livre['couverture_url'] = $coverUrl;
        }
        try {
            $path = $this->isbn()->telechargerCouverture($coverUrl);
            if ($path) {
                $livre['couverture'] = $path;
            }
        } catch (\Throwable $e) {
            $app->log('reparerCouverture download : ' . $e->getMessage());
        }
        if ($save) {
            $app->store('livres')->save($livre['id'], $livre);
        }
        return $path
            ? 'Couverture récupérée pour « ' . ($meta['titre'] ?? $isbn) . ' ».'
            : 'Métadonnées trouvées, couverture affichée via le lien distant.';
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
        // ISBN saisi/modifié sans couverture locale → on tente de la récupérer
        if (($livre['couverture'] ?? '') === '' && (string) ($livre['isbn'] ?? '') !== '') {
            $this->reparerCouverture($livre, (string) $livre['isbn'], false);
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
