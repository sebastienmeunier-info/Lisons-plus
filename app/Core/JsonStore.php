<?php
namespace App\Core;

/**
 * Stockage clé => enregistrement en fichier JSON, avec verrouillage fichier.
 * Convient à un inventaire personnel (mono-utilisateur ou peu d'écritures).
 */
class JsonStore
{
    /**
     * Garde d'exécution : si le fichier tombe dans un webroot servi par un
     * serveur qui exécute PHP (.php), toute lecture directe ne renvoie rien.
     */
    private const GUARD = '<?php exit; ?>';

    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Protection Apache (inutile sous nginx, sans effet nuisible)
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        if (!file_exists($this->file)) {
            // Migration des anciens fichiers *.json non gardés (exposés en clair)
            $legacy = (string) preg_replace('/\.php$/i', '.json', $file);
            if ($legacy !== $file && is_file($legacy)) {
                $raw = (string) file_get_contents($legacy);
                file_put_contents($this->file, self::GUARD . "\n" . $raw, LOCK_EX);
                @unlink($legacy); // supprime le fichier lisible par le serveur web
            } else {
                file_put_contents($this->file, self::GUARD . "\n{}\n", LOCK_EX);
            }
        }
    }

    /** @return array<string, array> */
    public function all(): array
    {
        $raw = (string) file_get_contents($this->file);
        if (str_starts_with($raw, '<?php')) {
            $nl = strpos($raw, "\n");
            $raw = $nl === false ? '' : substr($raw, $nl + 1);
        }
        $data = json_decode(trim($raw) ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    public function find(string $id): ?array
    {
        $all = $this->all();
        return $all[$id] ?? null;
    }

    public function save(string $id, array $record): array
    {
        $all = $this->all();
        $record['id'] = $id;
        $all[$id] = $record;
        $this->write($all);
        return $record;
    }

    public function delete(string $id): bool
    {
        $all = $this->all();
        if (!isset($all[$id])) {
            return false;
        }
        unset($all[$id]);
        $this->write($all);
        return true;
    }

    /** @return array<string, array> enregistrements filtrés, triés par clé décroissante si demandé */
    public function query(?callable $filter = null, bool $desc = true): array
    {
        $all = $this->all();
        if ($filter) {
            $all = array_filter($all, $filter, ARRAY_FILTER_USE_BOTH);
        }
        $keys = array_keys($all);
        if ($desc) {
            rsort($keys);
        } else {
            sort($keys);
        }
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $all[$k];
        }
        return $out;
    }

    /** Recherche plein texte sur un ensemble de champs. */
    public function search(string $term, array $fields): array
    {
        $term = \mb_strtolower(trim($term));
        if ($term === '') {
            return $this->all();
        }
        // Un terme qui ressemble à un ISBN (≥ 9 chiffres/X) est comparé aussi
        // sans ses séparateurs : « 978-2-07-292752-2 » doit retrouver « 9782072927522 ».
        $termIsbn = strtoupper((string) preg_replace('/[^0-9Xx]/', '', $term));
        $termEstIsbn = $termIsbn !== '' && \strlen($termIsbn) >= 9;
        return $this->query(function (array $rec) use ($term, $fields, $termIsbn, $termEstIsbn): bool {
            foreach ($fields as $f) {
                $v = $rec[$f] ?? '';
                if (is_array($v)) {
                    $v = implode(' ', $v);
                }
                $vs = \mb_strtolower((string) $v);
                if (\mb_str_contains($vs, $term)) {
                    return true;
                }
                if ($f === 'isbn' && $termEstIsbn) {
                    $vIsbn = strtoupper((string) preg_replace('/[^0-9Xx]/', '', (string) $v));
                    if ($vIsbn !== '' && (str_contains($vIsbn, $termIsbn) || str_contains($termIsbn, $vIsbn))) {
                        return true;
                    }
                }
            }
            return false;
        });
    }

    private function write(array $data): void
    {
        $fp = fopen($this->file, 'c+');
        if (!$fp) {
            throw new \RuntimeException('Impossible d\'ouvrir ' . $this->file);
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, self::GUARD . "\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
