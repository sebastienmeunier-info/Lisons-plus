<h1>Fiches partagées</h1>
<p class="aide">Toutes les fiches que tu as rendues publiques. Le lien de chaque fiche est accessible sans compte, à transmettre à qui tu veux.</p>

<?php if (!$fiches): ?>
  <div class="vide">
    <p>Aucune fiche publique pour l'instant.</p>
    <p>Depuis la page d'un livre, crée une fiche puis clique sur « Publier / partager ».</p>
  </div>
<?php else: ?>
  <ul class="liste-partages">
    <?php foreach ($fiches as $f): ?>
      <li>
        <div class="partage-infos">
          <a href="<?= url_partage($f['token']) ?>" target="_blank"><strong><?= e($f['titre']) ?></strong></a>
          <?php if (!empty($livres[$f['livre_id']])): ?>
            <span class="partage-livre"> — <?= e($livres[$f['livre_id']]['titre']) ?> (<?= e(implode(', ', $livres[$f['livre_id']]['auteurs'] ?? [])) ?>)</span>
          <?php endif; ?>
          <span class="note"><?= !empty($f['note']) ? str_repeat('★', (int) $f['note']) : '' ?></span>
        </div>
        <div class="partage-actions">
          <input type="text" readonly value="<?= url_partage($f['token']) ?>" class="lien-partage" onclick="this.select()">
          <button class="bouton secondaire petit copier-lien" type="button" data-lien="<?= url_partage($f['token']) ?>">Copier</button>
          <a class="bouton secondaire petit" href="<?= url('/fiche/' . $f['id'] . '/edit') ?>">Modifier</a>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
