<?php
/**
 * Fonctions utilitaires globales.
 */

use App\Core\App;
use App\Core\View;

// Polyfill : certains builds de mbstring n'exposent pas mb_str_contains (PHP >= 8.0 requis sinon)
if (!function_exists('mb_str_contains')) {
    function mb_str_contains(string $haystack, string $needle, ?string $encoding = null): bool
    {
        if ($needle === '') {
            return true;
        }
        return mb_strpos($haystack, $needle, 0, $encoding ?? 'UTF-8') !== false;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return View::e($value);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return View::url($path);
    }
}

/** Génère un identifiant court unique. */
function uuid_short(int $length = 12): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($alphabet);
    $out = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[ord($bytes[$i]) % $max];
    }
    return $out;
}

/** Token de partage lisible (sans caractères ambigus). */
function token_partage(): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    $bytes = random_bytes(16);
    for ($i = 0; $i < 16; $i++) {
        $out .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
    }
    return $out;
}

/** Mini-markdown sécurisé : titres, gras, italique, citations, listes, liens. */
function mini_markdown(string $text): string
{
    $html = e($text);
    // Titres
    $html = preg_replace('/^###\s+(.+)$/mu', '<h3>$1</h3>', $html);
    $html = preg_replace('/^##\s+(.+)$/mu', '<h2>$1</h2>', $html);
    $html = preg_replace('/^#\s+(.+)$/mu', '<h2>$1</h2>', $html);
    // Gras / italique / code inline
    $html = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $html);
    $html = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $html);
    // Citations
    $html = preg_replace('/^&gt;\s?(.*)$/mu', '<blockquote>$1</blockquote>', $html);
    // Listes à puces
    $html = preg_replace('/^(?:\*|-)\s+(.+)$/mu', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>\n?)+/u', '<ul>$0</ul>', $html);
    // Liens [texte](url) — uniquement http(s)
    $html = preg_replace(
        '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u',
        '<a href="$2" target="_blank" rel="noopener noreferrer nofollow">$1</a>',
        $html
    );
    // Retours à la ligne
    $html = nl2br($html);
    return $html;
}

/** Formate une date ISO (Y-m-d) en français. */
function date_fr(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $t = strtotime($iso);
    return $t ? date('j n Y', $t) : $iso;
}

/** Raccourci : accès au magasin de données. */
function store(string $name): \App\Core\JsonStore
{
    return App::get()->store($name);
}

/** Champ caché CSRF à inclure dans tous les formulaires POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(App::get()->auth()->csrfToken()) . '">';
}

/** Libellé d'un rôle. */
function role_label(string $role): string
{
    return App::get()->config()['auth']['role_labels'][$role] ?? $role;
}

/** Initiales / nom affiché d'un utilisateur. */
function user_display(?array $user): string
{
    if (!$user) {
        return '';
    }
    $prenom = trim($user['prenom'] ?? '');
    $nom = trim($user['nom'] ?? '');
    return trim($prenom . ' ' . $nom) ?: ($user['email'] ?? '');
}

/**
 * Liens de partage publics générés à partir des champs d'un livre :
 * /?isbn=…, /?auteur=…, /?tag=…, /?q=…
 * @return array<string,string> libellé => URL absolue
 */
function liens_partage_livre(array $livre): array
{
    $liens = [];
    if (!empty($livre['isbn'])) {
        $liens['ISBN ' . $livre['isbn']] = url('/?isbn=' . rawurlencode($livre['isbn']));
    }
    foreach ($livre['auteurs'] ?? [] as $a) {
        $liens['Auteur : ' . $a] = url('/?auteur=' . rawurlencode($a));
    }
    foreach ($livre['tags'] ?? [] as $t) {
        $liens['Étiquette : ' . $t] = url('/?tag=' . rawurlencode($t));
    }
    if (!empty($livre['titre'])) {
        $liens['Titre : ' . $livre['titre']] = url('/?q=' . rawurlencode($livre['titre']));
    }
    return $liens;
}

/** Normalise les clés de query reçues (ISBN -> isbn, Auteur -> auteur…). */
function query_params(\App\Core\Request $req): array
{
    $out = [];
    foreach ($req->all() as $k => $v) {
        if (is_string($v)) {
            $out[\mb_strtolower((string) $k)] = $v;
        }
    }
    return $out;
}
