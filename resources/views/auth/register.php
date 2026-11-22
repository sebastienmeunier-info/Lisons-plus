<div class="carte-auth">
  <h1>Inscription</h1>
  <?php if (!empty($message)): ?><div class="alertes ok"><p><?= e($message) ?></p></div><?php endif; ?>
  <form method="post" action="<?= url('/inscription') ?>" class="formulaire">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
    <div class="champ-ligne">
      <div class="champ"><label for="prenom">Prénom *</label><input type="text" id="prenom" name="prenom" required maxlength="80" value="<?= e($_POST['prenom'] ?? '') ?>"></div>
      <div class="champ"><label for="nom">Nom *</label><input type="text" id="nom" name="nom" required maxlength="80" value="<?= e($_POST['nom'] ?? '') ?>"></div>
    </div>
    <div class="champ"><label for="email">Adresse e-mail *</label><input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div class="champ"><label for="password">Mot de passe * (12 caractères min.)</label><input type="password" id="password" name="password" required minlength="12"></div>
    <div class="champ"><label for="password_confirm">Confirmer le mot de passe *</label><input type="password" id="password_confirm" name="password_confirm" required minlength="12"></div>
    <p class="aide">Le premier compte créé sur cette instance devient super-administrateur. Les données restent stockées localement (fichiers JSON).</p>
    <div class="actions">
      <button class="bouton" type="submit">Créer mon compte</button>
      <a class="bouton secondaire" href="<?= url('/connexion') ?>">J'ai déjà un compte</a>
    </div>
  </form>
</div>
