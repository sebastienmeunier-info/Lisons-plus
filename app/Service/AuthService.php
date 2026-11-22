<?php
namespace App\Service;

use App\Core\App;
use RuntimeException;

/**
 * Authentification par session PHP, calquée sur Plantons-plus :
 * stockage JSON des utilisateurs, cookie de session httponly/samesite,
 * jeton CSRF, rôles hiérarchiques (contributeur < administrateur < super_administrateur).
 */
class AuthService
{
    public function __construct(private array $config)
    {
    }

    private function authConfig(): array
    {
        return $this->config['auth'];
    }

    /** Niveau hiérarchique d'un rôle (plus grand = plus de droits). */
    public function roleLevel(string $role): int
    {
        $roles = $this->authConfig()['roles'];
        $level = array_search($role, $roles, true);
        return $level === false ? 0 : (int) $level + 1;
    }

    public function hasRole(?array $user, string $role): bool
    {
        if (!$user) {
            return false;
        }
        return $this->roleLevel($user['role'] ?? '') >= $this->roleLevel($role);
    }

    public function currentUser(): ?array
    {
        $this->startSession();
        $id = $_SESSION[$this->authConfig()['session_key']] ?? null;
        $user = is_string($id) ? App::get()->store('users')->find($id) : null;
        if ($user !== null && ($user['registration_status'] ?? 'approved') !== 'approved') {
            unset($_SESSION[$this->authConfig()['session_key']]);
            return null;
        }
        return $user;
    }

    public function login(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null
            || ($user['registration_status'] ?? 'approved') !== 'approved'
            || !is_string($user['password_hash'] ?? null)
            || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[$this->authConfig()['session_key']] = $user['id'];
        return $user;
    }

    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION[$this->authConfig()['session_key']]);
    }

    public function findByEmail(string $email): ?array
    {
        $email = \mb_strtolower(trim($email));
        foreach (App::get()->store('users')->all() as $user) {
            if (\mb_strtolower($user['email'] ?? '') === $email) {
                return $user;
            }
        }
        return null;
    }

    public function csrfToken(): string
    {
        $this->startSession();
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    public function verifyCsrf(string $token): bool
    {
        $this->startSession();
        return $token !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }

    /**
     * Règles hiérarchiques de changement de rôle (calquées sur Plantons-plus) :
     * - le rôle d'un super_administrateur est intangible ;
     * - un administrateur peut promouvoir/rétrograder un contributeur (contributeur <-> administrateur) ;
     * - un super_administrateur peut modifier un administrateur (vers contributeur, administrateur
     *   ou super_administrateur) mais ne peut pas promouvoir directement un contributeur super_administrateur.
     */
    public function mayChangeRole(array $actor, array $target, string $next): bool
    {
        $actorRole = $actor['role'] ?? '';
        $targetRole = $target['role'] ?? '';
        $roles = $this->authConfig()['roles'];
        if (!in_array($next, $roles, true)) {
            return false;
        }
        if ($targetRole === 'super_administrateur') {
            return false;
        }
        if ($actorRole === 'administrateur') {
            return $targetRole === 'contributeur' && in_array($next, ['contributeur', 'administrateur'], true);
        }
        if ($actorRole === 'super_administrateur') {
            // un super_administrateur gère les administrateurs (tous rôles possibles,
            // y compris super_administrateur) et peut aussi promouvoir un contributeur
            // (jamais directement super_administrateur).
            if ($targetRole === 'administrateur') {
                return true;
            }
            return $targetRole === 'contributeur' && in_array($next, ['contributeur', 'administrateur'], true);
        }
        return false;
    }

    /** Règles d'édition de profil par un admin (mêmes garde-fous que la suppression). */
    public function mayEditUser(array $actor, array $target): bool
    {
        return $this->mayDeleteUser($actor, $target);
    }

    /** Règles de suppression (un super_administrateur ne peut pas être supprimé). */
    public function mayDeleteUser(array $actor, array $target): bool
    {
        $actorRole = $actor['role'] ?? '';
        $targetRole = $target['role'] ?? '';
        if ($targetRole === 'super_administrateur') {
            return false;
        }
        if ($actorRole === 'administrateur') {
            return $targetRole === 'contributeur';
        }
        if ($actorRole === 'super_administrateur') {
            return in_array($targetRole, ['contributeur', 'administrateur'], true);
        }
        return false;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $sessionsPath = (string) ($this->authConfig()['sessions_path'] ?? '');
        if ($sessionsPath !== '') {
            if (!is_dir($sessionsPath) && !mkdir($sessionsPath, 0700, true) && !is_dir($sessionsPath)) {
                throw new RuntimeException('Impossible de créer le répertoire de sessions.');
            }
            session_save_path($sessionsPath);
        }
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
        session_start();
    }
}
