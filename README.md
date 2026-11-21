# Lisons-plus 📚

Lisons-plus est une application web personnelle pour **inventorier ses lectures** et **partager ses fiches de lecture**.

- Chaque livre est décrit par ses **métadonnées ISBN** (recherche automatique via [Open Library](https://openlibrary.org) : titre, auteur, éditeur, année, pages, couverture, description) **plus vos informations personnelles** : statut de lecture, note sur 5, commentaire, étiquettes, date de lecture.
- Pour chaque livre, vous pouvez rédiger des **fiches de lecture** (texte mis en forme, citations, note) et les **publier par un lien public** à transmettre à vos proches.
- Import possible d'une **bibliothèque Calibre** existante.

Conçu comme un logiciel libre, simple à installer sur un hébergement PHP mutualisé, avec un stockage pérenne en JSON (comme Plantons-plus).

## Prérequis

- PHP 8.2 ou supérieur
- extensions PHP : `json`, `mbstring`, `curl`, `fileinfo`
- droits d'écriture pour le processus PHP sur `data/`, `logs/` et `uploads/`

## Installation rapide (développement)

```bash
cd Lisons-plus
php -S 127.0.0.1:8080 dev-router.php
# ouvrir http://127.0.0.1:8080
```

## Installation sur un hébergement web

Voir [docs/install.md](docs/install.md) (Apache avec `.htaccess`, nginx, configuration par variables d'environnement).

## Utilisation

1. **Ajouter un livre** : saisir son ISBN (ou le scanner) → le formulaire se pré-remplit automatiquement via Open Library, la couverture est téléchargée localement. Compléter statut, note, commentaire, étiquettes.
2. **Ma bibliothèque** (`/`) : grille des livres avec recherche plein texte, filtres (statut, étiquette) et tris.
3. **Fiche de lecture** : depuis la page d'un livre, rédiger une fiche (mini-markdown : `**gras**`, `*italique*`, `# titre`, `> citation`, `- liste`, `[texte](https://…)`), puis **Publier / partager** pour obtenir un lien public du type `/partage/{token}`.
4. **Fiches partagées** (`/partages`) : toutes les fiches publiques avec leurs liens copiables.
5. **Import Calibre** (`/import`) :
   ```bash
   python3 scripts/calibre_to_json.py "/chemin/Bibliothèque calibre/metadata.db" data/import-calibre.json
   ```
   puis envoyer `data/import-calibre.json` dans le formulaire d'import (les doublons d'ISBN sont ignorés). Un export CSV Calibre (`title, authors, isbn, publisher, pubdate, series, tags, rating, comments`) est aussi accepté.

## Architecture

```text
index.php            Front controller + routes
dev-router.php       Routeur pour le serveur de dev (php -S)
app/
  Core/              Router, Request, Response, App, JsonStore (stockage JSON), View, helpers
  Controller/        Home, Livre, Fiche, Api (ISBN/recherche), Import
  Service/           IsbnService (Open Library + couvertures)
  Model/             Livre, Fiche (validation)
config/config.php    Configuration centralisée (surchargeable par variables d'environnement)
data/                Données JSON (livres.json, fiches.json) — non versionnées
public/              CSS et JS
resources/views/     Vues PHP
uploads/covers/      Couvertures téléchargées
logs/                Journaux
scripts/             calibre_to_json.py (export Calibre)
docs/                Installation et notes
```

### Stockage

Les données sont dans `data/livres.json` et `data/fiches.json` (écritures verrouillées par `flock`). Sauvegardez simplement le dossier `data/` (et `uploads/`) pour backuper toute votre bibliothèque.

### Partage

Une fiche publiée est accessible sans compte via `/partage/{token}` (token aléatoire de 16 caractères, `noindex`). La rendre privée supprime l'accès au lien.

## Licence

GNU Affero General Public License v3.0 ou ultérieure.
