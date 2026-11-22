<?php $user = $user ?? null; $auth = \App\Core\App::get()->auth(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre_page ?? ($config['app_name'] ?? 'Lisons-plus')) ?> — <?= e($config['app_name']) ?></title>
<link rel="stylesheet" href="<?= url('/public/css/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body>
<header class="barre">
  <a class="marque" href="<?= url('/') ?>">📚 <?= e($config['app_name']) ?></a>
  <nav class="menu">
    <?php if ($user): ?>
      <a href="<?= url('/') ?>" class="<?= ($page ?? '') === 'accueil' ? 'actif' : '' ?>">Ma bibliothèque</a>
      <a href="<?= url('/livres/nouveau') ?>" class="<?= ($page ?? '') === 'nouveau' ? 'actif' : '' ?>">+ Ajouter</a>
      <a href="<?= url('/partages') ?>" class="<?= ($page ?? '') === 'partages' ? 'actif' : '' ?>">Fiches partagées</a>
      <a href="<?= url('/stats') ?>" class="<?= ($page ?? '') === 'stats' ? 'actif' : '' ?>">📊 Stats</a>
      <a href="<?= url('/import') ?>" class="<?= ($page ?? '') === 'import' ? 'actif' : '' ?>">Import</a>
      <a href="<?= url('/mon-compte') ?>" class="<?= ($page ?? '') === 'compte' ? 'actif' : '' ?>"> <?= e(user_display($user)) ?></a>
      <?php if ($auth->hasRole($user, 'administrateur')): ?>
        <a href="<?= url('/admin') ?>" class="<?= ($page ?? '') === 'admin' ? 'actif' : '' ?>">⚙ Admin</a>
      <?php endif; ?>
      <form method="post" action="<?= url('/deconnexion') ?>" class="form-deconnexion"><?= csrf_field() ?><button class="lien" type="submit">Déconnexion</button></form>
    <?php else: ?>
      <a href="<?= url('/?q=') ?>">Explorer les fiches partagées</a>
      <a href="<?= url('/connexion') ?>" class="<?= ($page ?? '') === 'connexion' ? 'actif' : '' ?>">Connexion</a>
      <a href="<?= url('/inscription') ?>" class="<?= ($page ?? '') === 'inscription' ? 'actif' : '' ?>">Inscription</a>
    <?php endif; ?>
  </nav>
</header>
<main class="contenu">
<?= $content ?>
</main>
<footer class="pied">
  <?= e($config['app_name']) ?> — bibliothèque personnelle &amp; fiches de lecture partagées · données stockées localement (JSON, sans base de données)
</footer>
<script src="<?= url('/public/js/app.js') ?>"></script>
</body>
</html>
