<section class="entete">
  <h1>Résultats du partage</h1>
  <p class="aide">Fiches de lecture publiées correspondant à :
    <?php foreach ($criteres as $c => $v): ?>
      <span class="tag"><?= e($c) ?> = « <?= e($v) ?> »</span>
    <?php endforeach; ?>
  </p>
</section>

<form class="filtres" method="get" action="<?= url('/') ?>">
  <input type="search" name="q" value="<?= e($criteres['q'] ?? $criteres['titre'] ?? '') ?>" placeholder="Titre, auteur, éditeur, sujet…">
  <input type="search" name="auteur" value="<?= e($criteres['auteur'] ?? '') ?>" placeholder="Auteur">
  <input type="search" name="isbn" value="<?= e($criteres['isbn'] ?? '') ?>" placeholder="ISBN">
  <input type="search" name="tag" value="<?= e($criteres['tag'] ?? '') ?>" placeholder="Étiquette">
  <button class="bouton" type="submit">Rechercher</button>
  <a class="bouton secondaire" href="<?= url('/') ?>">Tout voir</a>
</form>

<?php if (!$livres && !$fiches): ?>
  <div class="vide"><p>📖 Aucun livre ni fiche partagée ne correspond.</p></div>
<?php endif; ?>

<?php if ($livres): ?>
  <h2><?= count($livres) ?> livre<?= count($livres) > 1 ? 's' : '' ?> concerné<?= count($livres) > 1 ? 's' : '' ?></h2>
  <div class="grille">
    <?php foreach ($livres as $l): ?>
      <?php
        $nbFiches = 0;
        foreach ($fiches as $f) { if (($f['livre_id'] ?? '') === $l['id']) { $nbFiches++; } }
      ?>
      <div class="carte carte-static">
        <span class="couverture">
          <?php if (!empty($l['couverture'])): ?>
            <img src="<?= asset($l['couverture']) ?>" alt="Couverture de <?= e($l['titre']) ?>" loading="lazy">
          <?php else: ?>
            <span class="couverture-vide">📖</span>
          <?php endif; ?>
        </span>
        <span class="infos">
          <span class="titre"><?= e($l['titre']) ?></span>
          <span class="auteurs"><?= e(implode(', ', $l['auteurs'] ?? [])) ?><?= !empty($l['annee']) ? ' · ' . (int) $l['annee'] : '' ?></span>
          <span class="auteurs"><?= $nbFiches ?> fiche<?= $nbFiches > 1 ? 's' : '' ?> partagée<?= $nbFiches > 1 ? 's' : '' ?></span>
          <span class="tags">
            <?php if (!empty($l['isbn'])): ?><a class="tag lien-tag" href="<?= url('/?isbn=' . rawurlencode($l['isbn'])) ?>">isbn <?= e($l['isbn']) ?></a><?php endif; ?>
            <?php foreach (array_slice($l['auteurs'] ?? [], 0, 2) as $a): ?><a class="tag lien-tag" href="<?= url('/?auteur=' . rawurlencode($a)) ?>">auteur <?= e($a) ?></a><?php endforeach; ?>
            <?php foreach (array_slice($l['tags'] ?? [], 0, 3) as $t): ?><a class="tag lien-tag" href="<?= url('/?tag=' . rawurlencode($t)) ?>">tag <?= e($t) ?></a><?php endforeach; ?>
          </span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($fiches): ?>
  <h2><?= count($fiches) ?> fiche<?= count($fiches) > 1 ? 's' : '' ?> de lecture</h2>
  <div class="liste-fiches-publiques">
    <?php foreach ($fiches as $f): ?>
      <?php $livre = $livres[$f['livre_id'] ?? ''] ?? null; $auteur = $users[$f['user_id'] ?? ''] ?? null; ?>
      <article class="fiche-publique-carte">
        <a href="<?= url_partage($f['token']) ?>">
          <h3><?= e($f['titre']) ?></h3>
          <?php if ($livre): ?><p class="fiche-livre">sur <strong><?= e($livre['titre']) ?></strong> de <?= e(implode(', ', $livre['auteurs'] ?? [])) ?></p><?php endif; ?>
          <p class="fiche-meta">par <?= e(user_display($auteur)) ?> · <?= e(date_fr(substr($f['date_creation'] ?? '', 0, 10))) ?>
            <?php if (!empty($f['note'])): ?> · <span class="note"><?= str_repeat('★', (int) $f['note']) ?></span><?php endif; ?>
          </p>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
