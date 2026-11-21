# Installation de Lisons-plus

## 1. Prérequis

- PHP 8.2+ avec les extensions `json`, `mbstring`, `curl`, `fileinfo`.
- Un serveur web (Apache ou nginx) pointant sur le dossier du projet.
- Droits d'écriture pour le processus PHP sur `data/`, `logs/`, `uploads/` :
  ```bash
  chown -R www-data:www-data data logs uploads   # adapter l'utilisateur selon l'hébergement
  chmod -R u+rwX data logs uploads
  ```

## 2. Déploiement

Copier le contenu du dépôt sur l'hébergement, par exemple dans `/var/www/lisons-plus`.

### Apache

Créer `public/` comme racine web **ou** utiliser la réécriture fournie. `.htaccess` à la racine :

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Si le dossier racine est le dossier du projet, les fichiers statiques (`/public/...`, `/uploads/...`) sont servis tels quels et tout le reste passe par `index.php`.

### nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(data|logs|config|app)/ {
    deny all;
}
```

> Important : bloquer l'accès direct à `data/`, `logs/`, `config/`, `app/` (les données sont en JSON lisible).

## 3. Configuration

Centralisée dans `config/config.php`. Les valeurs sensibles peuvent venir de variables d'environnement :

| Variable | Rôle |
|---|---|
| `LISONS_APP_NAME` | Nom affiché de l'application |
| `LISONS_BASE_URL` | URL publique, ex. `https://livres.example.tld` (détection auto sinon) |
| `LISONS_ADMIN_EMAIL` | Contact utilisé dans l'User-Agent des appels Open Library |
| `LISONS_DEBUG` | `1` pour afficher les erreurs détaillées (dev uniquement) |

## 4. Développement local

```bash
php -S 127.0.0.1:8080 dev-router.php
```

## 5. Sauvegarde

```bash
tar czf lisons-plus-backup.tar.gz data uploads
```

## 6. Import Calibre

```bash
python3 scripts/calibre_to_json.py "/chemin/Bibliothèque calibre/metadata.db" data/import-calibre.json
```
Puis envoyer `data/import-calibre.json` via la page **Import** de l'application.
