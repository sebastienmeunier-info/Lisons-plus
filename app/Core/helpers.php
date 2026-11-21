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
