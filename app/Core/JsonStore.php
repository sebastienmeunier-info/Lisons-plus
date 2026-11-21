<?php
namespace App\Core;

/**
 * Stockage clé => enregistrement en fichier JSON, avec verrouillage fichier.
 * Convient à un inventaire personnel (mono-utilisateur ou peu d'écritures).
 */
class JsonStore
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!file_exists($this->file)) {
            file_put_contents($this->file, "{}\n", LOCK_EX);
        }
    }

    /** @return array<string, array> */
    public function all(): array
    {
        $raw = file_get_contents($this->file);
        $data = json_decode($raw ?: '{}', true);
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
        return $this->query(function (array $rec) use ($term, $fields): bool {
            foreach ($fields as $f) {
                $v = $rec[$f] ?? '';
                if (is_array($v)) {
                    $v = implode(' ', $v);
                }
                if (\mb_str_contains(\mb_strtolower((string) $v), $term)) {
                    return true;
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
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
