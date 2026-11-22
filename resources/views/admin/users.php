<h1>⚙ Utilisateurs</h1>
<?php if (!empty($message)): ?><div class="alertes ok"><p><?= e($message) ?></p></div><?php endif; ?>

<table class="table-users">
  <thead>
    <tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Inscrit le</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e(user_display($u)) ?></td>
      <td><?= e($u['email'] ?? '') ?></td>
      <td>
        <form method="post" action="<?= url('/admin/utilisateurs/role') ?>" class="form-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
          <select name="role" <?= $auth->mayChangeRole($user, $u, $u['role'] ?? '') ? '' : 'disabled' ?>>
            <?php foreach ($roles as $r): ?>
              <option value="<?= e($r) ?>" <?= ($u['role'] ?? '') === $r ? 'selected' : '' ?>><?= e($role_labels[$r]) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="bouton petit" type="submit">OK</button>
        </form>
      </td>
      <td>
        <?php if (($u['registration_status'] ?? 'approved') === 'pending'): ?>
          <span class="badge statut-en_cours">en attente</span>
          <form method="post" action="<?= url('/admin/utilisateurs/approuver') ?>" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
            <button class="bouton petit" type="submit">Valider</button>
          </form>
        <?php else: ?>
          <span class="badge statut-lu">actif</span>
        <?php endif; ?>
      </td>
      <td><?= e(date_fr(substr($u['created_at'] ?? '', 0, 10))) ?></td>
      <td>
        <?php if ($auth->mayEditUser($user, $u)): ?>
          <a class="bouton secondaire petit" href="<?= url('/admin/utilisateurs/' . $u['id'] . '/edit') ?>">✏️ Modifier</a>
        <?php endif; ?>
        <?php if ($auth->mayDeleteUser($user, $u) && $u['id'] !== $user['id']): ?>
          <form method="post" action="<?= url('/admin/utilisateurs/supprimer') ?>" class="form-inline" onsubmit="return confirm('Supprimer ce compte ?');">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
            <button class="bouton danger petit" type="submit">Supprimer</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p><a class="bouton secondaire" href="<?= url('/admin') ?>">Retour à l'administration</a></p>
