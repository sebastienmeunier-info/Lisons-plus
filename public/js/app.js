/* Lisons-plus — comportements côté client */
(function () {
  'use strict';

  // --- Recherche ISBN en direct depuis le formulaire d'ajout ---
  var btn = document.getElementById('btn-isbn');
  var champ = document.getElementById('isbn');
  var statut = document.getElementById('isbn-statut');

  function remplir(id, valeur) {
    var el = document.getElementById(id);
    if (el && !el.value) el.value = valeur;
  }

  var chercher = function () {
    if (!champ) return;
    var isbn = champ.value.trim();
    if (!isbn) return;
    statut.textContent = 'Recherche dans Open Library…';
    if (btn) btn.disabled = true;
    var base = (window.LISONS_BASE || '/').replace(/\/$/, '');
    var urlApi = (typeof window.lisonsRoute === 'function')
      ? window.lisonsRoute('api/isbn/' + encodeURIComponent(isbn))
      : base + '/api/isbn/' + encodeURIComponent(isbn);
    fetch(urlApi, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (btn) btn.disabled = false;
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
        var ap = document.getElementById('isbn-apercu');
        if (ap && m.couverture_url) {
          document.getElementById('isbn-couverture').src = m.couverture_url;
          document.getElementById('isbn-titre').textContent = m.titre || '';
          document.getElementById('isbn-auteurs').textContent = (m.auteurs || []).join(', ');
          ap.hidden = false;
        }
        var hidden = document.querySelector('form.formulaire input[name="isbn"]');
        if (hidden) hidden.value = res.j.isbn;
      })
      .catch(function () {
        if (btn) btn.disabled = false;
        statut.textContent = 'Erreur réseau : réessaie ou remplis à la main.';
      });
  };

  if (btn && champ) {
    btn.addEventListener('click', chercher);
    champ.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); chercher(); } });
  }

  // --- Scan du code-barres ISBN (caméra) ---
  var btnScan = document.getElementById('btn-scan');
  var modal = document.getElementById('scan-modal');
  var video = document.getElementById('scan-video');
  var scanStatut = document.getElementById('scan-statut');
  var btnFermer = document.getElementById('btn-scan-fermer');
  var stream = null;
  var scanEnCours = false;
  var lecteurZxing = null;

  function nettoyerISBN(texte) {
    return (texte || '').replace(/[^0-9Xx]/g, '').toUpperCase();
  }

  function looksLikeISBN(v) {
    var v10 = /^[0-9]{9}[0-9X]$/.test(v);
    var v13 = /^[0-9]{13}$/.test(v);
    return v10 || v13;
  }

  function arreterScan() {
    scanEnCours = false;
    if (lecteurZxing) { try { lecteurZxing.reset(); } catch (e) {} lecteurZxing = null; }
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    if (modal) modal.hidden = true;
  }

  function apresDetection(code) {
    var isbn = nettoyerISBN(code);
    if (!looksLikeISBN(isbn)) return false;
    arreterScan();
    if (champ) champ.value = isbn;
    if (statut) statut.textContent = 'ISBN détecté : ' + isbn + ' — recherche…';
    chercher();
    return true;
  }

  function boucleNative(detector) {
    if (!scanEnCours) return;
    detector.detect(video).then(function (codes) {
      if (!scanEnCours) return;
      if (codes && codes.length) {
        for (var i = 0; i < codes.length; i++) {
          if (apresDetection(codes[i].rawValue)) return;
        }
      }
      setTimeout(function () { boucleNative(detector); }, 250);
    }).catch(function () { setTimeout(function () { boucleNative(detector); }, 400); });
  }

  function demarrerScan() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      scanStatut.textContent = 'Caméra indisponible sur ce navigateur/contexte (HTTP non sécurisé ?). Saisis l’ISBN à la main.';
      return;
    }
    scanStatut.textContent = 'Autorise la caméra, puis vise le code-barres au dos du livre…';
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
      .then(function (s) {
        stream = s;
        video.srcObject = s;
        return video.play();
      })
      .then(function () {
        scanEnCours = true;
        // 1) API native (Chrome/Edge récents)
        if ('BarcodeDetector' in window) {
          var detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'itf'] });
          scanStatut.textContent = 'Scan en cours… vise le code-barres.';
          boucleNative(detector);
          return;
        }
        // 2) Fallback : ZXing embarqué localement (public/js/vendor/zxing.min.js)
        if (window.ZXing) {
          scanStatut.textContent = 'Scan en cours (ZXing)… vise le code-barres.';
          lecteurZxing = new ZXing.BrowserMultiFormatReader();
          lecteurZxing.decodeFromVideoDevice(undefined, video, function (result, err) {
            if (result) {
              apresDetection(result.getText());
            }
          });
          return;
        }
        scanStatut.textContent = 'Détection de code-barres non supportée par ce navigateur : saisis l’ISBN à la main.';
      })
      .catch(function (err) {
        scanStatut.textContent = 'Caméra refusée ou indisponible (' + (err && err.name ? err.name : 'erreur') + '). Saisis l’ISBN à la main.';
      });
  }

  if (btnScan && modal && video) {
    btnScan.addEventListener('click', function () {
      modal.hidden = false;
      demarrerScan();
    });
    if (btnFermer) btnFermer.addEventListener('click', arreterScan);
    modal.addEventListener('click', function (e) { if (e.target === modal) arreterScan(); });
  }

  // --- ISBN transmis dans l'URL du formulaire à la soumission ---
  // (le bouton « Ajouter à ma bibliothèque » soumet ce formulaire : l'ISBN
  //  courant y est ajouté, pour que la couverture soit téléchargée même si
  //  le champ isbn disparaissait du POST)
  var formLivre = document.querySelector('form.formulaire');
  var champIsbn = formLivre ? formLivre.querySelector('input[name="isbn"]') : null;
  if (formLivre && champIsbn) {
    formLivre.addEventListener('submit', function () {
      var v = champIsbn.value.trim();
      if (!v) return;
      var a = formLivre.getAttribute('action') || '';
      a = a.replace(/([?&])isbn=[^&]*/, '$1').replace(/[?&]$/, '');
      formLivre.setAttribute('action', a + (a.indexOf('?') >= 0 ? '&' : '?') + 'isbn=' + encodeURIComponent(v));
    });
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
