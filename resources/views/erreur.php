<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Erreur <?= (int) $code ?> — <?= e($config['app_name']) ?></title>
<link rel="stylesheet" href="<?= asset('/public/css/style.css') ?>">
</head>
<body class="public">
<main class="contenu large erreur">
  <h1><?= (int) $code ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="bouton" href="<?= url('/') ?>">Retour à la bibliothèque</a></p>
</main>
</body>
</html>
