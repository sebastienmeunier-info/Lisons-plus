<?php
namespace App\Model;

/**
 * Structure d'un livre de l'inventaire + validation des champs personnels.
 */
class Livre
{
    public const CHAMPS_META = ['isbn', 'titre', 'auteurs', 'editeur', 'lieu', 'annee', 'pages', 'format', 'langue', 'serie', 'description', 'sujets', 'couverture', 'couverture_url', 'source'];

    /**
     * Construit un enregistrement livre à partir des données du formulaire.
     * @param array $meta métadonnées (éventuellement issues de l'ISBN)
     */
    public static function depuisFormulaire(array $post, array $statuts, ?array $meta = null): array
    {
        $meta = $meta ?? [];
        $statut = (string) ($post['statut'] ?? 'a_lire');
        if (!isset($statuts[$statut])) {
            $statut = 'a_lire';
        }
        $note = (int) ($post['note'] ?? 0);
        $note = max(0, min(5, $note));

        $livre = [
            // Métadonnées (ISBN ou saisie manuelle)
            'isbn' => self::nettoyerIsbn($post['isbn'] ?? ($meta['isbn'] ?? '')),
            'titre' => trim((string) ($post['titre'] ?? ($meta['titre'] ?? ''))),
            'auteurs' => self::liste($post['auteurs'] ?? ($meta['auteurs'] ?? [])),
            'editeur' => trim((string) ($post['editeur'] ?? ($meta['editeur'] ?? ''))),
            'lieu' => trim((string) ($post['lieu'] ?? ($meta['lieu'] ?? ''))),
            'annee' => self::annee($post['annee'] ?? ($meta['annee'] ?? null)),
            'pages' => self::entier($post['pages'] ?? ($meta['pages'] ?? null)),
            'format' => trim((string) ($post['format'] ?? ($meta['format'] ?? ''))),
            'langue' => trim((string) ($post['langue'] ?? ($meta['langue'] ?? ''))),
            'serie' => trim((string) ($post['serie'] ?? ($meta['serie'] ?? ''))),
            'description' => trim((string) ($post['description'] ?? ($meta['description'] ?? ''))),
            'sujets' => self::liste($post['sujets'] ?? ($meta['sujets'] ?? [])),
            'couverture' => trim((string) ($post['couverture'] ?? ($meta['couverture'] ?? ''))),
            'couverture_url' => trim((string) ($meta['couverture_url'] ?? '')),
            'source' => $meta['source'] ?? ($post['isbn'] ? 'manuel+isbn' : 'manuel'),
            // Champs personnels
            'statut' => $statut,
            'note' => $note,
            'commentaire' => trim((string) ($post['commentaire'] ?? '')),
            'tags' => self::liste($post['tags'] ?? []),
            'date_lecture' => self::date($post['date_lecture'] ?? null),
            'date_ajout' => date('Y-m-d'),
            'date_modif' => date('Y-m-d H:i:s'),
        ];
        return $livre;
    }

    public static function nettoyerIsbn(string $isbn): string
    {
        return strtoupper(preg_replace('/[^0-9Xx]/', '', trim($isbn)) ?? '');
    }

    public static function liste(mixed $v): array
    {
        if (is_array($v)) {
            $parts = $v;
        } else {
            $parts = preg_split('/[;,]/u', (string) $v) ?: [];
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    private static function annee(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (preg_match('/(\d{4})/', (string) $v, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private static function entier(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (int) preg_replace('/[^0-9]/', '', (string) $v) ?: null;
    }

    private static function date(mixed $v): ?string
    {
        if (!$v || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)) {
            return null;
        }
        return (string) $v;
    }

    public static function valide(array $livre): array
    {
        $erreurs = [];
        if (trim($livre['titre'] ?? '') === '') {
            $erreurs[] = 'Le titre est obligatoire.';
        }
        if (($livre['auteurs'] ?? []) === []) {
            $erreurs[] = 'Indique au moins un auteur.';
        }
        return $erreurs;
    }

    /** Recherche plein texte sur les champs utiles. */
    public static function champsRecherche(): array
    {
        return ['titre', 'auteurs', 'editeur', 'serie', 'commentaire', 'tags', 'isbn', 'description', 'sujets'];
    }
}
