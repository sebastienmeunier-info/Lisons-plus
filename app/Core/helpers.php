<?php
/**
 * Fonctions utilitaires globales.
 */

use App\Core\App;
use App\Core\View;

/*
 * Polyfills mbstring : certains hébergements PHP n'ont pas l'extension mbstring
 * (ou un build partiel). On fournit les fonctions utilisées par l'application,
 * basées sur iconv si disponible, sinon sur les fonctions natives/RegExp UTF-8.
 */
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s, ?string $encoding = null): string
    {
        if (function_exists('iconv')) {
            // ASCII//TRANSLIT approxime bien la casse UTF-8 pour la recherche plein texte
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t)) {
                return strtolower($t);
            }
        }
        return strtolower($s);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s, ?string $encoding = null): string
    {
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t)) {
                return strtoupper($t);
            }
        }
        return strtoupper($s);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $encoding = null): int
    {
        if (function_exists('iconv_strlen')) {
            return iconv_strlen($s, 'UTF-8');
        }
        return (int) preg_match_all('/./us', $s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (function_exists('iconv_substr')) {
            return (string) iconv_substr($s, $start, $length ?? mb_strlen($s), 'UTF-8');
        }
        preg_match_all('/./us', $s, $m);
        $chars = $m[0];
        return implode('', array_slice($chars, $start, $length));
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        if (function_exists('iconv_strpos')) {
            return iconv_strpos($haystack, $needle, $offset, 'UTF-8');
        }
        $pos = strpos($haystack, $needle, $offset);
        return $pos === false ? false : $pos;
    }
}
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

if (!function_exists('asset')) {
    /** Asset statique (css/js/img/couverture) : vrai chemin, jamais le mode QS. */
    function asset(string $path): string
    {
        return View::asset($path);
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

/**
 * URL publique d'une fiche de lecture : /?partage={token}
 * (query string sur la racine — fonctionne avec OU sans réécriture serveur).
 */
function url_partage(string $token): string
{
    return url('/?partage=' . rawurlencode($token));
}

/**
 * URL d'affichage de la couverture d'un livre :
 *  1. fichier local uploads/covers/… s'il existe vraiment sur le disque ;
 *  2. sinon l'URL distante Open Library (couverture_url) si connue ;
 *  3. sinon null (les vues affichent alors le repli 📖).
 */
function couverture_src(?array $livre): ?string
{
    if (!$livre) {
        return null;
    }
    $couv = trim((string) ($livre['couverture'] ?? ''));
    if ($couv !== '') {
        $root = rtrim((string) (App::get()->config()['paths']['root'] ?? ''), '/');
        $path = $root . '/' . ltrim($couv, '/');
        if ($root !== '' && is_file($path)) {
            // Auto-guérison : un fichier créé en 0600 (tempnam) est illisible
            // pour le serveur web → on le rend lisible à la volée.
            $perm = @fileperms($path);
            if ($perm !== false && !($perm & 0044)) {
                @chmod($path, 0644);
            }
            return asset($couv);
        }
    }
    $dist = trim((string) ($livre['couverture_url'] ?? ''));
    if ($dist !== '' && preg_match('#^https?://#i', $dist)) {
        return $dist;
    }
    return null;
}

/**
 * Réglage « partager mes fiches par défaut » du lecteur :
 * champ profil s'il est renseigné, sinon la valeur par défaut de la config
 * (cochée par défaut).
 */
function partage_par_defaut(?array $user): bool
{
    $v = $user['partage_par_defaut'] ?? null;
    if ($v !== null) {
        return (bool) $v;
    }
    return (bool) (App::get()->config()['partage_par_defaut'] ?? true);
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
