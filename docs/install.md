# Installation de Lisons-plus

Auto-hébergeable, **sans base de données** : toutes les données sont des fichiers JSON dans `data/`. `index.php` se place à la racine du serveur web.

## 1. Prérequis

- PHP 8.2+ avec les extensions `json`, `mbstring`, `curl`, `fileinfo`, `session`.
- Un serveur web (Apache ou nginx) dont le DocumentRoot est le dossier du projet.
- Droits d'écriture pour le processus PHP sur `data/`, `logs/`, `uploads/` :
  ```bash
  chown -R www-data:www-data data logs uploads   # adapter selon l'hébergement
  chmod -R u+rwX data logs uploads
  ```

## 2. Déploiement

Copier le contenu du dépôt à la racine du DocumentRoot (ex. `/var/www/html`), **index.php à la racine**.

### Apache

Activer la réécriture et autoriser les `.htaccess` :

```bash
a2enmod rewrite        # mod_rewrite
# dans la configuration du vhost ou apache2.conf : AllowOverride All pour le DocumentRoot
```

`.htaccess` fourni à la racine :

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
RedirectMatch 404 ^/(data|logs|config|app)/
```

### nginx

```nginx
root /var/www/html;
index index.php;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(data|logs|config|app)/ { deny all; }
```

> Sans réécriture, seules les URLs « réelles » (fichiers) répondent : activez-la, ou testez d'abord avec `php -S`.

## 3. Configuration (variables d'environnement)

Centralisée dans `config/config.php`, surchargeable par environnement :

| Variable | Rôle |
|---|---|
| `LISONS_APP_NAME` | Nom affiché |
| `LISONS_BASE_URL` | URL publique (ex. `https://livres.example.tld`) ; détection auto sinon |
| `LISONS_ADMIN_EMAIL` | Contact (User-Agent Open Library) |
| `LISONS_DEBUG` | `1` = erreurs détaillées + liens de réinit. de mot de passe affichés (dev) |
| `LISONS_SESSIONS_PATH` | Dossier des sessions PHP (défaut `data/sessions`) |
| `LISONS_BOOTSTRAP_SUPER_ADMIN_EMAIL` | E-mail promu super-administrateur à l'inscription |
| `LISONS_BOOTSTRAP_ADMIN_EMAIL` | E-mail promu administrateur |
| `LISONS_ADMINISTRATOR_EMAILS` | Liste d'e-mails promus administrateurs (séparés par des virgules) |
| `LISONS_VALIDATION_INSCRIPTION_OBLIGATOIRE` | `1` = les nouvelles inscriptions exigent une validation admin |

## 4. Test local

```bash
php -S 127.0.0.1:8080 dev-router.php     # http://127.0.0.1:8080
```

Premier compte créé sur une instance vide = **super-administrateur**.

## 5. Comptes et rôles (comme Plantons-plus)

- Rôles hiérarchiques : `contributeur` < `administrateur` < `super_administrateur`.
- Règles : un administrateur gère les contributeurs (promotion/rétrogradation) ; un super-administrateur gère aussi les administrateurs ; le rôle super-administrateur est intangible et non supprimable.
- Inscription avec mot de passe de 12 caractères min., hachage `password_hash`, session PHP locale, **CSRF** sur tous les POST.
- Mot de passe oublié : jeton hashé valable 1 h dans `data/reinitialisations.json` ; sans SMTP configuré, le lien est écrit dans `logs/app.log` (et affiché si `LISONS_DEBUG=1`).

## 6. Import Calibre

```bash
python3 scripts/calibre_to_json.py "/chemin/Bibliothèque calibre/metadata.db" data/import-calibre.json
```
Puis envoyer le fichier via la page **Import** (connecté). Les doublons d'ISBN de votre bibliothèque sont ignorés.

## 7. Sauvegarde

```bash
tar czf lisons-plus-backup.tar.gz data uploads
```
