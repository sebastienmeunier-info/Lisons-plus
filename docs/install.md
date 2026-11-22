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

**À la racine du domaine** :

```nginx
root /var/www/html;
index index.php;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(data|logs|config|app)/ { deny all; }
```

**En sous-dossier** (ex. `https://apps.example.tld/lisons/`) — Lisons-plus détecte
automatiquement le préfixe, mais la conf nginx doit router vers le bon `index.php` :

```nginx
location /lisons/ {
    alias /var/www/lisons/;              # dossier contenant index.php (adapter)
    try_files $uri $uri/ /lisons/index.php?$query_string;
}
# OBLIGATOIRE : bloquer l'accès direct aux données (sinon /lisons/data/livres.json est public !)
location ~ ^/lisons/(data|logs|config|app)/ { deny all; }
```

> Sans réécriture (`try_files … /index.php`), seules les URLs « réelles » (fichiers) répondent : activez-la, ou utilisez le **mode query-string** ci-dessous.

### Option B — sans aucune conf serveur (déploiement FTP seul)

Si nginx/Apache ne fait pas de réécriture vers `index.php` (les URLs propres
`/lisons/connexion` renvoient une 404 du serveur web), activez le **mode query-string** :
déposer un fichier `config/url_mode` contenant `qs` (ou variable `LISONS_URL_MODE=qs`).
Toutes les URLs deviennent alors `/lisons/?/connexion`, `/lisons/?/livre/…` — 100 %
fonctionnel sans toucher à la conf serveur. Les recherches publiques restent
`/lisons/?isbn=…`, `/lisons/?auteur=…` (inchangé).

### Protéger les données (URGENT si data/ est dans le webroot)

`data/` et `logs/` ne doivent **jamais** être servis en HTTP (ils contiennent livres,
utilisateurs + hash de mots de passe, sessions). Sans accès à la conf serveur :

1. Créer `lisons-data/` et `lisons-logs/` **à côté** du dossier de l'app (hors DocumentRoot,
   ex. `../lisons-data` si l'app est dans `/lisons/`) et les rendre writables par PHP (chmod 777).
2. Y déplacer les `*.json` de `data/`, le dossier `sessions/`, et le contenu de `logs/`.
3. L'application les détecte **automatiquement** (voir `config/config.php`) — aucun réglage.

Vérifier ensuite : `/lisons/data/livres.json` et `/lisons/logs/app.log` doivent répondre 403/404.
Si accès à la conf nginx : `location ~ ^/lisons/(data|logs|config|app)/ { deny all; }`.

### Protéger les données (recommandé en production)

`data/` et `logs/` contiennent des données personnelles. Deux niveaux de protection :

1. **Règle serveur** `deny all` ci-dessus (nginx) / `RedirectMatch 404` du `.htaccess` (Apache).
2. **Déplacer `data/` et `logs/` hors du webroot** via les variables d'environnement
   `LISONS_DATA_DIR` et `LISONS_LOGS_DIR` (définies dans le vhost Apache `SetEnv`,
   le pool PHP-FPM `env[...]`, ou le panel de l'hébergeur), par ex. :
   ```
   LISONS_DATA_DIR=/home/seb/lisons-data
   LISONS_LOGS_DIR=/home/seb/lisons-logs
   ```
   Les sessions suivront (`$LISONS_DATA_DIR/sessions`). `uploads/` (couvertures) reste
   dans le webroot, il est destiné à être public.

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

## 4. Déploiement par FTP/SFTP (auto-hébergement, sans accès à la conf serveur)

Si le site est servi en sous-dossier (ex. `https://apps.example.tld/lisons/`) et que l'accès
se fait uniquement par FTP/SFTP (FileZilla, port 21 FTP ou 22 SFTP) :

1. **Uploader** le contenu du dépôt vers le dossier servi (ex. `/lisons/`).
2. **Exclure du transfert** : `.git/`, `data/`, `logs/`, `uploads/covers/` — ne pas écraser
   les données, sessions et couvertures déjà présentes sur le serveur.
3. **Fichiers cachés** : menu *Serveur → Forcer l'affichage des fichiers cachés* pour
   transférer `.htaccess` (utile sous Apache, inoffensif sous nginx).
4. **Droits** : `data/`, `logs/`, `uploads/` doivent être writables par PHP
   (clic droit → droits du fichier → 777 si nécessaire).
5. **Protéger les données sans toucher à nginx** : créer un dossier `lisons-data/`
   **à côté** du dossier de l'app (hors du DocumentRoot, ex. `../lisons-data/`),
   y déplacer les `*.json` de `data/` et le dossier `sessions/`.
   L'application le détecte **automatiquement** (voir `config/config.php`) :
   `data/` n'est alors plus accessible en HTTP. Alternative si accès à la conf :
   `location ~ ^/lisons/(data|logs|config|app)/ { deny all; }`.
6. Vider `data/sessions/` côté serveur en cas de problème de session.

## 5. Test local

```bash
php -S 127.0.0.1:8080 dev-router.php     # http://127.0.0.1:8080
```

Premier compte créé sur une instance vide = **super-administrateur**.

## 6. Comptes et rôles (comme Plantons-plus)

- Rôles hiérarchiques : `contributeur` < `administrateur` < `super_administrateur`.
- Règles : un administrateur gère les contributeurs (promotion/rétrogradation) ; un super-administrateur gère aussi les administrateurs ; le rôle super-administrateur est intangible et non supprimable.
- Inscription avec mot de passe de 12 caractères min., hachage `password_hash`, session PHP locale, **CSRF** sur tous les POST.
- Mot de passe oublié : jeton hashé valable 1 h dans `data/reinitialisations.json` ; sans SMTP configuré, le lien est écrit dans `logs/app.log` (et affiché si `LISONS_DEBUG=1`).

## 7. Import Calibre

```bash
python3 scripts/calibre_to_json.py "/chemin/Bibliothèque calibre/metadata.db" data/import-calibre.json
```
Puis envoyer le fichier via la page **Import** (connecté). Les doublons d'ISBN de votre bibliothèque sont ignorés.

## 8. Sauvegarde

```bash
tar czf lisons-plus-backup.tar.gz data uploads
```
