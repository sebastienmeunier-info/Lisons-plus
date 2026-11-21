<?php
namespace App\Core;

/**
 * Routeur minimal : patterns du type "/livre/{id}" avec paramètres nommés.
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:callable, name:string}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, string $name = ''): void
    {
        $this->add('GET', $pattern, $handler, $name);
    }

    public function post(string $pattern, callable $handler, string $name = ''): void
    {
        $this->add('POST', $pattern, $handler, $name);
    }

    private function add(string $method, string $pattern, callable $handler, string $name): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#u';
        $this->routes[] = compact('method', 'pattern', 'regex', 'handler', 'name');
    }

    /**
     * @return array{handler:callable, params:array<string,string>}|null
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
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }
        return null;
    }
}
