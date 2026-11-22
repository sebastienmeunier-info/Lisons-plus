<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Espace « mon compte » : profil et changement de mot de passe.
 */
class AccountController
{
    public function index(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $html = $app->view()->render('account/index', [
            'user' => $user,
            'csrfToken' => $app->auth()->csrfToken(),
            'message' => (string) $req->input('message', ''),
            'erreurs' => [],
            'role_labels' => $app->config()['auth']['role_labels'],
            'page' => 'compte',
        ]);
        return Response::html($html);
    }

    public function update(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $erreurs = [];
        try {
            $prenom = \mb_substr(trim((string) $req->input('prenom')), 0, 80);
            $nom = \mb_substr(trim((string) $req->input('nom')), 0, 80);
            $email = \mb_strtolower(trim((string) $req->input('email')));
            if ($prenom === '' || $nom === '') {
                throw new \InvalidArgumentException('Prénom et nom obligatoires.');
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Adresse e-mail invalide.');
            }
            $existant = $app->auth()->findByEmail($email);
            if ($existant && $existant['id'] !== $user['id']) {
                throw new \InvalidArgumentException('Cette adresse e-mail est déjà utilisée par un autre compte.');
            }
            $app->store('users')->save($user['id'], array_merge($user, [
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'updated_at' => date(DATE_ATOM),
            ]));
            return Response::redirect(View::url('/mon-compte?message=' . rawurlencode('Profil mis à jour.')));
        } catch (\InvalidArgumentException $e) {
            $erreurs[] = $e->getMessage();
        }
        $html = $app->view()->render('account/index', [
            'user' => $user,
            'csrfToken' => $app->auth()->csrfToken(),
            'message' => '',
            'erreurs' => $erreurs,
            'role_labels' => $app->config()['auth']['role_labels'],
            'page' => 'compte',
        ]);
        return Response::html($html, 422);
    }

    public function updatePassword(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $min = (int) ($app->config()['auth']['password_min_length'] ?? 12);
        try {
            $old = (string) $req->input('password_old');
            $new = (string) $req->input('password_new');
            $confirm = (string) $req->input('password_confirm');
            if (!password_verify($old, (string) ($user['password_hash'] ?? ''))) {
                throw new \InvalidArgumentException('Ancien mot de passe incorrect.');
            }
            if (\strlen($new) < $min) {
                throw new \InvalidArgumentException("Le nouveau mot de passe doit contenir au moins $min caractères.");
            }
            if ($new !== $confirm) {
                throw new \InvalidArgumentException('La confirmation ne correspond pas.');
            }
            $app->store('users')->save($user['id'], array_merge($user, [
                'password_hash' => password_hash($new, PASSWORD_DEFAULT),
                'updated_at' => date(DATE_ATOM),
            ]));
            return Response::redirect(View::url('/mon-compte?message=' . rawurlencode('Mot de passe mis à jour.')));
        } catch (\InvalidArgumentException $e) {
            $html = $app->view()->render('account/index', [
                'user' => $user,
                'csrfToken' => $app->auth()->csrfToken(),
                'message' => '',
                'erreurs' => [$e->getMessage()],
                'role_labels' => $app->config()['auth']['role_labels'],
                'page' => 'compte',
            ]);
            return Response::html($html, 422);
        }
    }
}
