<?php
/**
 * Lisons-plus — front controller.
 * Toute requête passe par ce fichier (voir docs/install.md pour la réécriture Apache/nginx).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

// Autoloader PSR-4 minimal pour le namespace App\
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/app/Core/helpers.php';

use App\Core\App;
use App\Core\Request;
use App\Controller\HomeController;
use App\Controller\LivreController;
use App\Controller\FicheController;
use App\Controller\ApiController;
use App\Controller\ImportController;

$config = require __DIR__ . '/config/config.php';
$config['debug'] = (bool) (getenv('LISONS_DEBUG') ?: false);

$app = App::boot($config);
$router = $app->router();

$home = new HomeController();
$livre = new LivreController();
$fiche = new FicheController();
$api = new ApiController();
$import = new ImportController();

// Pages
$router->get('/', $home->index(...), 'accueil');
$router->get('/livres/nouveau', $livre->nouveau(...), 'livre_nouveau');
$router->post('/livres', $livre->creer(...), 'livre_creer');
$router->get('/livre/{id}', $livre->voir(...), 'livre_voir');
$router->get('/livre/{id}/edit', $livre->editer(...), 'livre_editer');
$router->post('/livre/{id}/maj', $livre->maj(...), 'livre_maj');
$router->post('/livre/{id}/supprimer', $livre->supprimer(...), 'livre_supprimer');

// Fiches de lecture
$router->get('/partages', $fiche->partages(...), 'partages');
$router->get('/livre/{id}/fiche/nouvelle', $fiche->nouvelle(...), 'fiche_nouvelle');
$router->post('/livre/{id}/fiche', $fiche->creer(...), 'fiche_creer');
$router->get('/fiche/{fiche}/edit', $fiche->editer(...), 'fiche_editer');
$router->post('/fiche/{fiche}/maj', $fiche->maj(...), 'fiche_maj');
$router->post('/fiche/{fiche}/supprimer', $fiche->supprimer(...), 'fiche_supprimer');
$router->post('/fiche/{fiche}/publication', $fiche->basculerPublication(...), 'fiche_publication');
$router->get('/partage/{token}', $fiche->publique(...), 'fiche_publique');

// API interne
$router->get('/api/isbn/{isbn}', $api->isbn(...), 'api_isbn');
$router->get('/api/livres', $api->recherche(...), 'api_livres');

// Import
$router->get('/import', $import->form(...), 'import_form');
$router->post('/import', $import->traiter(...), 'import_traiter');

// Ressources statiques servies telles quelles si on passe par le serveur built-in
$request = new Request();
$app->dispatch($request)->send();
