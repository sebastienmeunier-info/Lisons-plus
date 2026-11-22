<div class="carte-auth">
  <h1>Nouveau mot de passe</h1>
  <?php if (!empty($message)): ?><div class="alertes" role="alert"><p><?= e($message) ?></p></div><?php endif; ?>
  <form method="post" action="<?= url('/reinitialiser-mot-de-passe') ?>" class="formulaire">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <div class="champ"><label for="password">Nouveau mot de passe (12 caractères min.)</label><input type="password" id="password" name="password" required minlength="12"></div>
    <div class="champ"><label for="password_confirm">Confirmer</label><input type="password" id="password_confirm" name="password_confirm" required minlength="12"></div>
    <div class="actions"><button class="bouton" type="submit">Enregistrer</button></div>
  </form>
</div>
