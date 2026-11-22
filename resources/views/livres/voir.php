<article class="detail">
  <div class="detail-couverture">
    <?php $srcCouv = couverture_src($livre); ?>
    <?php if ($srcCouv): ?>
      <img src="<?= e($srcCouv) ?>" alt="Couverture de <?= e($livre['titre']) ?>">
    <?php else: ?>
      <div class="couverture-vide grande">📖</div>
    <?php endif; ?>
  </div>
  <div class="detail-infos">
    <h1><?= e($livre['titre']) ?></h1>
    <p class="auteurs"><?= e(implode(', ', $livre['auteurs'] ?? [])) ?></p>
    <dl class="meta">
      <?php if (!empty($livre['isbn'])): ?><div><dt>ISBN</dt><dd><?= e($livre['isbn']) ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['editeur'])): ?><div><dt>Éditeur</dt><dd><?= e($livre['editeur']) ?><?= !empty($livre['lieu']) ? ' (' . e($livre['lieu']) . ')' : '' ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['annee'])): ?><div><dt>Année</dt><dd><?= (int) $livre['annee'] ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['pages'])): ?><div><dt>Pages</dt><dd><?= (int) $livre['pages'] ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['format'])): ?><div><dt>Format</dt><dd><?= e($livre['format']) ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['langue'])): ?><div><dt>Langue</dt><dd><?= e($livre['langue']) ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['serie'])): ?><div><dt>Série</dt><dd><?= e($livre['serie']) ?></dd></div><?php endif; ?>
      <?php if (!empty($livre['source'])): ?><div><dt>Source</dt><dd><?= e($livre['source']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if (!empty($livre['description'])): ?>
      <details class="description"><summary>Description (Open Library)</summary><p><?= nl2br(e($livre['description'])) ?></p></details>
    <?php endif; ?>
    <?php if (!empty($livre['sujets'])): ?>
      <p class="sujets"><?php foreach ($livre['sujets'] as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?></p>
    <?php endif; ?>
  </div>
  <div class="detail-perso">
    <h2>Ma lecture</h2>
    <p><span class="badge statut-<?= e($livre['statut'] ?? '') ?>"><?= e($statuts[$livre['statut'] ?? ''] ?? '') ?></span>
       <?php if (!empty($livre['note'])): ?> <span class="note"><?= str_repeat('★', (int) $livre['note']) ?><?= str_repeat('☆', 5 - (int) $livre['note']) ?></span><?php endif; ?></p>
    <?php if (!empty($livre['date_lecture'])): ?><p>Lu le <?= e(date_fr($livre['date_lecture'])) ?></p><?php endif; ?>
    <?php if (!empty($livre['tags'])): ?><p class="tags"><?php foreach ($livre['tags'] as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?></p><?php endif; ?>
    <?php if (!empty($livre['commentaire'])): ?>
      <blockquote class="commentaire"><?= nl2br(e($livre['commentaire'])) ?></blockquote>
    <?php endif; ?>
    <div class="actions">
      <a class="bouton" href="<?= url('/livre/' . $livre['id'] . '/edit') ?>">Modifier</a>
      <a class="bouton secondaire" href="<?= url('/livre/' . $livre['id'] . '/fiche/nouvelle') ?>">+ Fiche de lecture</a>
      <form method="post" action="<?= url('/livre/' . $livre['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer ce livre et ses fiches ?');">
        <?= csrf_field() ?>
        <button class="bouton danger" type="submit">Supprimer</button>
      </form>
    </div>
    <?php if (!empty($liens_partage)): ?>
      <h2>Partager ce livre (liens publics)</h2>
      <p class="aide">Toute fiche publiée sur ce livre apparaîtra sur ces liens :</p>
      <ul class="liens-partage">
        <?php foreach ($liens_partage as $libelle => $lien): ?>
          <li>
            <span class="tag"><?= e($libelle) ?></span>
            <input type="text" readonly class="lien-partage" value="<?= e($lien) ?>" onclick="this.select()">
            <button class="bouton secondaire petit copier-lien" type="button" data-lien="<?= e($lien) ?>">Copier</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</article>

<section class="fiches">
  <h2>Fiches de lecture</h2>
  <?php if (!$fiches): ?>
    <p class="vide-petit">Aucune fiche pour ce livre. <a href="<?= url('/livre/' . $livre['id'] . '/fiche/nouvelle') ?>">En écrire une</a>.</p>
  <?php endif; ?>
  <?php foreach ($fiches as $f): ?>
    <article class="fiche" id="fiche-<?= e($f['id']) ?>">
      <header>
        <h3><?= e($f['titre']) ?></h3>
        <span class="fiche-badges">
          <?php if (!empty($f['note'])): ?><span class="note"><?= str_repeat('★', (int) $f['note']) ?><?= str_repeat('☆', 5 - (int) $f['note']) ?></span><?php endif; ?>
          <?php if (!empty($f['publique']) && !empty($f['token'])): ?>
            <a class="badge public" href="<?= url_partage($f['token']) ?>" target="_blank" title="Ouvrir la page publique">🌐 partagée</a>
          <?php else: ?>
            <span class="badge prive" title="Visible seulement par toi">🔒 privée</span>
          <?php endif; ?>
        </span>
      </header>
      <div class="fiche-contenu"><?= mini_markdown($f['contenu']) ?></div>
      <?php if (!empty($f['citations'])): ?>
        <div class="citations">
          <h4>Citations</h4>
          <?php foreach ($f['citations'] as $c): ?><blockquote><?= e($c) ?></blockquote><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="fiche-pied">Écrite le <?= e(date_fr(substr($f['date_creation'] ?? '', 0, 10))) ?></p>
      <div class="actions">
        <a class="bouton secondaire petit" href="<?= url('/fiche/' . $f['id'] . '/edit') ?>">Modifier</a>
        <form method="post" action="<?= url('/fiche/' . $f['id'] . '/publication') ?>">
          <?= csrf_field() ?>
          <button class="bouton secondaire petit" type="submit"><?= !empty($f['publique']) ? 'Rendre privée' : 'Publier / partager' ?></button>
        </form>
        <?php if (!empty($f['publique']) && !empty($f['token'])): ?>
          <button class="bouton secondaire petit copier-lien" type="button" data-lien="<?= url_partage($f['token']) ?>">Copier le lien fiche</button>
        <?php endif; ?>
        <form method="post" action="<?= url('/fiche/' . $f['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette fiche ?');">
          <?= csrf_field() ?>
          <button class="bouton danger petit" type="submit">Supprimer</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</section>
