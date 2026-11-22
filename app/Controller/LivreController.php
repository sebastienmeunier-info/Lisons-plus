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
        $flash = '';
        if ($isbnSaisi !== '') {
            if ($this->isbn()->normaliser($isbnSaisi) === null) {
                return $this->reponseErreur($req, 'ISBN invalide (10 ou 13 chiffres, checksum faux). Vérifie la saisie.', $isbnSaisi);
            }
            try {
                $meta = $this->isbn()->lookup($isbnSaisi);
            } catch (\Throwable $e) {
                $app->log('livre creer lookup ' . $isbnSaisi . ' : ' . $e->getMessage());
                $meta = null;
            }
            if ($meta === null) {
                // Open Library indisponible/introuvable : on enregistre quand même le
                // livre AVEC son ISBN → la couverture sera récupérée automatiquement
                // à l'affichage de la page (auto-réparation).
                $flash = 'Livre ajouté avec son ISBN, mais métadonnées Open Library indisponibles pour le moment — la couverture sera récupérée automatiquement.';
            }
        }
        $post = $req->all();
        // L'ISBN peut arriver par l'URL du formulaire (?isbn=…) si le champ POST est vide
        if (trim((string) ($post['isbn'] ?? '')) === '' && $isbnSaisi !== '') {
            $post['isbn'] = $isbnSaisi;
        }
        $livre = Livre::depuisFormulaire($post, $app->config()['statuts'], $meta);
        $livre['user_id'] = $user['id'];
        $id = uuid_short();
        $livre['id'] = $id;
        // Couverture téléchargée AUTOMATIQUEMENT à la création si l'ISBN est connu
        // (CDN par ISBN d'abord, sinon id de couverture des métadonnées).
        if ((string) ($livre['isbn'] ?? '') !== '') {
            $this->reparerCouverture($livre, (string) $livre['isbn'], false, $meta);
        }
        $erreurs = Livre::valide($livre);
        if ($erreurs) {
            return $this->reponseErreur($req, implode(' ', $erreurs), $isbnSaisi, $livre);
        }
        $app->store('livres')->save($id, $livre);
        $cible = '/livre/' . $id . ($flash !== '' ? '?message=' . rawurlencode($flash) : '');
        return Response::redirect(url($cible));
    }

    public function voir(Request $req, array $params): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $livre = $app->store('livres')->find($params['id']);
        if (!$this->peutVoir($livre, $user)) {
            return Response::html($app->view()->render('erreur', ['code' => 404, 'message' => 'Livre introuvable.'], null), 404);
        }
        // Auto-réparation : dès que le fichier local manque (même si une URL
        // distante existe) et qu'un ISBN est connu → téléchargement local auto
        // (cache 7 j : aucun appel HTTP répété une fois la couverture en place).
        $couvLocale = trim((string) ($livre['couverture'] ?? ''));
        $root = rtrim((string) $app->config()['paths']['root'], '/');
        $localOk = $couvLocale !== '' && is_file($root . '/' . $couvLocale);
        if (!$localOk && trim((string) ($livre['isbn'] ?? '')) !== '') {
            $this->reparerCouverture($livre, (string) $livre['isbn'], true);
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
     * Délègue à IsbnService::reparer() (logique partagée avec l'accueil).
     */
    private function reparerCouverture(array &$livre, string $isbnSaisi, bool $save, ?array $meta = null): ?string
    {
        return $this->isbn()->reparer($livre, $isbnSaisi, $save, $meta);
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
        // supprime aussi le fichier de couverture local (uploads/covers/…)
        $couv = trim((string) ($existant['couverture'] ?? ''));
        if ($couv !== '' && str_starts_with($couv, 'uploads/')) {
            $path = rtrim((string) $app->config()['paths']['root'], '/') . '/' . ltrim($couv, '/');
            if (is_file($path)) {
                @unlink($path);
            }
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
