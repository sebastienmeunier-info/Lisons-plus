<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titre_page ?? 'Fiche de lecture') ?> — <?= e($config['app_name']) ?></title>
<link rel="stylesheet" href="<?= url('/public/css/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body class="public">
<header class="barre publique">
  <span class="marque">📚 <?= e($config['app_name']) ?></span>
  <span class="badge-partage">fiche partagée</span>
</header>
<main class="contenu large">
<?= $content ?>
</main>
<footer class="pied">
  Fiche de lecture partagée via <?= e($config['app_name']) ?>
</footer>
</body>
</html>
