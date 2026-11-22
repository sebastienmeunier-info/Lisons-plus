<h1>Mon compte</h1>
<?php if (!empty($message)): ?><div class="alertes ok"><p><?= e($message) ?></p></div><?php endif; ?>
<?php if (!empty($erreurs)): ?><div class="alertes" role="alert"><?php foreach ($erreurs as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div><?php endif; ?>

<div class="compte-grid">
  <section class="formulaire">
    <h2>Profil</h2>
    <p>Rôle : <strong><?= e($role_labels[$user['role'] ?? ''] ?? $user['role'] ?? '') ?></strong></p>
    <form method="post" action="<?= url('/mon-compte') ?>">
      <?= csrf_field() ?>
      <div class="champ-ligne">
        <div class="champ"><label for="prenom">Prénom</label><input type="text" id="prenom" name="prenom" required maxlength="80" value="<?= e($user['prenom'] ?? '') ?>"></div>
        <div class="champ"><label for="nom">Nom</label><input type="text" id="nom" name="nom" required maxlength="80" value="<?= e($user['nom'] ?? '') ?>"></div>
      </div>
      <div class="champ"><label for="email">Adresse e-mail</label><input type="email" id="email" name="email" required value="<?= e($user['email'] ?? '') ?>"></div>
      <div class="actions"><button class="bouton" type="submit">Enregistrer</button></div>
    </form>
  </section>

  <section class="formulaire">
    <h2>Changer de mot de passe</h2>
    <form method="post" action="<?= url('/mon-compte/mot-de-passe') ?>">
      <?= csrf_field() ?>
      <div class="champ"><label for="password_old">Ancien mot de passe</label><input type="password" id="password_old" name="password_old" required></div>
      <div class="champ"><label for="password_new">Nouveau mot de passe (12 caractères min.)</label><input type="password" id="password_new" name="password_new" required minlength="12"></div>
      <div class="champ"><label for="password_confirm">Confirmer</label><input type="password" id="password_confirm" name="password_confirm" required minlength="12"></div>
      <div class="actions"><button class="bouton" type="submit">Mettre à jour</button></div>
    </form>
  </section>
</div>
