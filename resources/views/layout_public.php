<?php $auth = \App\Core\App::get()->auth(); $user = $auth->currentUser(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titre_page ?? 'Fiches de lecture partagées') ?> — <?= e($config['app_name']) ?></title>
<link rel="stylesheet" href="<?= asset('/public/css/style.css') ?>">
<link rel="icon" href="<?= asset($config['logo_url'] ?? '/public/img/logo.svg') ?>" type="image/svg+xml">
</head>
<body class="public">
<header class="site-header">
  <a class="project-brand" href="<?= url('/partage') ?>" aria-label="<?= e($config['app_name']) ?>">
    <img class="project-logo" src="<?= asset($config['logo_url'] ?? '/public/img/logo.svg') ?>" alt="">
    <strong class="project-title"><?= e($config['header_text'] ?? 'Lisons') ?> <span class="project-title-plus">+</span></strong>
  </a>
  <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-controls="site-navigation" aria-expanded="false"><span></span><span></span><span></span></button>
  <nav id="site-navigation" class="site-navigation" aria-label="Navigation principale">
    <span class="badge-partage">fiches partagées</span>
    <?php if ($user): ?>
      <a class="menu-button" href="<?= url('/') ?>">Ma bibliothèque</a>
    <?php else: ?>
      <a class="menu-button" href="<?= url('/connexion') ?>">Connexion</a>
      <a class="menu-button" href="<?= url('/inscription') ?>">Inscription</a>
    <?php endif; ?>
  </nav>
</header>
<main class="page-layout">
<?= $content ?>
</main>
<footer class="site-footer">
  <span>Fiches de lecture partagées publiquement via <?= e($config['app_name']) ?> — <a href="<?= url('/partage') ?>">voir toutes les fiches</a></span>
</footer>
<script>document.addEventListener('DOMContentLoaded',()=>{const toggle=document.querySelector('.menu-toggle'),navigation=document.querySelector('#site-navigation');if(!toggle||!navigation)return;const close=()=>{navigation.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Ouvrir le menu');};toggle.addEventListener('click',()=>{const open=!navigation.classList.contains('is-open');navigation.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Fermer le menu':'Ouvrir le menu');});navigation.addEventListener('click',event=>{if(event.target.closest('a'))close();});document.addEventListener('keydown',event=>{if(event.key==='Escape')close();});});</script>
<script>window.LISONS_BASE = <?= json_encode(url('/')) ?>;window.LISONS_URL_MODE = <?= json_encode(App\Core\App::get()->config()['url_mode'] ?? 'path') ?>;window.lisonsRoute = function (p) { p = String(p || '').replace(/^\/+/, ''); var b = String(window.LISONS_BASE || '/').replace(/\/+$/, ''); return window.LISONS_URL_MODE === 'qs' ? b + '/?/' + p : b + '/' + p; };</script>
<script src="<?= asset('/public/js/app.js') ?>"></script>
</body>
</html>
