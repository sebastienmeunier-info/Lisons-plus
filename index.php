<?php
/**
 * Lisons-plus — front controller (à la racine, comme Plantons-plus).
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
use App\Controller\AuthController;
use App\Controller\AccountController;
use App\Controller\AdminController;
use App\Controller\StatsController;

$config = require __DIR__ . '/config/config.php';

$app = App::boot($config);
$router = $app->router();

// Force la migration immédiate des magasins de données vers les fichiers
// gardés .php (si data/ est encore dans le webroot, les anciens *.json
// lisibles sont convertis puis supprimés au premier appel).
foreach (array_keys($config['data_files']) as $storeName) {
    $app->store($storeName);
}
// Idem pour l'ancien log non protégé (logs/app.log → app.log.php gardé).
$logsDir = (string) ($config['paths']['logs'] ?? '');
if ($logsDir !== '' && is_file($logsDir . '/app.log') && !is_file($logsDir . '/app.log.php')) {
    @file_put_contents($logsDir . '/app.log.php', "<?php exit; ?>\n" . (string) file_get_contents($logsDir . '/app.log'), LOCK_EX);
    @unlink($logsDir . '/app.log');
}

$home = new HomeController();
$livre = new LivreController();
$fiche = new FicheController();
$api = new ApiController();
$import = new ImportController();
$auth = new AuthController();
$account = new AccountController();
$admin = new AdminController();
$stats = new StatsController();

// Options de protection : ['auth' => true] exige une session,
// ['role' => 'administrateur'] exige un rôle suffisant (hiérarchie).
$AUTH = ['auth' => true];
$ADMIN = ['auth' => true, 'role' => 'administrateur'];

// ----- Pages publiques -----
$router->get('/', $home->index(...), 'accueil');
// Liste de TOUTES les fiches partagées (tous utilisateurs, y compris le
// connecté) — c'est la page d'accueil des visiteurs, accessible à tous.
$router->get('/partage', $home->partage(...), 'partage_liste');
$router->get('/partage/{token}', $fiche->publique(...), 'fiche_publique');
$router->get('/api/isbn/{isbn}', $api->isbn(...), 'api_isbn');
$router->get('/api/livres', $api->recherche(...), 'api_livres');

// ----- Authentification -----
$router->get('/inscription', $auth->registerForm(...), 'inscription_form');
$router->post('/inscription', $auth->register(...), 'inscription');
$router->get('/connexion', $auth->loginForm(...), 'connexion_form');
$router->post('/connexion', $auth->login(...), 'connexion');
$router->post('/deconnexion', $auth->logout(...), 'deconnexion', $AUTH);
$router->get('/mot-de-passe-oublie', $auth->forgotForm(...), 'mdp_form');
$router->post('/mot-de-passe-oublie', $auth->forgot(...), 'mdp');
$router->get('/reinitialiser-mot-de-passe', $auth->resetForm(...), 'reset_form');
$router->post('/reinitialiser-mot-de-passe', $auth->reset(...), 'reset');

// ----- Espace personnel (connecté) -----
$router->get('/livres/nouveau', $livre->nouveau(...), 'livre_nouveau', $AUTH);
$router->post('/livres', $livre->creer(...), 'livre_creer', $AUTH);
$router->get('/livre/{id}', $livre->voir(...), 'livre_voir', $AUTH);
$router->get('/livre/{id}/edit', $livre->editer(...), 'livre_editer', $AUTH);
$router->post('/livre/{id}/maj', $livre->maj(...), 'livre_maj', $AUTH);
$router->post('/livre/{id}/supprimer', $livre->supprimer(...), 'livre_supprimer', $AUTH);
$router->post('/livre/{id}/couverture', $livre->rechargerCouverture(...), 'livre_couverture', $AUTH);

$router->get('/partages', $fiche->partages(...), 'partages', $AUTH);
$router->get('/livre/{id}/fiche/nouvelle', $fiche->nouvelle(...), 'fiche_nouvelle', $AUTH);
$router->post('/livre/{id}/fiche', $fiche->creer(...), 'fiche_creer', $AUTH);
$router->get('/fiche/{fiche}/edit', $fiche->editer(...), 'fiche_editer', $AUTH);
$router->post('/fiche/{fiche}/maj', $fiche->maj(...), 'fiche_maj', $AUTH);
$router->post('/fiche/{fiche}/supprimer', $fiche->supprimer(...), 'fiche_supprimer', $AUTH);
$router->post('/fiche/{fiche}/publication', $fiche->basculerPublication(...), 'fiche_publication', $AUTH);

$router->get('/import', $import->form(...), 'import_form', $AUTH);
$router->post('/import', $import->traiter(...), 'import_traiter', $AUTH);

// Statistiques de lecture
$router->get('/stats', $stats->index(...), 'stats', $AUTH);

// ----- Mon compte -----
$router->get('/mon-compte', $account->index(...), 'compte', $AUTH);
$router->post('/mon-compte', $account->update(...), 'compte_maj', $AUTH);
$router->post('/mon-compte/mot-de-passe', $account->updatePassword(...), 'compte_mdp', $AUTH);

// ----- Administration (administrateur et super_administrateur) -----
$router->get('/admin', $admin->index(...), 'admin', $ADMIN);
$router->get('/admin/utilisateurs', $admin->users(...), 'admin_users', $ADMIN);
$router->get('/admin/utilisateurs/{id}/edit', $admin->editUser(...), 'admin_user_edit', $ADMIN);
$router->post('/admin/utilisateurs/{id}/maj', $admin->updateUser(...), 'admin_user_maj', $ADMIN);
$router->post('/admin/utilisateurs/role', $admin->changeRole(...), 'admin_role', $ADMIN);
$router->post('/admin/utilisateurs/approuver', $admin->approve(...), 'admin_approuver', $ADMIN);
$router->post('/admin/utilisateurs/supprimer', $admin->deleteUser(...), 'admin_suppr', $ADMIN);
$router->get('/admin/contenus', $admin->contenus(...), 'admin_contenus', $ADMIN);
$router->post('/admin/contenus/desactiver-fiche', $admin->desactiverFiche(...), 'admin_desactiver', $ADMIN);

$request = new Request();
$app->dispatch($request)->send();
