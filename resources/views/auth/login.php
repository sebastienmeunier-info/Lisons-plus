<div class="carte-auth">
  <h1>Connexion</h1>
  <?php if (!empty($message)): ?><div class="alertes" role="alert"><p><?= e($message) ?></p></div><?php endif; ?>
  <form method="post" action="<?= url('/connexion') ?>" class="formulaire">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
    <div class="champ"><label for="email">Adresse e-mail</label><input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div class="champ"><label for="password">Mot de passe</label><input type="password" id="password" name="password" required></div>
    <div class="actions">
      <button class="bouton" type="submit">Se connecter</button>
      <a class="bouton secondaire" href="<?= url('/mot-de-passe-oublie') ?>">Mot de passe oublié</a>
      <a class="bouton secondaire" href="<?= url('/inscription') ?>">Inscription</a>
    </div>
  </form>
</div>
