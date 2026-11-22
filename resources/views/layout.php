<?php $user = $user ?? null; $auth = \App\Core\App::get()->auth(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre_page ?? ($config['app_name'] ?? 'Lisons-plus')) ?> — <?= e($config['app_name']) ?></title>
<link rel="stylesheet" href="<?= url('/public/css/style.css') ?>">
<link rel="icon" href="<?= url($config['logo_url'] ?? '/public/img/logo.svg') ?>" type="image/svg+xml">
</head>
<body>
<header class="site-header">
  <a class="project-brand" href="<?= url('/') ?>" aria-label="<?= e($config['app_name']) ?>">
    <img class="project-logo" src="<?= url($config['logo_url'] ?? '/public/img/logo.svg') ?>" alt="">
    <strong class="project-title"><?= e($config['header_text'] ?? 'Lisons') ?> <span class="project-title-plus">+</span></strong>
  </a>
  <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-controls="site-navigation" aria-expanded="false"><span></span><span></span><span></span></button>
  <nav id="site-navigation" class="site-navigation" aria-label="Navigation principale">
    <?php if ($user): ?>
      <span class="user-menu">Bonjour <?= e($user['prenom'] ?? '') ?></span>
      <a class="menu-button<?= ($page ?? '') === 'accueil' ? ' menu-current' : '' ?>" href="<?= url('/') ?>">Ma bibliothèque</a>
      <a class="menu-button<?= ($page ?? '') === 'nouveau' ? ' menu-current' : '' ?>" href="<?= url('/livres/nouveau') ?>">+ Ajouter</a>
      <a class="menu-button<?= ($page ?? '') === 'partages' ? ' menu-current' : '' ?>" href="<?= url('/partages') ?>">Fiches partagées</a>
      <a class="menu-button<?= ($page ?? '') === 'stats' ? ' menu-current' : '' ?>" href="<?= url('/stats') ?>">📊 Stats</a>
      <a class="menu-button<?= ($page ?? '') === 'import' ? ' menu-current' : '' ?>" href="<?= url('/import') ?>">Import</a>
      <a class="menu-button<?= ($page ?? '') === 'compte' ? ' menu-current' : '' ?>" href="<?= url('/mon-compte') ?>">Mon compte</a>
      <?php if ($auth->hasRole($user, 'administrateur')): ?>
        <a class="menu-button menu-admin<?= ($page ?? '') === 'admin' ? ' menu-current' : '' ?>" href="<?= url('/admin') ?>">⚙ Admin</a>
      <?php endif; ?>
      <form class="logout-form" method="post" action="<?= url('/deconnexion') ?>"><?= csrf_field() ?><button class="menu-button menu-logout" type="submit">Déconnexion</button></form>
    <?php else: ?>
      <a class="menu-button" href="<?= url('/?q=') ?>">Explorer les fiches</a>
      <a class="menu-button<?= ($page ?? '') === 'connexion' ? ' menu-current' : '' ?>" href="<?= url('/connexion') ?>">Connexion</a>
      <a class="menu-button<?= ($page ?? '') === 'inscription' ? ' menu-current' : '' ?>" href="<?= url('/inscription') ?>">Inscription</a>
    <?php endif; ?>
  </nav>
</header>
<main class="page-layout">
<?= $content ?>
</main>
<footer class="site-footer">
  <span><?= e($config['app_name']) ?> — bibliothèque personnelle &amp; fiches de lecture partagées · stockage JSON local, sans base de données</span>
</footer>
<script>document.addEventListener('DOMContentLoaded',()=>{const toggle=document.querySelector('.menu-toggle'),navigation=document.querySelector('#site-navigation');if(!toggle||!navigation)return;const close=()=>{navigation.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Ouvrir le menu');};toggle.addEventListener('click',()=>{const open=!navigation.classList.contains('is-open');navigation.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Fermer le menu':'Ouvrir le menu');});navigation.addEventListener('click',event=>{if(event.target.closest('a'))close();});document.addEventListener('keydown',event=>{if(event.key==='Escape')close();});});</script>
<script>window.LISONS_BASE = <?= json_encode(url('/')) ?>;</script>
<script src="<?= url('/public/js/app.js') ?>"></script>
</body>
</html>
