<h1>Importer une bibliothèque</h1>

<?php if (!empty($message)): ?><div class="alertes ok"><p><?= e($message) ?></p></div><?php endif; ?>
<?php if (!empty($erreurs)): ?>
  <div class="alertes" role="alert"><?php foreach ($erreurs as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="import-aide">
  <h2>Depuis Calibre</h2>
  <ol>
    <li>Exporte ta bibliothèque Calibre avec le script fourni :
      <pre><code>python3 scripts/calibre_to_json.py "/chemin/vers/Bibliothèque calibre/metadata.db" data/import-calibre.json</code></pre>
    </li>
    <li>Envoie le fichier JSON produit ci-dessous (les doublons d'ISBN sont ignorés).</li>
  </ol>
  <h2>Depuis un CSV</h2>
  <p>Un CSV avec les en-têtes <code>title, authors, isbn, publisher, pubdate, series, tags, rating, comments</code> (export Calibre « CSV » ou tableur) fonctionne aussi.</p>
</div>

<form method="post" action="<?= url('/import') ?>" enctype="multipart/form-data" class="formulaire">
  <?= csrf_field() ?>
  <div class="champ">
    <label for="fichier">Fichier (.json ou .csv)</label>
    <input type="file" id="fichier" name="fichier" accept=".json,.csv" required>
  </div>
  <div class="actions">
    <button class="bouton" type="submit">Importer</button>
  </div>
</form>
