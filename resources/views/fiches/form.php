<h1><?= e($titre_page) ?></h1>

<?php if (!empty($livre)): ?>
  <p class="fiche-livre">Pour <strong><?= e($livre['titre']) ?></strong> — <?= e(implode(', ', $livre['auteurs'] ?? [])) ?></p>
<?php endif; ?>

<?php if (!empty($erreurs)): ?>
  <div class="alertes" role="alert">
    <?php foreach ($erreurs as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="formulaire">
  <?= csrf_field() ?>
  <div class="champ">
    <label for="titre">Titre de la fiche *</label>
    <input type="text" id="titre" name="titre" required value="<?= e($fiche['titre'] ?? '') ?>" placeholder="ex : Pourquoi ce livre m'a marqué">
  </div>

  <div class="champ">
    <label for="contenu">Contenu *</label>
    <textarea id="contenu" name="contenu" rows="14" required placeholder="Résumé, analyse, ressenti… Mise en forme : **gras**, *italique*, # titre, &gt; citation, - liste, [texte](https://lien)"><?= e($fiche['contenu'] ?? '') ?></textarea>
    <p class="aide">Astuce : <code>**gras**</code>, <code>*italique*</code>, <code># titre</code>, <code>&gt; citation</code>, <code>- élément de liste</code>, <code>[texte](https://…)</code></p>
  </div>

  <div class="champ">
    <label for="citations">Citations du livre — une par ligne</label>
    <textarea id="citations" name="citations" rows="4"><?= e(implode("\n", $fiche['citations'] ?? [])) ?></textarea>
  </div>

  <div class="champ-ligne">
    <div class="champ">
      <label for="note">Note (0–5)</label>
      <select id="note" name="note">
        <?php for ($i = 0; $i <= 5; $i++): ?>
          <option value="<?= $i ?>" <?= (int) ($fiche['note'] ?? 0) === $i ? 'selected' : '' ?>><?= $i === 0 ? '—' : str_repeat('★', $i) ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="champ case">
      <label for="publique"><input type="checkbox" id="publique" name="publique" value="1" <?= !empty($fiche['publique']) ? 'checked' : '' ?>> Publier cette fiche (lien partageable)</label>
      <?php if (empty($fiche['id'])): ?><p class="aide">Pré-cochée selon votre réglage « Mon compte → Partager mes fiches par défaut ».</p><?php endif; ?>
    </div>
  </div>

  <div class="actions">
    <button class="bouton" type="submit">Enregistrer la fiche</button>
    <a class="bouton secondaire" href="<?= !empty($fiche['livre_id']) ? url('/livre/' . $fiche['livre_id']) : url('/partages') ?>">Annuler</a>
  </div>
</form>
