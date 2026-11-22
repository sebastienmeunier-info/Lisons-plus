<h1>⚙ Administration</h1>
<p>Connecté en tant que <strong><?= e(user_display($user)) ?></strong> (<?= e($role_labels[$user['role'] ?? ''] ?? '') ?>).</p>

<div class="stats admin-stats">
  <span class="stat"><strong><?= $stats['users'] ?></strong> compte(s)<?php if ($stats['users_pending']): ?> dont <strong><?= $stats['users_pending'] ?></strong> en attente<?php endif; ?></span>
  <span class="stat"><strong><?= $stats['livres'] ?></strong> livre(s)</span>
  <span class="stat"><strong><?= $stats['fiches'] ?></strong> fiche(s)</span>
  <span class="stat"><strong><?= $stats['fiches_publiques'] ?></strong> fiche(s) publique(s)</span>
</div>

<nav class="admin-nav">
  <a class="bouton" href="<?= url('/admin/utilisateurs') ?>">Gérer les utilisateurs</a>
  <a class="bouton secondaire" href="<?= url('/admin/contenus') ?>">Modérer les fiches publiques</a>
  <a class="bouton secondaire" href="<?= url('/') ?>">Retour à ma bibliothèque</a>
</nav>

<h2>Rôles (hiérarchie)</h2>
<ol class="roles-liste">
  <?php foreach (array_reverse($role_labels, true) as $role => $libelle): ?>
    <li><strong><?= e($libelle) ?></strong> <code><?= e($role) ?></code></li>
  <?php endforeach; ?>
</ol>
<p class="aide">Un administrateur peut promouvoir/rétrograder des contributeurs. Seul un super-administrateur peut modifier un administrateur (y compris le promouvoir super-administrateur). Le rôle super-administrateur est intangible et non supprimable.</p>
