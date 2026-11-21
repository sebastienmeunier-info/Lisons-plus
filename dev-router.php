<?php
/**
 * Routeur pour le serveur de développement : php -S 127.0.0.1:8080 dev-router.php
 * Sert les fichiers statiques existants, sinon passe à index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false; // le serveur built-in sert le fichier tel quel
}
require __DIR__ . '/index.php';
