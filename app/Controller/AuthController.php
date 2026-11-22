<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Service\AuthService;

/**
 * Inscription, connexion, déconnexion, mot de passe oublié (calqué sur Plantons-plus).
 */
class AuthController
{
    private function auth(): AuthService
    {
        return App::get()->auth();
    }

    private function page(string $template, ?string $message = null, int $status = 200, array $extra = []): Response
    {
        $app = App::get();
        $params = array_merge([
            'csrfToken' => $this->auth()->csrfToken(),
            'user' => $this->auth()->currentUser(),
            'message' => $message,
            'auth_labels' => $app->config()['auth']['role_labels'],
        ], $extra);
        return Response::html($app->view()->render($template, $params, 'layout_public'), $status);
    }

    public function registerForm(Request $req): Response
    {
        if ($this->auth()->currentUser()) {
            return Response::redirect(View::url('/'));
        }
        return $this->page('auth/register', null, 200, ['next' => (string) $req->input('next', '')]);
    }

    public function register(Request $req): Response
    {
        $app = App::get();
        $authCfg = $app->config()['auth'];
        try {
            $prenom = \mb_substr(trim((string) $req->input('prenom')), 0, 80);
            $nom = \mb_substr(trim((string) $req->input('nom')), 0, 80);
            $email = \mb_strtolower(trim((string) $req->input('email')));
            $password = (string) $req->input('password');
            $passwordConfirm = (string) $req->input('password_confirm');
            $next = (string) $req->input('next', '');

            if ($prenom === '' || $nom === '') {
                throw new \InvalidArgumentException('Renseignez votre prénom et votre nom.');
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Adresse e-mail invalide.');
            }
            $min = (int) ($authCfg['password_min_length'] ?? 12);
            if (\strlen($password) < $min) {
                throw new \InvalidArgumentException("Le mot de passe doit contenir au moins $min caractères.");
            }
            if ($password !== $passwordConfirm) {
                throw new \InvalidArgumentException('La confirmation du mot de passe ne correspond pas.');
            }
            if ($this->auth()->findByEmail($email)) {
                throw new \InvalidArgumentException('Cette adresse e-mail est déjà utilisée.');
            }

            $store = $app->store('users');
            $isFirstUser = $store->all() === [];
            $role = ($isFirstUser || ($authCfg['bootstrap_super_admin_email'] !== '' && $email === $authCfg['bootstrap_super_admin_email']))
                ? 'super_administrateur'
                : ((($authCfg['bootstrap_admin_email'] !== '' && $email === $authCfg['bootstrap_admin_email'])
                    || in_array($email, $authCfg['administrator_emails'], true)) ? 'administrateur' : 'contributeur');
            $status = !$isFirstUser && !empty($authCfg['validation_inscription_obligatoire']) ? 'pending' : 'approved';

            $user = [
                'id' => bin2hex(random_bytes(16)),
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'registration_status' => $status,
                'created_at' => date(DATE_ATOM),
                'updated_at' => date(DATE_ATOM),
            ];
            $store->save($user['id'], $user);

            if ($status === 'pending') {
                return $this->page('auth/register', 'Votre inscription est en attente de validation par un administrateur.', 200, ['next' => $next]);
            }
            $this->auth()->login($email, $password);
            $cible = $next !== '' && str_starts_with($next, '/') ? $next : '/';
            return Response::redirect(View::url($cible));
        } catch (\InvalidArgumentException $e) {
            return $this->page('auth/register', $e->getMessage(), 422, ['next' => (string) $req->input('next', '')]);
        }
    }

    public function loginForm(Request $req): Response
    {
        if ($this->auth()->currentUser()) {
            return Response::redirect(View::url('/'));
        }
        return $this->page('auth/login', null, 200, ['next' => (string) $req->input('next', '')]);
    }

    public function login(Request $req): Response
    {
        $email = \mb_strtolower(trim((string) $req->input('email')));
        $next = (string) $req->input('next', '');
        if (!$this->auth()->login($email, (string) $req->input('password'))) {
            $user = $this->auth()->findByEmail($email);
            $msg = ($user && ($user['registration_status'] ?? 'approved') === 'pending')
                ? 'Votre inscription est en attente de validation par un administrateur.'
                : 'Identifiants invalides.';
            return $this->page('auth/login', $msg, 422, ['next' => $next]);
        }
        $cible = $next !== '' && str_starts_with($next, '/') ? $next : '/';
        return Response::redirect(View::url($cible));
    }

    public function logout(Request $req): Response
    {
        $this->auth()->logout();
        return Response::redirect(View::url('/'));
    }

    public function forgotForm(Request $req): Response
    {
        return $this->page('auth/forgot');
    }

    /**
     * Demande de réinitialisation : un jeton est stocké (hashé) dans
     * data/reinitialisations.json. Sans SMTP configuré, le lien est journalisé
     * (logs/app.log) et affiché si LISONS_DEBUG=1.
     */
    public function forgot(Request $req): Response
    {
        $app = App::get();
        $email = \mb_strtolower(trim((string) $req->input('email')));
        $user = $this->auth()->findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $resets = array_values(array_filter($app->store('resets')->all(), fn ($r) => ($r['email'] ?? '') !== $email));
            $resets[] = ['id' => bin2hex(random_bytes(8)), 'email' => $email, 'token_hash' => password_hash($token, PASSWORD_DEFAULT), 'expires_at' => time() + 3600];
            // réécrit la liste complète
            $store = $app->store('resets');
            foreach ($store->all() as $id => $r) {
                $store->delete($id);
            }
            foreach ($resets as $r) {
                $store->save($r['id'], $r);
            }
            $url = View::url('/reinitialiser-mot-de-passe?token=' . rawurlencode($token));
            $app->log('RESET_MDP ' . $email . ' ' . $url);
            if (!empty($app->config()['debug'])) {
                return $this->page('auth/forgot', 'Mode debug : lien de réinitialisation : ' . $url);
            }
        }
        return $this->page('auth/forgot', 'Si cette adresse existe, un lien de réinitialisation a été généré (voir journal de l’application).');
    }

    public function resetForm(Request $req): Response
    {
        return $this->page('auth/reset', null, 200, ['token' => (string) $req->input('token', '')]);
    }

    public function reset(Request $req): Response
    {
        $app = App::get();
        $token = (string) $req->input('token');
        $password = (string) $req->input('password');
        $min = (int) ($app->config()['auth']['password_min_length'] ?? 12);
        try {
            if (\strlen($password) < $min) {
                throw new \InvalidArgumentException("Le mot de passe doit contenir au moins $min caractères.");
            }
            $store = $app->store('resets');
            $found = null;
            foreach ($store->all() as $id => $reset) {
                if ((int) ($reset['expires_at'] ?? 0) >= time() && password_verify($token, (string) ($reset['token_hash'] ?? ''))) {
                    $found = [$id, $reset];
                    break;
                }
            }
            if ($found === null) {
                throw new \InvalidArgumentException('Ce lien est invalide ou expiré.');
            }
            [$id, $reset] = $found;
            $user = $this->auth()->findByEmail($reset['email']);
            if ($user) {
                $app->store('users')->save($user['id'], array_merge($user, [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'updated_at' => date(DATE_ATOM),
                ]));
            }
            $store->delete($id);
            return $this->page('auth/login', 'Mot de passe mis à jour : vous pouvez vous connecter.');
        } catch (\InvalidArgumentException $e) {
            return $this->page('auth/reset', $e->getMessage(), 422, ['token' => $token]);
        }
    }
}
