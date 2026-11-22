<section class="entete">
  <h1>Ma bibliothèque</h1>
  <div class="stats">
    <span class="stat"><strong><?= $stats['total'] ?></strong> livre<?= $stats['total'] > 1 ? 's' : '' ?></span>
    <span class="stat lu"><strong><?= $stats['lus'] ?></strong> lu<?= $stats['lus'] > 1 ? 's' : '' ?></span>
    <span class="stat encours"><strong><?= $stats['en_cours'] ?></strong> en cours</span>
    <span class="stat alire"><strong><?= $stats['a_lire'] ?></strong> à lire</span>
  </div>
</section>

<form class="filtres" method="get" action="<?= url('/') ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher (titre, auteur, tag, ISBN…)" aria-label="Recherche">
  <select name="statut" aria-label="Filtrer par statut">
    <option value="">Tous les statuts</option>
    <?php foreach ($statuts as $cle => $libelle): ?>
      <option value="<?= e($cle) ?>" <?= $statut === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="tag" aria-label="Filtrer par étiquette">
    <option value="">Toutes les étiquettes</option>
    <?php foreach ($tags as $t => $n): ?>
      <option value="<?= e($t) ?>" <?= $tag === $t ? 'selected' : '' ?>><?= e($t) ?> (<?= $n ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="tri" aria-label="Trier par">
    <option value="ajout" <?= $tri === 'ajout' ? 'selected' : '' ?>>Ajout récent</option>
    <option value="titre" <?= $tri === 'titre' ? 'selected' : '' ?>>Titre A→Z</option>
    <option value="note" <?= $tri === 'note' ? 'selected' : '' ?>>Meilleures notes</option>
    <option value="lecture" <?= $tri === 'lecture' ? 'selected' : '' ?>>Dernière lecture</option>
  </select>
  <button class="bouton" type="submit">Filtrer</button>
  <?php if ($q !== '' || $statut !== '' || $tag !== ''): ?>
    <a class="bouton secondaire" href="<?= url('/') ?>">Réinitialiser</a>
  <?php endif; ?>
</form>

<?php if (!$livres): ?>
  <div class="vide">
    <p>📖 Aucun livre pour l'instant.</p>
    <p><a class="bouton" href="<?= url('/livres/nouveau') ?>">Ajouter mon premier livre</a>
       ou <a href="<?= url('/import') ?>">importer ma bibliothèque Calibre</a>.</p>
  </div>
<?php else: ?>
<div class="grille">
  <?php foreach ($livres as $l): ?>
    <a class="carte" href="<?= url('/livre/' . $l['id']) ?>">
      <span class="couverture">
        <?php $srcCouv = couverture_src($l); ?>
        <?php if ($srcCouv): ?>
          <img src="<?= e($srcCouv) ?>" alt="Couverture de <?= e($l['titre']) ?>" loading="lazy">
        <?php else: ?>
          <span class="couverture-vide">📖</span>
        <?php endif; ?>
      </span>
      <span class="infos">
        <span class="titre"><?= e($l['titre']) ?></span>
        <span class="auteurs"><?= e(implode(', ', $l['auteurs'] ?? [])) ?><?= !empty($l['annee']) ? ' · ' . (int) $l['annee'] : '' ?></span>
        <span class="ligne-badges">
          <span class="badge statut-<?= e($l['statut'] ?? '') ?>"><?= e($statuts[$l['statut'] ?? ''] ?? $l['statut'] ?? '') ?></span>
          <?php if (!empty($l['note'])): ?><span class="note" title="Note personnelle"><?= str_repeat('★', (int) $l['note']) ?><?= str_repeat('☆', 5 - (int) $l['note']) ?></span><?php endif; ?>
        </span>
        <?php if (!empty($l['tags'])): ?>
          <span class="tags"><?php foreach ($l['tags'] as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?></span>
        <?php endif; ?>
      </span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
