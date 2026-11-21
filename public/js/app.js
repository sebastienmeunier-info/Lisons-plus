/* Lisons-plus — comportements côté client */
(function () {
  'use strict';

  // --- Recherche ISBN en direct depuis le formulaire d'ajout ---
  var btn = document.getElementById('btn-isbn');
  var champ = document.getElementById('isbn');
  var statut = document.getElementById('isbn-statut');
  if (btn && champ) {
    var chercher = function () {
      var isbn = champ.value.trim();
      if (!isbn) return;
      statut.textContent = 'Recherche dans Open Library…';
      btn.disabled = true;
      fetch('api/isbn/' + encodeURIComponent(isbn), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          btn.disabled = false;
          if (!res.ok || !res.j.ok) {
            statut.textContent = res.j.erreur || 'Introuvable.';
            return;
          }
          var m = res.j.meta;
          statut.textContent = 'Trouvé : ' + (m.titre || '') + '. Formulaire pré-rempli.';
          remplir('titre', m.titre || '');
          remplir('auteurs', (m.auteurs || []).join(', '));
          remplir('editeur', m.editeur || '');
          remplir('annee', m.annee || '');
          remplir('pages', m.pages || '');
          remplir('format', m.format || '');
          remplir('langue', m.langue || '');
          remplir('serie', m.serie || '');
          remplir('description', m.description || '');
          remplir('sujets', (m.sujets || []).join(', '));
          // Aperçu couverture
          var ap = document.getElementById('isbn-apercu');
          if (ap && m.couverture_url) {
            document.getElementById('isbn-couverture').src = m.couverture_url;
            document.getElementById('isbn-titre').textContent = m.titre || '';
            document.getElementById('isbn-auteurs').textContent = (m.auteurs || []).join(', ');
            ap.hidden = false;
          }
          // Remplit le vrai champ ISBN du formulaire avec la valeur normalisée
          var hidden = document.querySelector('form.formulaire input[name="isbn"]');
          if (hidden) hidden.value = res.j.isbn;
          else champ.setAttribute('form', '');
        })
        .catch(function () {
          btn.disabled = false;
          statut.textContent = 'Erreur réseau : réessaie ou remplis à la main.';
        });
    };
    btn.addEventListener('click', chercher);
    champ.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); chercher(); } });

    function remplir(id, valeur) {
      var el = document.getElementById(id);
      if (el && !el.value) el.value = valeur;
    }
  }

  // --- Boutons « copier le lien » ---
  document.querySelectorAll('.copier-lien').forEach(function (b) {
    b.addEventListener('click', function () {
      var lien = b.getAttribute('data-lien');
      var fait = function () {
        var old = b.textContent;
        b.textContent = 'Copié !';
        setTimeout(function () { b.textContent = old; }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(lien).then(fait).catch(function () { window.prompt('Lien à copier :', lien); });
      } else {
        window.prompt('Lien à copier :', lien);
      }
    });
  });
})();
