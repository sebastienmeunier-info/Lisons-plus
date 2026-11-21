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
     * Métadonnées complètes pour un ISBN saisi (10 ou 13).
     * @return array|null tableau normalisé ou null si introuvable/invalide
     */
    public function lookup(string $saisie): ?array
    {
        $isbn = $this->normaliser($saisie);
        if ($isbn === null) {
            return null;
        }
        $base = rtrim($this->config['isbn']['openlibrary_base'], '/');
        $edition = $this->httpGetJson($base . '/isbn/' . $isbn . '.json');
        if (!is_array($edition) || isset($edition['error'])) {
            // Fallback : recherche par ISBN (certaines éditions n'ont pas de fiche /isbn/)
            $search = $this->httpGetJson($base . '/search.json?isbn=' . $isbn . '&fields=title,author_name,first_publish_year,publisher,cover_i,number_of_pages_median,subject');
            if (!is_array($search) || empty($search['docs'][0])) {
                return null;
            }
            $doc = $search['docs'][0];
            return $this->normaliserMeta([
                'isbn' => $isbn,
                'titre' => $doc['title'] ?? '',
                'auteurs' => $doc['author_name'] ?? [],
                'annee' => $doc['first_publish_year'] ?? null,
                'editeur' => $doc['publisher'][0] ?? null,
                'pages' => $doc['number_of_pages_median'] ?? null,
                'sujets' => array_slice($doc['subject'] ?? [], 0, 10),
                'couverture_id' => $doc['cover_i'] ?? null,
                'source' => 'openlibrary-search',
            ]);
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

        // Fiche "work" : description + auteurs + sujets
        $workKey = $edition['works'][0]['key'] ?? null;
        if ($workKey) {
            $work = $this->httpGetJson($base . $workKey . '.json');
            if (is_array($work)) {
                $desc = $work['description'] ?? null;
                if (is_array($desc)) {
                    $desc = $desc['value'] ?? null;
                }
                $meta['description'] = $desc;
                $meta['sujets'] = array_slice($work['subjects'] ?? [], 0, 10);
                $auteurs = [];
                foreach (array_slice($work['authors'] ?? [], 0, 5) as $a) {
                    $authorKey = $a['author']['key'] ?? null;
                    if ($authorKey) {
                        $author = $this->httpGetJson($base . $authorKey . '.json');
                        if (is_array($author) && !empty($author['name'])) {
                            $auteurs[] = $author['name'];
                        }
                    }
                }
                $meta['auteurs'] = $auteurs;
            }
        }
        // Fallback auteurs via la recherche si la work n'a rien donné
        if ($meta['auteurs'] === []) {
            $search = $this->httpGetJson($base . '/search.json?isbn=' . $isbn . '&fields=author_name,first_publish_year');
            if (is_array($search) && !empty($search['docs'][0]['author_name'])) {
                $meta['auteurs'] = $search['docs'][0]['author_name'];
            }
            if (empty($meta['annee']) && !empty($search['docs'][0]['first_publish_year'])) {
                $meta['annee'] = $search['docs'][0]['first_publish_year'];
            }
        }
        return $this->normaliserMeta($meta);
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

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->config['isbn']['timeout'],
            CURLOPT_USERAGENT => $this->config['isbn']['user_agent'],
            CURLOPT_ACCEPT_ENCODING => '',
        ]);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($out === false || $code >= 400) {
            return null;
        }
        return (string) $out;
    }

    private function httpDownload(string $url, string $dest): bool
    {
        $ch = curl_init($url);
        $fp = fopen($dest, 'wb');
        if (!$fp) {
            curl_close($ch);
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->config['isbn']['timeout'],
            CURLOPT_USERAGENT => $this->config['isbn']['user_agent'],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        return $code < 400;
    }
}
