<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Service\AuthService;

/**
 * Espace admin : tableau de bord, gestion des utilisateurs (3 rôles
 * hiérarchiques, règles calquées sur Plantons-plus) et modération des contenus.
 */
class AdminController
{
    private function auth(): AuthService
    {
        return App::get()->auth();
    }

    public function index(Request $req): Response
    {
        $app = App::get();
        $users = $app->store('users')->all();
        $livres = $app->store('livres')->all();
        $fiches = $app->store('fiches')->all();
        $stats = [
            'users' => count($users),
            'users_pending' => count(array_filter($users, fn ($u) => ($u['registration_status'] ?? 'approved') === 'pending')),
            'livres' => count($livres),
            'fiches' => count($fiches),
            'fiches_publiques' => count(array_filter($fiches, fn ($f) => !empty($f['publique']))),
        ];
        // Alerte si les données sont encore servies depuis le webroot
        $paths = $app->config()['paths'];
        $dataDansWebroot = $paths['data'] === $paths['root'] . '/data' && file_exists($paths['root'] . '/data/livres.json');
        $html = $app->view()->render('admin/index', [
            'user' => $this->auth()->currentUser(),
            'stats' => $stats,
            'dataDansWebroot' => $dataDansWebroot,
            'dataDir' => $paths['data'],
            'page' => 'admin',
            'role_labels' => $app->config()['auth']['role_labels'],
        ]);
        return Response::html($html);
    }

    public function users(Request $req): Response
    {
        $app = App::get();
        $moi = $this->auth()->currentUser();
        $users = $app->store('users')->all();
        // tri : super admin, admins, contributeurs, puis date
        uasort($users, function ($a, $b) use ($app) {
            $la = $app->auth()->roleLevel($a['role'] ?? '');
            $lb = $app->auth()->roleLevel($b['role'] ?? '');
            return $lb <=> $la ?: strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });
        $html = $app->view()->render('admin/users', [
            'user' => $moi,
            'users' => $users,
            'auth' => $this->auth(),
            'csrfToken' => $this->auth()->csrfToken(),
            'message' => (string) $req->input('message', ''),
            'erreurs' => [],
            'role_labels' => $app->config()['auth']['role_labels'],
            'roles' => $app->config()['auth']['roles'],
            'page' => 'admin',
        ]);
        return Response::html($html);
    }

    public function changeRole(Request $req): Response
    {
        $app = App::get();
        $moi = $this->auth()->currentUser();
        $target = $app->store('users')->find((string) $req->input('user_id'));
        $next = (string) $req->input('role');
        if (!$target || !$this->auth()->mayChangeRole($moi, $target, $next)) {
            return Response::html($app->view()->render('erreur', ['code' => 403, 'message' => 'Cette modification de rôle n’est pas autorisée.'], null), 403);
        }
        $app->store('users')->save($target['id'], array_merge($target, ['role' => $next, 'updated_at' => date(DATE_ATOM)]));
        return Response::redirect(View::url('/admin/utilisateurs?message=' . rawurlencode('Rôle mis à jour.')));
    }

    public function approve(Request $req): Response
    {
        $app = App::get();
        $target = $app->store('users')->find((string) $req->input('user_id'));
        if (!$target) {
            return Response::redirect(View::url('/admin/utilisateurs'));
        }
        $app->store('users')->save($target['id'], array_merge($target, ['registration_status' => 'approved', 'updated_at' => date(DATE_ATOM)]));
        return Response::redirect(View::url('/admin/utilisateurs?message=' . rawurlencode('Inscription validée.')));
    }

    public function deleteUser(Request $req): Response
    {
        $app = App::get();
        $moi = $this->auth()->currentUser();
        $target = $app->store('users')->find((string) $req->input('user_id'));
        if (!$target || !$this->auth()->mayDeleteUser($moi, $target) || $target['id'] === $moi['id']) {
            return Response::html($app->view()->render('erreur', ['code' => 403, 'message' => 'Suppression non autorisée.'], null), 403);
        }
        $app->store('users')->delete($target['id']);
        return Response::redirect(View::url('/admin/utilisateurs?message=' . rawurlencode('Utilisateur supprimé.')));
    }

    /** Modération : toutes les fiches (avec leur livre et leur auteur). */
    public function contenus(Request $req): Response
    {
        $app = App::get();
        $fiches = $app->store('fiches')->all();
        $livres = $app->store('livres')->all();
        $users = $app->store('users')->all();
        uasort($fiches, fn ($a, $b) => strcmp($b['date_creation'] ?? '', $a['date_creation'] ?? ''));
        $html = $app->view()->render('admin/contenus', [
            'user' => $this->auth()->currentUser(),
            'fiches' => $fiches,
            'livres' => $livres,
            'users' => $users,
            'csrfToken' => $this->auth()->csrfToken(),
            'page' => 'admin',
        ]);
        return Response::html($html);
    }

    /** Modération : retirer le caractère public d'une fiche. */
    public function desactiverFiche(Request $req): Response
    {
        $app = App::get();
        $fiche = $app->store('fiches')->find((string) $req->input('fiche_id'));
        if ($fiche) {
            $fiche['publique'] = false;
            $fiche['token'] = null;
            $fiche['date_modif'] = date('Y-m-d H:i:s');
            $app->store('fiches')->save($fiche['id'], $fiche);
        }
        return Response::redirect(View::url('/admin/contenus'));
    }
}
