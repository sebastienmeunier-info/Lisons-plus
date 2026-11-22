<?php
namespace App\Core;

/**
 * Routeur minimal : patterns du type "/livre/{id}" avec paramètres nommés.
 * Chaque route peut déclarer des options :
 *   ['auth' => true]           : exige une session connectée
 *   ['role' => 'administrateur'] : exige un rôle au moins égal (hiérarchie)
 *   ['csrf' => false]          : désactive la vérification CSRF (POST uniquement)
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:callable, name:string, options:array}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, string $name = '', array $options = []): void
    {
        $this->add('GET', $pattern, $handler, $name, $options);
    }

    public function post(string $pattern, callable $handler, string $name = '', array $options = []): void
    {
        $this->add('POST', $pattern, $handler, $name, $options);
    }

    private function add(string $method, string $pattern, callable $handler, string $name, array $options): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#u';
        $this->routes[] = compact('method', 'pattern', 'regex', 'handler', 'name', 'options');
    }

    /**
     * @return array{handler:callable, params:array<string,string>, options:array}|null
     */
    public function match(string $method, string $uri): ?array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['handler' => $route['handler'], 'params' => $params, 'options' => $route['options']];
            }
        }
        return null;
    }
}
