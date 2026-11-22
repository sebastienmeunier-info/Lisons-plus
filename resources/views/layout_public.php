<?php $auth = \App\Core\App::get()->auth(); $user = $auth->currentUser(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titre_page ?? 'Fiches de lecture partagées') ?> — <?= e($config['app_name']) ?></title>
<link rel="stylesheet" href="<?= url('/public/css/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body class="public">
<header class="barre publique">
  <a class="marque" href="<?= url('/') ?>">📚 <?= e($config['app_name']) ?></a>
  <nav class="menu">
    <span class="badge-partage">fiches partagées</span>
    <?php if ($user): ?>
      <a href="<?= url('/') ?>">Ma bibliothèque</a>
    <?php else: ?>
      <a href="<?= url('/connexion') ?>">Connexion</a>
      <a href="<?= url('/inscription') ?>">Inscription</a>
    <?php endif; ?>
  </nav>
</header>
<main class="contenu large">
<?= $content ?>
</main>
<footer class="pied">
  Fiches de lecture partagées publiquement via <?= e($config['app_name']) ?> — <a href="<?= url('/') ?>">voir toutes les fiches</a>
</footer>
<script>window.LISONS_BASE = <?= json_encode(url('/')) ?>;</script>
<script src="<?= url('/public/js/app.js') ?>"></script>
</body>
</html>
