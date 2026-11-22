<div class="carte-auth">
  <h1>Mot de passe oublié</h1>
  <?php if (!empty($message)): ?><div class="alertes ok"><p><?= e($message) ?></p></div><?php endif; ?>
  <form method="post" action="<?= url('/mot-de-passe-oublie') ?>" class="formulaire">
    <?= csrf_field() ?>
    <div class="champ"><label for="email">Adresse e-mail du compte</label><input type="email" id="email" name="email" required></div>
    <div class="actions">
      <button class="bouton" type="submit">Générer un lien de réinitialisation</button>
      <a class="bouton secondaire" href="<?= url('/connexion') ?>">Retour à la connexion</a>
    </div>
  </form>
</div>
