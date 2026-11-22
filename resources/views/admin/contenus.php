<h1>⚙ Fiches de lecture (modération)</h1>
<p class="aide">Toutes les fiches, de tous les utilisateurs. Une fiche publique est visible par tout visiteur via son lien et via les pages de partage par champ (/?isbn=…, ?auteur=…).</p>

<?php if (!$fiches): ?>
  <p class="vide-petit">Aucune fiche pour l'instant.</p>
<?php else: ?>
<table class="table-users">
  <thead><tr><th>Fiche</th><th>Livre</th><th>Auteur</th><th>Visibilité</th><th>Modifiée le</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($fiches as $f): ?>
    <?php $livre = $livres[$f['livre_id'] ?? ''] ?? null; $auteur = $users[$f['user_id'] ?? ''] ?? null; ?>
    <tr>
      <td>
        <?php if (!empty($f['publique']) && !empty($f['token'])): ?>
          <a href="<?= url_partage($f['token']) ?>" target="_blank"><strong><?= e($f['titre']) ?></strong></a>
        <?php else: ?>
          <strong><?= e($f['titre']) ?></strong>
        <?php endif; ?>
      </td>
      <td><?= $livre ? e($livre['titre']) : '—' ?></td>
      <td><?= e(user_display($auteur)) ?></td>
      <td><?= !empty($f['publique']) ? '<span class="badge public">🌐 publique</span>' : '<span class="badge prive">🔒 privée</span>' ?></td>
      <td><?= e(date_fr(substr($f['date_modif'] ?? $f['date_creation'] ?? '', 0, 10))) ?></td>
      <td>
        <?php if (!empty($f['publique'])): ?>
          <form method="post" action="<?= url('/admin/contenus/desactiver-fiche') ?>" class="form-inline" onsubmit="return confirm('Rendre cette fiche privée ?');">
            <?= csrf_field() ?>
            <input type="hidden" name="fiche_id" value="<?= e($f['id']) ?>">
            <button class="bouton danger petit" type="submit">Retirer du partage</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<p><a class="bouton secondaire" href="<?= url('/admin') ?>">Retour à l'administration</a></p>
