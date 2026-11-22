<?php use App\Controller\StatsController; ?>
<h1>📊 Mes statistiques de lecture</h1>

<div class="stats stats-glob">
  <span class="stat"><strong><?= $total ?></strong> livre<?= $total > 1 ? 's' : '' ?></span>
  <span class="stat lu"><strong><?= $pagesLues ?></strong> pages lues<?= $pagesTotales > $pagesLues ? ' / ' . $pagesTotales . ' au total' : '' ?></span>
  <?php if ($noteMoyenne !== null): ?><span class="stat"><strong><?= $noteMoyenne ?>/5</strong> note moyenne (<?= count(array_filter($repartitionNotes, fn ($n) => $n > 0)) ?> livre<?= count(array_filter($repartitionNotes, fn ($n) => $n > 0)) > 1 ? 's' : '' ?> noté<?= count(array_filter($repartitionNotes, fn ($n) => $n > 0)) > 1 ? 's' : '' ?>)</span><?php endif; ?>
  <span class="stat"><strong><?= $nbFiches ?></strong> fiche<?= $nbFiches > 1 ? 's' : '' ?> (<?= $nbFichesPubliques ?> publique<?= $nbFichesPubliques > 1 ? 's' : '' ?>)</span>
</div>

<div class="stats-grid">
  <section class="panneau">
    <h2>Répartition par statut</h2>
    <div class="donut-et-legende">
      <?= StatsController::donutSvg($segments) ?>
      <ul class="legende">
        <?php foreach ($parStatut as $cle => $s): if ($s['nb'] === 0) continue; ?>
          <li><span class="pastille statut-<?= e($cle) ?>"></span> <?= e($s['libelle']) ?> — <strong><?= $s['nb'] ?></strong></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="panneau">
    <h2>Répartition des notes</h2>
    <?php foreach ($repartitionNotes as $n => $nb): ?>
      <div class="barre-ligne">
        <span class="barre-etiquette"><?= str_repeat('★', $n) ?> <?= $n ?>/5</span>
        <span class="barre"><span class="barre-remplissage note" style="width: <?= $nb ? round($nb / $maxNotes * 100) : 0 ?>%"></span></span>
        <span class="barre-valeur"><?= $nb ?></span>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<section class="panneau">
  <h2>Lectures par mois (12 derniers mois)</h2>
  <div class="barres-mois">
    <?php foreach ($parMois as $m): ?>
      <div class="barre-mois" title="<?= e($m['libelle']) ?> : <?= $m['lus'] ?> lu(s), <?= $m['pages'] ?> pages, <?= $m['ajoutes'] ?> ajouté(s)">
        <span class="barre-verticale lue" style="height: <?= round($m['lus'] / $maxLus * 70) ?>px"></span>
        <span class="barre-verticale pages" style="height: <?= round($m['pages'] / $maxPages * 70) ?>px"></span>
        <span class="barre-etiquette"><?= e(mb_substr($m['libelle'], 0, 3)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="aide"><span class="pastille lu"></span> livres lus &nbsp; <span class="pastille pages"></span> pages lues (échelle indépendante, infobulle au survol)</p>
</section>

<div class="stats-grid">
  <section class="panneau">
    <h2>Top auteurs lus</h2>
    <?php if (!$topAuteurs): ?><p class="vide-petit">Aucun auteur pour l'instant.</p><?php endif; ?>
    <?php foreach ($topAuteurs as $a => $nb): ?>
      <div class="barre-ligne">
        <span class="barre-etiquette"><?= e($a) ?></span>
        <span class="barre"><span class="barre-remplissage" style="width: <?= round($nb / $maxAuteurs * 100) ?>%"></span></span>
        <span class="barre-valeur"><?= $nb ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="panneau">
    <h2>Étiquettes les plus utilisées</h2>
    <?php if (!$topTags): ?><p class="vide-petit">Aucune étiquette pour l'instant.</p><?php endif; ?>
    <?php foreach ($topTags as $t => $nb): ?>
      <div class="barre-ligne">
        <span class="barre-etiquette"><a class="tag lien-tag" href="<?= url('/?tag=' . rawurlencode($t)) ?>"><?= e($t) ?></a></span>
        <span class="barre"><span class="barre-remplissage tag-bar" style="width: <?= round($nb / $maxTags * 100) ?>%"></span></span>
        <span class="barre-valeur"><?= $nb ?></span>
      </div>
    <?php endforeach; ?>
  </section>
</div>
