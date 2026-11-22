<?php $edition = !empty($livre['id']); ?>
<h1><?= e($titre_page) ?></h1>

<?php if (!empty($erreurs)): ?>
  <div class="alertes" role="alert">
    <?php foreach ($erreurs as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$edition): ?>
<section class="isbn-box">
  <label for="isbn">Ajouter par ISBN (recherche automatique Open Library)</label>
  <div class="isbn-ligne">
    <input type="text" id="isbn" name="isbn" value="<?= e($isbn_saisie) ?>" placeholder="978… ou 2-07-036002-4" inputmode="numeric" autocomplete="off">
    <button class="bouton" type="button" id="btn-isbn">Rechercher</button>
    <button class="bouton secondaire" type="button" id="btn-scan">📷 Scanner</button>
  </div>
  <p class="aide" id="isbn-statut">Saisis l'ISBN, ou scanne le code-barres au dos du livre (caméra du téléphone/ordinateur, fonctionne en HTTPS ou sur localhost).</p>
  <div id="isbn-apercu" class="isbn-apercu" hidden>
    <img id="isbn-couverture" src="" alt="Couverture trouvée">
    <div><strong id="isbn-titre"></strong><p id="isbn-auteurs"></p></div>
  </div>
</section>
<div id="scan-modal" class="scan-modal" hidden>
  <div class="scan-box">
    <p class="scan-statut" id="scan-statut">Initialisation de la caméra…</p>
    <video id="scan-video" playsinline muted></video>
    <div class="scan-viseur"></div>
    <p class="aide">Place le code-barres (EAN-13) du livre dans le viseur.</p>
    <button class="bouton secondaire" type="button" id="btn-scan-fermer">Fermer</button>
  </div>
</div>
<script src="<?= asset('/public/js/vendor/zxing.min.js') ?>"></script>
<hr>
<?php endif; ?>

<?php
// L'ISBN saisi est aussi passé dans l'URL du formulaire : même si le champ
// est perdu à la soumission, la création peut télécharger la couverture.
$actionForm = $action;
if (empty($edition) && (string) ($isbn_saisie ?? '') !== '') {
    $actionForm = url('/livres?isbn=' . rawurlencode((string) $isbn_saisie));
}
?>
<form method="post" action="<?= e($actionForm) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?php // Champ ISBN toujours présent dans le formulaire (création aussi) : la
      // saisie manuelle est alors soumise même si le champ visible (hors form)
      // ou l'URL ?isbn= est perdu ; le JS le resynchronise aussi à la soumission. ?>
  <input type="hidden" name="isbn" value="<?= e($edition ? (string) ($livre['isbn'] ?? '') : (string) ($isbn_saisie ?? '')) ?>">

  <div class="champ">
    <label for="titre">Titre *</label>
    <input type="text" id="titre" name="titre" required value="<?= e($livre['titre'] ?? '') ?>">
  </div>

  <div class="champ">
    <label for="auteurs">Auteur(s) * — séparés par des virgules</label>
    <input type="text" id="auteurs" name="auteurs" required value="<?= e(is_array($livre['auteurs'] ?? null) ? implode(', ', $livre['auteurs']) : ($livre['auteurs'] ?? '')) ?>">
  </div>

  <div class="champ-ligne">
    <div class="champ">
      <label for="editeur">Éditeur</label>
      <input type="text" id="editeur" name="editeur" value="<?= e($livre['editeur'] ?? '') ?>">
    </div>
    <div class="champ">
      <label for="annee">Année</label>
      <input type="number" id="annee" name="annee" min="1000" max="2100" value="<?= e($livre['annee'] ?? '') ?>">
    </div>
    <div class="champ">
      <label for="pages">Pages</label>
      <input type="number" id="pages" name="pages" min="1" value="<?= e($livre['pages'] ?? '') ?>">
    </div>
  </div>

  <div class="champ-ligne">
    <div class="champ">
      <label for="format">Format</label>
      <input type="text" id="format" name="format" value="<?= e($livre['format'] ?? '') ?>" placeholder="Poche, broché…">
    </div>
    <div class="champ">
      <label for="langue">Langue</label>
      <input type="text" id="langue" name="langue" value="<?= e($livre['langue'] ?? '') ?>" placeholder="fre, eng…">
    </div>
    <div class="champ">
      <label for="serie">Série</label>
      <input type="text" id="serie" name="serie" value="<?= e($livre['serie'] ?? '') ?>">
    </div>
  </div>

  <div class="champ">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3"><?= e($livre['description'] ?? '') ?></textarea>
  </div>

  <div class="champ">
    <label for="sujets">Sujets — séparés par des virgules</label>
    <input type="text" id="sujets" name="sujets" value="<?= e(is_array($livre['sujets'] ?? null) ? implode(', ', $livre['sujets']) : ($livre['sujets'] ?? '')) ?>">
  </div>

  <h2>Ma lecture</h2>

  <div class="champ-ligne">
    <div class="champ">
      <label for="statut">Statut</label>
      <select id="statut" name="statut">
        <?php foreach ($statuts as $cle => $libelle): ?>
          <option value="<?= e($cle) ?>" <?= ($livre['statut'] ?? 'a_lire') === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="note">Note (0–5)</label>
      <select id="note" name="note">
        <?php for ($i = 0; $i <= 5; $i++): ?>
          <option value="<?= $i ?>" <?= (int) ($livre['note'] ?? 0) === $i ? 'selected' : '' ?>><?= $i === 0 ? '—' : str_repeat('★', $i) ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="champ">
      <label for="date_lecture">Date de lecture</label>
      <input type="date" id="date_lecture" name="date_lecture" value="<?= e($livre['date_lecture'] ?? '') ?>">
    </div>
  </div>

  <div class="champ">
    <label for="tags">Étiquettes personnelles — séparées par des virgules</label>
    <input type="text" id="tags" name="tags" value="<?= e(is_array($livre['tags'] ?? null) ? implode(', ', $livre['tags']) : ($livre['tags'] ?? '')) ?>" placeholder="roman, à offrir, cours…">
  </div>

  <div class="champ">
    <label for="commentaire">Commentaire personnel</label>
    <textarea id="commentaire" name="commentaire" rows="4" placeholder="Mon avis, pourquoi je l'ai aimé/détesté, contexte de lecture…"><?= e($livre['commentaire'] ?? '') ?></textarea>
  </div>

  <div class="actions">
    <button class="bouton" type="submit"><?= $edition ? 'Enregistrer' : 'Ajouter à ma bibliothèque' ?></button>
    <a class="bouton secondaire" href="<?= $edition ? url('/livre/' . $livre['id']) : url('/') ?>">Annuler</a>
  </div>
</form>
