# Lisons-plus 📚

Lisons-plus est une application web pour **inventorier ses lectures** et **partager ses fiches de lecture**.

- **Pas de base de données** : stockage pérenne en fichiers JSON (`data/`), comme Plantons-plus — auto-hébergeable sur n'importe quel hébergement PHP, `index.php` à la racine.
- **Comptes utilisateurs** (calqués sur Plantons-plus) : inscription, connexion, mot de passe oublié, sessions PHP sécurisées, jetons CSRF, et **3 rôles hiérarchiques** : `contributeur` < `administrateur` < `super_administrateur` (le premier inscrit est super-administrateur), avec espace admin de gestion des utilisateurs et modération des fiches.
- Chaque livre est décrit par ses **métadonnées ISBN** (recherche automatique via [Open Library](https://openlibrary.org) : titre, auteur, éditeur, année, pages, couverture, description) **plus vos informations personnelles** : statut de lecture, note sur 5, commentaire, étiquettes, date de lecture.
- Des **fiches de lecture** (texte mis en forme, citations, note) par livre, publiables individuellement par lien (`/?partage={token}`).
- **Partage par champ** : les champs d'un livre génèrent des liens publics qui agrègent toutes les fiches publiées correspondantes : `lisons-plus.example/?isbn=9782070360024`, `?auteur=zola`, `?tag=philosophie`, `?editeur=…`, `?statut=lu`, `?q=…`.
- Import possible d'une **bibliothèque Calibre** existante.

## Prérequis

- PHP 8.2 ou supérieur
- extensions PHP : `json`, `mbstring`, `curl`, `fileinfo`, `session`
- droits d'écriture pour le processus PHP sur `data/`, `logs/` et `uploads/`

## Installation rapide (développement / test)

```bash
cd Lisons-plus
php -S 127.0.0.1:8080 dev-router.php
# ouvrir http://127.0.0.1:8080  →  créer le premier compte (super-administrateur)
```

## Installation sur un hébergement web (Apache/nginx)

Voir [docs/install.md](docs/install.md) : `index.php` à la racine du DocumentRoot, réécriture d'URL (`mod_rewrite` + `AllowOverride All`, ou `try_files … /index.php` sous nginx), blocage de `data/`, `logs/`, `config/`, `app/`.

## Utilisation

1. **Inscription / connexion** : `/inscription`, `/connexion`. Le premier compte créé devient super-administrateur.
2. **Ajouter un livre** : saisir son ISBN, ou **scanner le code-barres EAN au dos du livre avec la caméra** (BarcodeDetector natif, sinon ZXing embarqué localement dans `public/js/vendor/` ; fonctionne en HTTPS ou sur `localhost`) → pré-remplissage automatique via Open Library + couverture téléchargée localement. Compléter statut, note, commentaire, étiquettes.
3. **Statistiques de lecture** (`/stats`) : répartition par statut (donut SVG), notes, pages lues, lectures par mois sur 12 mois, top auteurs lus et étiquettes — calculées à la volée depuis tes livres.
4. **Ma bibliothèque** (`/`, connecté) : grille personnelle avec recherche, filtres (statut, étiquette) et tris. Chaque livre n'est visible que par son propriétaire (et les admins).
5. **Fiche de lecture** : depuis un livre, rédiger une fiche (mini-markdown : `**gras**`, `*italique*`, `# titre`, `> citation`, `- liste`, `[texte](https://…)`), puis **Publier / partager** → lien `/?partage={token}` + **liens par champ** (`/?isbn=…`, `/?auteur=…`, `/?tag=…`) copiables depuis la page du livre.
6. **Côté visiteur** (déconnecté), `/` est une page publique : dernières fiches partagées, et `/?isbn=…&auteur=…&tag=…` listent les livres et fiches publiques correspondants.
7. **Espace admin** (`/admin`, administrateur+) : statistiques globales, gestion des utilisateurs (rôles hiérarchiques, validation des inscriptions, suppression), modération des fiches publiques.
8. **Import Calibre** (`/import`) :
   ```bash
   python3 scripts/calibre_to_json.py "/chemin/Bibliothèque calibre/metadata.db" data/import-calibre.json
   ```
   puis envoyer `data/import-calibre.json` dans le formulaire (doublons d'ISBN ignorés, import dans la bibliothèque du compte connecté). CSV accepté aussi.

## Architecture

```text
index.php            Front controller à la racine + routes (avec options auth/rôle)
dev-router.php       Routeur pour le serveur de dev (php -S)
app/
  Core/              Router, Request, Response, App (gardes auth/CSRF), JsonStore, View, helpers
  Controller/        Home, Livre, Fiche, Api, Import, Auth, Account, Admin
  Service/           AuthService (sessions, CSRF, rôles), IsbnService (Open Library)
  Model/             Livre, Fiche
config/config.php    Configuration (env LISONS_*), rôles et règles d'inscription
data/                JSON : livres, fiches, utilisateurs, réinitialisations, sessions (non versionné)
public/              CSS et JS
resources/views/     Vues PHP (layout perso / layout public)
uploads/covers/      Couvertures téléchargées
logs/                Journaux
scripts/             calibre_to_json.py (export Calibre)
docs/                Installation
```

### Sécurité

Sessions PHP stockées localement (`data/sessions`), cookies `httponly` + `samesite=Lax` (+ `secure` en HTTPS), `session_regenerate_id` à la connexion, **jeton CSRF vérifié sur chaque POST**, mots de passe hashés (`password_hash`, 12 caractères min), contrôle d'accès par route (auth + rôle hiérarchique), données personnelles isolées par propriétaire, pages publiques `noindex`.

### Sauvegarde

```bash
tar czf lisons-plus-backup.tar.gz data uploads
```

## Licence

GNU General Public License v3.0 ou ultérieure. Voir [LICENSE](LICENSE).
