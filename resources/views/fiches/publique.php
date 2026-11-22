<article class="fiche publique">
  <header class="fiche-entete">
    <?php if ($livre && !empty($livre['couverture'])): ?>
      <img class="fiche-couverture" src="<?= asset($livre['couverture']) ?>" alt="Couverture de <?= e($livre['titre']) ?>">
    <?php endif; ?>
    <div>
      <h1><?= e($fiche['titre']) ?></h1>
      <?php if ($livre): ?>
        <p class="fiche-livre">Une fiche de lecture sur <strong><?= e($livre['titre']) ?></strong> de <?= e(implode(', ', $livre['auteurs'] ?? [])) ?><?= !empty($livre['annee']) ? ' (' . (int) $livre['annee'] . ')' : '' ?></p>
      <?php endif; ?>
      <p class="fiche-meta">Par <?= e(user_display($auteur_fiche)) ?> · partagée le <?= e(date_fr(substr($fiche['date_creation'] ?? '', 0, 10))) ?>
        <?php if (!empty($fiche['note'])): ?> · <span class="note"><?= str_repeat('★', (int) $fiche['note']) ?><?= str_repeat('☆', 5 - (int) $fiche['note']) ?></span><?php endif; ?>
      </p>
    </div>
  </header>

  <div class="fiche-contenu"><?= mini_markdown($fiche['contenu']) ?></div>

  <?php if (!empty($fiche['citations'])): ?>
    <section class="citations">
      <h2>Citations</h2>
      <?php foreach ($fiche['citations'] as $c): ?><blockquote><?= e($c) ?></blockquote><?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if (!empty($liens_partage)): ?>
    <footer class="fiche-partages">
      <h2>Voir aussi</h2>
      <ul class="liens-partage">
        <?php foreach ($liens_partage as $libelle => $lien): ?>
          <li><a class="tag lien-tag" href="<?= e($lien) ?>"><?= e($libelle) ?> → toutes les fiches</a></li>
        <?php endforeach; ?>
      </ul>
    </footer>
  <?php endif; ?>
</article>
