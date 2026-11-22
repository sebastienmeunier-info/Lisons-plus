<h1>⚙ Administration</h1>
<p>Connecté en tant que <strong><?= e(user_display($user)) ?></strong> (<?= e($role_labels[$user['role'] ?? ''] ?? '') ?>).</p>

<?php if (!empty($dataDansWebroot)): ?>
  <div class="alertes" role="alert">
    <p>⚠️ <strong>Fuite de données :</strong> le dossier <code>data/</code> est encore dans le webroot
    (<code><?= e($dataDir) ?></code>) et ses fichiers JSON sont accessibles en HTTP
    (testez <code>/lisons/data/livres.json</code>). En production, l'application tente de déplacer
    automatiquement <code>data/</code> et <code>logs/</code> vers <code>../../lisons-data</code> et
    <code>../../lisons-logs</code> (hors webroot) — si ce déplacement est impossible (droits insuffisants),
    déplace-les à la main ou bloquez <code>^/lisons/(data|logs|config|app)/</code> côté nginx/Apache.</p>
  </div>
<?php endif; ?>

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
