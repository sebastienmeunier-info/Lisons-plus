<?php
namespace App\Core;

/**
 * Enveloppe légère autour des superglobales.
 */
class Request
{
    private string $method;
    private string $uri;
    /** Reste de la query string quand celle-ci contenait une route en mode QS (ex. /?/stats&next=/x). */
    private string $qsReste = '';

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Chemin de la requête RELATIF au dossier de l'application
     * (ex. déploiement en sous-dossier /lisons/ : "/lisons/connexion" -> "/connexion").
     * Fonctionne à la racine comme en sous-dossier, avec ou sans réécriture.
     */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = rtrim(dirname($script), '/'); // '' à la racine, '/lisons' en sous-dossier
        if ($dir !== '' && ($path === $dir || str_starts_with($path, $dir . '/'))) {
            $path = substr($path, strlen($dir));
        }
        if ($path === '/index.php') {
            $path = '/';
        }
        $path = rtrim($path, '/') ?: '/';
        // Mode query-string (déploiement sans réécriture serveur) :
        // /lisons/?/connexion&next=/x  →  path = /connexion, $_GET['next'] = /x
        if ($path === '/') {
            $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
            if ($qs !== '' && $qs[0] === '/') {
                $parts = preg_split('/[&?]/', $qs, 2);
                $route = $parts[0];
                $this->qsReste = $parts[1] ?? '';
                if ($this->qsReste !== '') {
                    parse_str($this->qsReste, $params);
                    $_GET = array_merge($params, $_GET);
                }
                $path = rtrim($route, '/') ?: '/';
            }
        }
        return $path;
    }

    /** path() + query string restante (pour les redirections « next »). */
    public function pathWithQuery(): string
    {
        $qs = $this->qsReste !== '' ? $this->qsReste : (string) ($_SERVER['QUERY_STRING'] ?? '');
        // si la query string était une route QS (commence par '/'), elle a déjà été extraite
        if ($qs !== '' && $qs[0] === '/') {
            $qs = '';
        }
        return $this->path() . ($qs !== '' ? '?' . $qs : '');
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** Tableau de chaînes non vides (ex: tags saisis séparés par des virgules). */
    public function list(string $key): array
    {
        $raw = $this->input($key, '');
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[;,]/u', (string) $raw) ?: [];
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        if (empty($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 'on', 'true', 'oui'], true);
    }
}
