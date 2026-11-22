<?php
namespace App\Service;

/**
 * Interrogation de l'API Open Library pour obtenir les métadonnées d'un ISBN
 * (édition + work + auteurs + couverture).
 */
class IsbnService
{
    public function __construct(private array $config)
    {
    }

    /**
     * Normalise un ISBN saisi : retire tirets/espaces, valide le checksum.
     * Retourne l'ISBN-13 canonique ou null si invalide.
     */
    public function normaliser(string $saisie): ?string
    {
        $digits = strtoupper(preg_replace('/[^0-9Xx]/', '', $saisie) ?? '');
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 10) {
            if (!$this->checksum10($digits)) {
                return null;
            }
            return $this->vers13($digits);
        }
        if (strlen($digits) === 13) {
            if (!$this->checksum13($digits)) {
                return null;
            }
            return $digits;
        }
        return null;
    }

    private function checksum10(string $isbn): bool
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (10 - $i) * (int) $isbn[$i];
        }
        $check = $isbn[9] === 'X' ? 10 : (int) $isbn[9];
        return ($sum + $check) % 11 === 0;
    }

    private function checksum13(string $isbn): bool
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $isbn[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int) $isbn[12];
    }

    private function vers13(string $isbn10): string
    {
        $core = '978' . substr($isbn10, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $core[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return $core . ((10 - ($sum % 10)) % 10);
    }

    /**
     * Métadonnées pour un ISBN saisi (10 ou 13).
     * Rapide : 2 appels HTTP max (édition + search qui fournit auteurs/sujets
     * directement, sans appeler chaque fiche auteur), et cache disque 7 jours.
     * @return array|null tableau normalisé ou null si introuvable/invalide
     */
    public function lookup(string $saisie): ?array
    {
        $isbn = $this->normaliser($saisie);
        if ($isbn === null) {
            return null;
        }
        $cache = $this->cacheLire($isbn);
        if ($cache !== null) {
            return $cache;
        }
        $base = rtrim($this->config['isbn']['openlibrary_base'], '/');
        // UN SEUL appel en priorité : search.json renvoie titre, auteurs,
        // éditeur, année, pages, sujets, première phrase et couverture.
        $searchFields = 'title,subtitle,author_name,first_publish_year,publisher,cover_i,number_of_pages_median,subject,first_sentence';
        $search = $this->httpGetJson($base . '/search.json?isbn=' . $isbn . '&fields=' . $searchFields . '&limit=1');
        $doc = is_array($search) ? ($search['docs'][0] ?? null) : null;

        if (is_array($doc) && !empty($doc['title'])) {
            $meta = [
                'isbn' => $isbn,
                'titre' => $doc['title'] ?? '',
                'sous_titre' => $doc['subtitle'] ?? null,
                'auteurs' => $doc['author_name'] ?? [],
                'editeur' => $doc['publisher'][0] ?? null,
                'annee' => $doc['first_publish_year'] ?? null,
                'pages' => $doc['number_of_pages_median'] ?? null,
                'description' => $doc['first_sentence'][0] ?? null,
                'sujets' => array_slice($doc['subject'] ?? [], 0, 10),
                'couverture_id' => $doc['cover_i'] ?? null,
                'source' => 'openlibrary-search',
            ];
        } else {
            // Fallback : fiche édition /isbn/ (données riches mais sans auteurs)
            $edition = $this->httpGetJson($base . '/isbn/' . $isbn . '.json');
            if (!is_array($edition) || isset($edition['error'])) {
                return null;
            }
            $meta = [
                'isbn' => $isbn,
                'isbn_10' => $edition['isbn_10'][0] ?? null,
                'titre' => $edition['title'] ?? '',
                'sous_titre' => $edition['subtitle'] ?? null,
                'auteurs' => [],
                'editeur' => $edition['publishers'][0] ?? null,
                'lieu' => $edition['publish_places'][0] ?? null,
                'annee' => $this->extraireAnnee($edition['publish_date'] ?? null),
                'pages' => $edition['number_of_pages'] ?? null,
                'format' => $edition['physical_format'] ?? null,
                'langue' => $this->extraireLangue($edition['languages'][0] ?? null),
                'serie' => $edition['series'][0] ?? null,
                'couverture_id' => $edition['covers'][0] ?? null,
                'description' => null,
                'sujets' => [],
                'source' => 'openlibrary',
            ];
        }
        $meta = $this->normaliserMeta($meta);
        $this->cacheEcrire($isbn, $meta);
        return $meta;
    }

    /** Cache disque (7 jours) des métadonnées par ISBN, dans paths.data/cache. */
    private function cacheFile(string $isbn): string
    {
        return rtrim((string) ($this->config['paths']['data'] ?? sys_get_temp_dir()), '/') . '/cache/isbn_' . $isbn . '.json';
    }

    private function cacheLire(string $isbn): ?array
    {
        try {
            $f = $this->cacheFile($isbn);
            if (is_file($f) && filemtime($f) > time() - 7 * 86400) {
                $data = json_decode((string) file_get_contents($f), true);
                // version du cache : on ignore les entrées écrites par une vieille version
                if (is_array($data) && ($data['isbn'] ?? '') === $isbn && (int) ($data['couv_cache_v'] ?? 0) >= 2) {
                    return $data;
                }
            }
        } catch (\Throwable $e) {
            // le cache ne doit jamais faire échouer la recherche
        }
        return null;
    }

    private function cacheEcrire(string $isbn, array $meta): void
    {
        try {
            $meta['couv_cache_v'] = 2;
            $f = $this->cacheFile($isbn);
            if (!is_dir(dirname($f))) {
                mkdir(dirname($f), 0775, true);
            }
            file_put_contents($f, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        } catch (\Throwable $e) {
        }
    }

    /** URL de couverture Open Library (taille L) à partir de l'id de couverture. */
    public function couvertureUrl(?int $coverId, string $size = 'L'): ?string
    {
        if (!$coverId) {
            return null;
        }
        return rtrim($this->config['isbn']['covers_base'], '/') . '/' . $coverId . '-' . $size . '.jpg';
    }

    /**
     * URL de couverture directement par ISBN (CDN rapide, ?default=false → 404
     * si aucune couverture, au lieu d'une image vide).
     */
    public function couvertureUrlIsbn(string $isbn, string $size = 'L'): string
    {
        return rtrim($this->config['isbn']['covers_base'], '/') . '/b/isbn/' . $isbn . '-' . $size . '.jpg?default=false';
    }

    /**
     * Répare la couverture d'un livre : télécharge le fichier local
     * (CDN par ISBN d'abord, sinon id de couverture des métadonnées).
     * Met à jour $livre (couverture, couverture_url, isbn) et sauvegarde si $save.
     * @return string|null message de succès, ou null si rien n'a pu être fait
     */
    public function reparer(array &$livre, string $isbnSaisi = '', bool $save = true, ?array $meta = null): ?string
    {
        $app = \App\Core\App::get();
        $isbn = \App\Model\Livre::nettoyerIsbn($isbnSaisi !== '' ? $isbnSaisi : (string) ($livre['isbn'] ?? ''));
        if ($isbn === '') {
            return null;
        }
        if (($livre['isbn'] ?? '') === '') {
            $livre['isbn'] = $isbn;
        }
        // 1) Tentative RAPIDE : CDN des couvertures directement par ISBN
        //    (1 appel, 404 propre si aucune couverture — ?default=false)
        $cdnUrl = $this->couvertureUrlIsbn($isbn);
        try {
            $path = $this->telechargerCouverture($cdnUrl);
        } catch (\Throwable $e) {
            $app->log('reparer CDN : ' . $e->getMessage());
            $path = null;
        }
        if ($path) {
            $livre['couverture'] = $path;
            $livre['couverture_url'] = str_replace('?default=false', '', $cdnUrl);
            if ($save && !empty($livre['id'])) {
                $app->store('livres')->save($livre['id'], $livre);
            }
            return 'Couverture récupérée.';
        }
        // 2) Repli : métadonnées (cachées 7 j) + couverture par id
        if ($meta === null) {
            try {
                $meta = $this->lookup($isbn);
            } catch (\Throwable $e) {
                $app->log('reparer lookup ' . $isbn . ' : ' . $e->getMessage());
                return null;
            }
        }
        if (!is_array($meta)) {
            return null;
        }
        $coverUrl = !empty($meta['couverture_id'])
            ? $this->couvertureUrl((int) $meta['couverture_id'])
            : null;
        if ($coverUrl === null) {
            return null; // métadonnées trouvées mais vraiment aucune couverture
        }
        if (empty($livre['couverture_url'])) {
            $livre['couverture_url'] = $coverUrl;
        }
        try {
            $path = $this->telechargerCouverture($coverUrl);
            if ($path) {
                $livre['couverture'] = $path;
            }
        } catch (\Throwable $e) {
            $app->log('reparer download : ' . $e->getMessage());
        }
        if ($save && !empty($livre['id'])) {
            $app->store('livres')->save($livre['id'], $livre);
        }
        return $path
            ? 'Couverture récupérée pour « ' . ($meta['titre'] ?? $isbn) . ' ».'
            : 'Métadonnées trouvées, couverture affichée via le lien distant.';
    }

    /**
     * Télécharge une couverture distante vers uploads/covers/.
     * @return string|null chemin relatif (uploads/covers/xxx.jpg) ou null
     */
    public function telechargerCouverture(string $urlDest): ?string
    {
        $cfg = $this->config['upload'];
        $dir = $this->config['paths']['uploads'] . '/' . $cfg['covers_dir'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cover');
        $ok = $this->httpDownload($urlDest, $tmp);
        if (!$ok || filesize($tmp) < 2000) { // moins de 2 ko = probablement une image d'erreur
            @unlink($tmp);
            return null;
        }
        $ext = 'jpg';
        $dest = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
        rename($tmp, $dest);
        // tempnam() crée en 0600 : illisible pour le serveur web (nginx/FPM
        // différents) → 403. On rend le fichier lisible par tous.
        @chmod($dest, 0644);
        @chmod($dir, 0775);
        return 'uploads/' . $cfg['covers_dir'] . '/' . basename($dest);
    }

    private function extraireAnnee(?string $publishDate): ?int
    {
        if ($publishDate && preg_match('/(\d{4})/', $publishDate, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function extraireLangue(?array $lang): ?string
    {
        if ($lang && isset($lang['key'])) {
            return str_replace('/languages/', '', $lang['key']);
        }
        return null;
    }

    private function normaliserMeta(array $meta): array
    {
        if (isset($meta['annee']) && $meta['annee']) {
            $meta['annee'] = (int) $meta['annee'];
        }
        if (isset($meta['pages']) && $meta['pages']) {
            $meta['pages'] = (int) $meta['pages'];
        }
        $meta['auteurs'] = array_values(array_filter(array_map('trim', $meta['auteurs'] ?? [])));
        if (!empty($meta['sous_titre'])) {
            $meta['titre'] = trim($meta['titre'] . ' : ' . $meta['sous_titre']);
            unset($meta['sous_titre']);
        }
        return $meta;
    }

    private function httpGetJson(string $url): ?array
    {
        $raw = $this->httpGet($url);
        if ($raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * GET HTTP résilient : ext-curl, sinon streams, sinon fsockopen
     * (l'hébergement cible peut être un PHP minimal sans ext-curl).
     */
    private function httpGet(string $url): ?string
    {
        return \App\Core\Http::get(
            $url,
            (int) ($this->config['isbn']['timeout'] ?? 20),
            (string) ($this->config['isbn']['user_agent'] ?? 'Lisons-plus/1.0')
        );
    }

    private function httpDownload(string $url, string $dest): bool
    {
        return \App\Core\Http::download(
            $url,
            $dest,
            (int) ($this->config['isbn']['timeout'] ?? 20) + 10,
            (string) ($this->config['isbn']['user_agent'] ?? 'Lisons-plus/1.0')
        );
    }
}
