<?php
/**
 * Configuration centrale de Lisons-plus.
 * Les valeurs sensibles peuvent être surchargées par des variables
 * d'environnement (voir docs/install.md).
 */

$root = dirname(__DIR__);

/*
 * ============================================================
 *  Stockage des données hors du webroot (anti-fuite).
 *  En production (PHP-FPM/Apache), si aucune conf n'est faite,
 *  l'application déplace ELLE-MÊME data/ et logs/ vers
 *  ../../lisons-data et ../../lisons-logs (hors DocumentRoot,
 *  que l'app soit à la racine ou en sous-dossier du webroot).
 *  Priorité : 1. LISONS_DATA_DIR (env) ; 2. migration auto hors
 *  webroot ; 3. ../lisons-data existant ; 4. data/ local (dev).
 * ============================================================
 */

/** Dossier hors webroot pour $nom, créé si possible. 2 niveaux au-dessus = toujours hors DocumentRoot. */
$lisons_dossier_hors_webroot = function (string $root, string $nom): ?string {
    foreach ([$root . '/../../' . $nom, $root . '/../' . $nom] as $candidat) {
        if (is_dir($candidat) && is_writable($candidat)) {
            return $candidat;
        }
        if (@mkdir($candidat, 0775, true) && is_writable($candidat)) {
            return $candidat;
        }
    }
    return null;
};

/** Déplace les *.json et sessions/ de $src vers $dst (sans jamais écraser). */
$lisons_migrer = function (string $src, string $dst): void {
    if (!is_dir($src)) {
        return;
    }
    foreach (glob($src . '/*.json') ?: [] as $f) {
        $cible = $dst . '/' . basename($f);
        if (!file_exists($cible)) {
            @rename($f, $cible);
        }
    }
    if (is_dir($src . '/sessions') && !is_dir($dst . '/sessions')) {
        @rename($src . '/sessions', $dst . '/sessions');
    }
    // marqueurs de protection restants dans l'ancien dossier (Apache)
    @file_put_contents($src . '/.htaccess', "Require all denied\n");
};

$sapi = php_sapi_name();
$enProd = in_array($sapi, ['apache2handler', 'fpm-fcgi', 'cgi', 'cgi-fcgi'], true)
    || (bool) getenv('LISONS_TEST_MIGRATION'); // forcé pour les tests

$dataDir = getenv('LISONS_DATA_DIR') ?: '';
if ($dataDir === '' && $enProd) {
    $hors = $lisons_dossier_hors_webroot($root, 'lisons-data');
    if ($hors !== null && is_dir($root . '/data') && $hors !== $root . '/data') {
        $lisons_migrer($root . '/data', $hors);
        $dataDir = $hors;
    }
}
if ($dataDir === '' && is_dir($root . '/../lisons-data') && is_writable($root . '/../lisons-data')) {
    $dataDir = $root . '/../lisons-data';
}
if ($dataDir === '') {
    $dataDir = $root . '/data';
}

$logsDir = getenv('LISONS_LOGS_DIR') ?: '';
if ($logsDir === '' && $enProd) {
    $horsLogs = $lisons_dossier_hors_webroot($root, 'lisons-logs');
    if ($horsLogs !== null && is_dir($root . '/logs') && $horsLogs !== $root . '/logs') {
        $lisons_migrer($root . '/logs', $horsLogs);
        $logsDir = $horsLogs;
    }
}
if ($logsDir === '' && is_dir($root . '/../lisons-logs') && is_writable($root . '/../lisons-logs')) {
    $logsDir = $root . '/../lisons-logs';
}
if ($logsDir === '') {
    $logsDir = $root . '/logs';
}

/*
 * Mode d'URL : 'path' (défaut, URLs propres /lisons/connexion — nécessite une
 * réécriture serveur : try_files nginx ou .htaccess Apache) ou 'qs' (query-string,
 * fonctionne SANS aucune conf serveur : /lisons/?/connexion). Activer 'qs' via la
 * variable d'environnement LISONS_URL_MODE=qs ou en déposant un fichier
 * config/url_mode contenant « qs » (utile en déploiement FTP seul).
 */
$urlMode = strtolower((string) (getenv('LISONS_URL_MODE') ?: ''));
if ($urlMode === '' && is_file($root . '/config/url_mode')) {
    $urlMode = strtolower(trim((string) file_get_contents($root . '/config/url_mode')));
}
if (!in_array($urlMode, ['path', 'qs'], true)) {
    $urlMode = 'path';
}

return [
    'url_mode' => $urlMode,
    'app_name'    => getenv('LISONS_APP_NAME') ?: 'Lisons-plus',
    // Charte graphique (comme Plantons-plus) : logo + texte du bandeau
    'logo_url'    => getenv('LISONS_LOGO_URL') ?: '/public/img/logo.svg',
    'header_text' => getenv('LISONS_HEADER_TEXT') ?: 'Lisons-plus',
    'base_url'    => rtrim(getenv('LISONS_BASE_URL') ?: '', '/'), // ex: https://livres.example.tld ; vide = détection auto
    'admin_email' => getenv('LISONS_ADMIN_EMAIL') ?: '',
    'debug'       => filter_var(getenv('LISONS_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    // Partage des fiches de lecture par défaut (coché par défaut pour les
    // nouveaux comptes ET les comptes qui n'ont jamais réglé l'option ;
    // chaque lecteur peut ensuite décocher ça dans « Mon compte »)
    'partage_par_defaut' => filter_var(getenv('LISONS_PARTAGE_PAR_DEFAUT') ?: true, FILTER_VALIDATE_BOOL),

    'paths' => [
        'root'   => $root,
        // data/ et logs/ contiennent des données personnelles : en production,
        // les déplacer HORS du webroot (LISONS_DATA_DIR, ou ../lisons-data détecté
        // auto) ou bloquer ^/(data|logs|config|app)/ côté serveur web.
        'data'   => $dataDir,
        'logs'   => $logsDir,
        'uploads'=> $root . '/uploads', // servi publiquement (couvertures)
        'views'  => $root . '/resources/views',
    ],

    'data_files' => [
        'livres' => 'livres.json',
        'fiches' => 'fiches.json',
        'users'  => 'utilisateurs.json',
        'resets' => 'reinitialisations.json',
    ],

    // Statuts de lecture possibles (clé => libellé)
    'statuts' => [
        'a_lire'    => 'À lire',
        'en_cours'  => 'En cours',
        'lu'        => 'Lu',
        'abandonne' => 'Abandonné',
    ],

    /* =====================================================================
     * Comptes et rôles (calqué sur Plantons-plus : stockage JSON, sessions
     * PHP locales, 3 niveaux hiérarchiques).
     * ===================================================================== */
    'auth' => [
        'users_file'  => $dataDir . '/utilisateurs.json',
        'password_resets_file' => $dataDir . '/reinitialisations.json',
        // Sessions PHP stockées localement (hébergement mutualisé multi-serveurs :
        // le répertoire par défaut n'est pas partagé entre serveurs).
        'sessions_path' => getenv('LISONS_SESSIONS_PATH') ?: $dataDir . '/sessions',
        'session_key'   => 'lisons_user_id',
        // E-mails promus administrateur / super-administrateur.
        'bootstrap_admin_email' => strtolower((string) (getenv('LISONS_BOOTSTRAP_ADMIN_EMAIL') ?: '')),
        'administrator_emails'  => array_filter(array_map('trim', explode(',', (string) (getenv('LISONS_ADMINISTRATOR_EMAILS') ?: '')))),
        'bootstrap_super_admin_email' => strtolower((string) (getenv('LISONS_BOOTSTRAP_SUPER_ADMIN_EMAIL') ?: '')),
        // true : toute nouvelle inscription doit être approuvée par un administrateur.
        'validation_inscription_obligatoire' => filter_var(getenv('LISONS_VALIDATION_INSCRIPTION_OBLIGATOIRE') ?: false, FILTER_VALIDATE_BOOL),
        // Longueur minimale du mot de passe (comme Plantons-plus : 12).
        'password_min_length' => 12,
        // Rôles, du plus simple au plus élevé (hiérarchie).
        'roles' => ['contributeur', 'administrateur', 'super_administrateur'],
        'role_labels' => [
            'contributeur'        => 'Contributeur',
            'administrateur'      => 'Administrateur',
            'super_administrateur'=> 'Super-administrateur',
        ],
    ],

    // Source de métadonnées ISBN
    'isbn' => [
        'openlibrary_base' => 'https://openlibrary.org',
        'covers_base'      => 'https://covers.openlibrary.org/b/id',
        'timeout'          => 10,
        'user_agent'       => 'Lisons-plus/1.0 (contact: ' . (getenv('LISONS_ADMIN_EMAIL') ?: 'local') . ')',
    ],

    'upload' => [
        'covers_dir'   => 'covers',          // sous-dossier de uploads/
        'max_size'     => 3 * 1024 * 1024,   // 3 Mo
        'allowed_ext'  => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ],

    'pagination' => [
        'par_page' => 24,
    ],
];
