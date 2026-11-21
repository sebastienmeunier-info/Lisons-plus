<?php
namespace App\Model;

/**
 * Fiche de lecture personnelle, partageable ou non.
 */
class Fiche
{
    public static function depuisFormulaire(array $post, ?string $livreId): array
    {
        $note = (int) ($post['note'] ?? 0);
        return [
            'livre_id' => $livreId,
            'titre' => trim((string) ($post['titre'] ?? '')),
            'contenu' => trim((string) ($post['contenu'] ?? '')),
            'citations' => self::citations($post['citations'] ?? ''),
            'note' => max(0, min(5, $note)),
            'publique' => !empty($post['publique']),
            'token' => null, // renseigné à la publication
            'date_creation' => date('Y-m-d H:i:s'),
            'date_modif' => date('Y-m-d H:i:s'),
        ];
    }

    /** Blocs de citations : un par ligne. */
    public static function citations(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '') {
                $out[] = $ligne;
            }
        }
        return $out;
    }

    public static function valide(array $fiche): array
    {
        $erreurs = [];
        if (trim($fiche['titre'] ?? '') === '') {
            $erreurs[] = 'Le titre de la fiche est obligatoire.';
        }
        if (trim($fiche['contenu'] ?? '') === '') {
            $erreurs[] = 'Le contenu de la fiche est obligatoire.';
        }
        return $erreurs;
    }
}
