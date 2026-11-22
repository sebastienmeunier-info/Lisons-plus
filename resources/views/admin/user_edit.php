<h1>✏️ Modifier le profil de <?= e(user_display($target)) ?></h1>

<?php if (!empty($erreurs)): ?>
  <div class="alertes" role="alert"><?php foreach ($erreurs as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<section class="formulaire carte-auth">
  <form method="post" action="<?= url('/admin/utilisateurs/' . $target['id'] . '/maj') ?>">
    <?= csrf_field() ?>
    <div class="champ-ligne">
      <div class="champ"><label for="prenom">Prénom</label><input type="text" id="prenom" name="prenom" required maxlength="80" value="<?= e($target['prenom'] ?? '') ?>"></div>
      <div class="champ"><label for="nom">Nom</label><input type="text" id="nom" name="nom" required maxlength="80" value="<?= e($target['nom'] ?? '') ?>"></div>
    </div>
    <div class="champ"><label for="email">Adresse e-mail</label><input type="email" id="email" name="email" required value="<?= e($target['email'] ?? '') ?>"></div>
    <div class="champ">
      <label for="role">Rôle</label>
      <select id="role" name="role" <?= $auth->mayChangeRole($user, $target, $target['role'] ?? '') ? '' : 'disabled' ?>>
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r) ?>" <?= ($target['role'] ?? '') === $r ? 'selected' : '' ?>><?= e($role_labels[$r] ?? $r) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$auth->mayChangeRole($user, $target, $target['role'] ?? '')): ?>
        <p class="aide">Rôle non modifiable (hiérarchie : un super_administrateur est intangible).</p>
      <?php endif; ?>
    </div>
    <div class="champ case">
      <label for="partage_par_defaut"><input type="checkbox" id="partage_par_defaut" name="partage_par_defaut" value="1" <?= !empty($target['partage_par_defaut']) ? 'checked' : '' ?>> Partager ses fiches par défaut</label>
    </div>
    <div class="champ"><p>Inscrit le <?= e(date_fr(substr($target['created_at'] ?? '', 0, 10))) ?> · Statut : <strong><?= e(($target['registration_status'] ?? 'approved') === 'pending' ? 'en attente de validation' : 'actif') ?></strong></p></div>
    <div class="actions">
      <button class="bouton" type="submit">Enregistrer</button>
      <a class="bouton secondaire" href="<?= url('/admin/utilisateurs') ?>">Annuler</a>
    </div>
  </form>
</section>
