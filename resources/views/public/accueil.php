<section class="entete">
  <h1>Fiches de lecture partagées</h1>
  <p class="aide">Découvrez les fiches de lecture publiées par les membres de <?= e($config['app_name']) ?>.
  Recherchez par champ : <code>?isbn=9782070360024</code>, <code>?auteur=Camus</code>, <code>?tag=philosophie</code>, <code>?q=…</code></p>
</section>

<form class="filtres" method="get" action="<?= url('/') ?>">
  <input type="search" name="q" placeholder="Titre, auteur, éditeur, sujet…" aria-label="Recherche publique">
  <input type="search" name="auteur" placeholder="Auteur" aria-label="Auteur">
  <input type="search" name="isbn" placeholder="ISBN" aria-label="ISBN">
  <input type="search" name="tag" placeholder="Étiquette" aria-label="Étiquette">
  <button class="bouton" type="submit">Rechercher</button>
</form>

<?php if (!$fiches): ?>
  <div class="vide"><p>📖 Aucune fiche partagée pour l'instant.</p></div>
<?php else: ?>
  <h2>Dernières fiches publiées</h2>
  <div class="liste-fiches-publiques">
    <?php foreach ($fiches as $f): ?>
      <?php $livre = $livres[$f['livre_id'] ?? ''] ?? null; $auteur = $users[$f['user_id'] ?? ''] ?? null; ?>
      <article class="fiche-publique-carte">
        <a href="<?= url_partage($f['token']) ?>">
          <span class="couverture">
            <?php if ($livre && !empty($livre['couverture'])): ?>
              <img src="<?= asset($livre['couverture']) ?>" alt="Couverture de <?= e($livre['titre']) ?>" loading="lazy">
            <?php else: ?>
              <span class="couverture-vide">📖</span>
            <?php endif; ?>
          </span>
          <div class="fiche-corps">
            <h3><?= e($f['titre']) ?></h3>
            <?php if ($livre): ?>
              <p class="fiche-livre">sur <strong><?= e($livre['titre']) ?></strong> de <?= e(implode(', ', $livre['auteurs'] ?? [])) ?></p>
              <p class="fiches-liens-mini">
                <a href="<?= url('/?isbn=' . rawurlencode($livre['isbn'])) ?>" onclick="event.stopPropagation()">toutes les fiches sur ce livre</a>
              </p>
            <?php endif; ?>
            <p class="fiche-meta">par <?= e(user_display($auteur)) ?> · <?= e(date_fr(substr($f['date_creation'] ?? '', 0, 10))) ?>
              <?php if (!empty($f['note'])): ?> · <span class="note"><?= str_repeat('★', (int) $f['note']) ?></span><?php endif; ?>
            </p>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
