<?php
namespace App\Core;

use App\Service\AuthService;

/**
 * Conteneur minimal : config, magasins, services, gardes d'accès et dispatch.
 */
class App
{
    private static ?App $instance = null;
    private Router $router;
    private View $view;
    /** @var array<string, JsonStore> */
    private array $stores = [];
    /** @var array<string, mixed> */
    private array $services = [];

    private function __construct(private array $config)
    {
        $this->router = new Router();
        $this->view = new View($config['paths']['views'], $config);
    }

    public static function boot(array $config): self
    {
        self::$instance = new self($config);
        return self::$instance;
    }

    public static function get(): self
    {
        if (!self::$instance) {
            throw new \RuntimeException('App non initialisée');
        }
        return self::$instance;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function view(): View
    {
        return $this->view;
    }

    public function store(string $name): JsonStore
    {
        if (!isset($this->stores[$name])) {
            $file = $this->config['paths']['data'] . '/' . $this->config['data_files'][$name];
            $this->stores[$name] = new JsonStore($file);
        }
        return $this->stores[$name];
    }

    public function auth(): AuthService
    {
        return $this->service('auth', fn ($app) => new AuthService($app->config()));
    }

    /** Service paresseux (fabrique enregistrée sous une clé). */
    public function service(string $name, callable $factory): mixed
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $factory($this);
        }
        return $this->services[$name];
    }

    public function dispatch(Request $request): Response
    {
        $match = $this->router->match($request->method(), $request->path());
        if (!$match) {
            return Response::html(
                $this->view->render('erreur', ['code' => 404, 'message' => 'Page introuvable.'], null),
                404
            );
        }
        $options = $match['options'];

        // Garde-fous d'accès
        if (!empty($options['auth']) || !empty($options['role'])) {
            $user = $this->auth()->currentUser();
            if (!$user) {
                $next = rawurlencode($request->pathWithQuery());
                return Response::redirect(View::url('/connexion?next=' . $next));
            }
            if (!empty($options['role']) && !$this->auth()->hasRole($user, $options['role'])) {
                return Response::html(
                    $this->view->render('erreur', ['code' => 403, 'message' => 'Accès réservé aux comptes ayant un rôle suffisant (' . ($this->config['auth']['role_labels'][$options['role']] ?? $options['role']) . ').'], null),
                    403
                );
            }
        }

        // Vérification CSRF sur tous les POST (sauf option contraire)
        if ($request->isPost() && ($options['csrf'] ?? true)) {
            if (!$this->auth()->verifyCsrf((string) $request->input('csrf_token', ''))) {
                return Response::html(
                    $this->view->render('erreur', ['code' => 419, 'message' => 'Session expirée (jeton CSRF invalide) : revenez en arrière et réessayez.'], null),
                    419
                );
            }
        }

        try {
            $result = ($match['handler'])($request, $match['params']);
            if ($result instanceof Response) {
                return $result;
            }
            return Response::html((string) $result);
        } catch (\Throwable $e) {
            $this->log('ERROR ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (($this->config['debug'] ?? false)) {
                $html = $this->view->render('erreur', [
                    'code' => 500,
                    'message' => $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')',
                ], null);
            } else {
                $html = $this->view->render('erreur', ['code' => 500, 'message' => 'Une erreur est survenue.'], null);
            }
            return Response::html($html, 500);
        }
    }

    public function log(string $message): void
    {
        // Fichier .php protégé par une garde PHP (exit en tête) : jamais servi en clair par le
        // serveur web s'il reste dans le webroot.
        $file = $this->config['paths']['logs'] . '/app.log.php';
        if (!file_exists($file)) {
            @file_put_contents($file, "<?php exit; ?>\n", LOCK_EX);
            @unlink($this->config['paths']['logs'] . '/app.log'); // ancien log non gardé
        }
        $line = '[' . date('c') . '] ' . $message . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** URL de base absolue (sans slash final) ; détection auto si config vide. */
    public static function baseUrl(): string
    {
        $config = self::get()->config();
        $base = $config['base_url'] ?? '';
        if ($base !== '') {
            return rtrim($base, '/');
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = dirname($script);
        if ($dir === '.' || $dir === '/' || $dir === '\\') {
            $dir = '';
        }
        return $scheme . '://' . $host . $dir;
    }
}
