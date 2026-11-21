<?php
/**
 * Configuration centrale de Lisons-plus.
 * Les valeurs sensibles peuvent être surchargées par des variables
 * d'environnement (voir docs/install.md).
 */

return [
    'app_name'    => getenv('LISONS_APP_NAME') ?: 'Lisons-plus',
    'base_url'    => rtrim(getenv('LISONS_BASE_URL') ?: '', '/'), // ex: https://livres.example.tld ; vide = détection auto
    'admin_email' => getenv('LISONS_ADMIN_EMAIL') ?: '',

    'paths' => [
        'root'   => dirname(__DIR__),
        'data'   => dirname(__DIR__) . '/data',
        'logs'   => dirname(__DIR__) . '/logs',
        'uploads'=> dirname(__DIR__) . '/uploads',
        'views'  => dirname(__DIR__) . '/resources/views',
    ],

    'data_files' => [
        'livres' => 'livres.json',
        'fiches' => 'fiches.json',
    ],

    // Statuts de lecture possibles (clé => libellé)
    'statuts' => [
        'a_lire'    => 'À lire',
        'en_cours'  => 'En cours',
        'lu'        => 'Lu',
        'abandonne' => 'Abandonné',
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
