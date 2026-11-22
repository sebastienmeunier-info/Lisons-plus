<?php
/**
 * Configuration centrale de Lisons-plus.
 * Les valeurs sensibles peuvent être surchargées par des variables
 * d'environnement (voir docs/install.md).
 */

$root = dirname(__DIR__);

return [
    'app_name'    => getenv('LISONS_APP_NAME') ?: 'Lisons-plus',
    'base_url'    => rtrim(getenv('LISONS_BASE_URL') ?: '', '/'), // ex: https://livres.example.tld ; vide = détection auto
    'admin_email' => getenv('LISONS_ADMIN_EMAIL') ?: '',
    'debug'       => filter_var(getenv('LISONS_DEBUG') ?: false, FILTER_VALIDATE_BOOL),

    'paths' => [
        'root'   => $root,
        'data'   => $root . '/data',
        'logs'   => $root . '/logs',
        'uploads'=> $root . '/uploads',
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
        'users_file'  => $root . '/data/utilisateurs.json',
        'password_resets_file' => $root . '/data/reinitialisations.json',
        // Sessions PHP stockées localement (hébergement mutualisé multi-serveurs :
        // le répertoire par défaut n'est pas partagé entre serveurs).
        'sessions_path' => getenv('LISONS_SESSIONS_PATH') ?: $root . '/data/sessions',
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
        'timeout'          => 20,
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
