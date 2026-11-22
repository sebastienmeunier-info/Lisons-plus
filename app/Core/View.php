<?php
namespace App\Core;

/**
 * Moteur de vues PHP minimal avec layout.
 */
class View
{
    public function __construct(
        private string $viewsDir,
        private array $config
    ) {
    }

    public function render(string $template, array $params = [], ?string $layout = 'layout'): string
    {
        $params['config'] = $this->config;
        $params['base_url'] = App::baseUrl();
        $content = $this->renderPartial($template, $params);
        if ($layout === null) {
            return $content;
        }
        $params['content'] = $content;
        return $this->renderPartial($layout, $params);
    }

    public function partial(string $template, array $params = []): string
    {
        $params['config'] = $this->config;
        $params['base_url'] = App::baseUrl();
        return $this->renderPartial($template, $params);
    }

    private function renderPartial(string $template, array $params): string
    {
        $file = $this->viewsDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vue introuvable : ' . $template);
        }
        extract($params, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /** Échappement HTML systématique. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * URL d'un asset statique (CSS, JS, image, couverture uploadée) : toujours un
     * vrai chemin /lisons/public/… — JAMAIS le mode query-string, sinon le fichier
     * ne serait pas servi (la requête passerait par index.php).
     */
    public static function asset(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path; // URL absolue (ex. logo_url externe)
        }
        return App::baseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * URL absolue à partir d'un chemin relatif.
     * Mode 'path' (défaut) : /lisons/connexion — nécessite une réécriture serveur.
     * Mode 'qs' (sans réécriture) : /lisons/?/connexion — /lisons/?isbn=… reste
     * une recherche publique sur la racine.
     */
    public static function url(string $path = ''): string
    {
        $base = App::baseUrl();
        $mode = App::get()->config()['url_mode'] ?? 'path';
        if ($mode === 'qs') {
            if ($path === '' || $path === '/') {
                return $base . '/';
            }
            if (str_starts_with($path, '/?')) {
                // paramètres sur la racine : /lisons/?isbn=…
                return $base . '/' . ltrim($path, '/');
            }
            $p = ltrim(str_replace('?', '&', $path), '/&');
            return $base . '/?/' . $p;
        }
        $path = '/' . ltrim($path, '/');
        return $base === '' ? $path : $base . $path;
    }
}
