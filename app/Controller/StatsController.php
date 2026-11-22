<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

/**
 * Statistiques de lecture de l'utilisateur connecté (calculées à la volée
 * depuis les fichiers JSON — aucun stockage supplémentaire).
 */
class StatsController
{
    public function index(Request $req): Response
    {
        $app = App::get();
        $user = $app->auth()->currentUser();
        $statuts = $app->config()['statuts'];

        $livres = $app->store('livres')->query(fn ($l) => ($l['user_id'] ?? '') === $user['id']);
        $fiches = $app->store('fiches')->query(fn ($f) => ($f['user_id'] ?? '') === $user['id']);

        // --- Par statut ---
        $parStatut = [];
        foreach ($statuts as $cle => $libelle) {
            $parStatut[$cle] = ['libelle' => $libelle, 'nb' => 0];
        }
        foreach ($livres as $l) {
            $s = $l['statut'] ?? 'a_lire';
            if (!isset($parStatut[$s])) {
                $parStatut[$s] = ['libelle' => $s, 'nb' => 0];
            }
            $parStatut[$s]['nb']++;
        }

        // --- Notes ---
        $notes = array_filter($livres, fn ($l) => !empty($l['note']));
        $noteMoyenne = $notes ? round(array_sum(array_column($notes, 'note')) / count($notes), 1) : null;
        $repartitionNotes = array_fill(1, 5, 0);
        foreach ($notes as $l) {
            $repartitionNotes[(int) $l['note']]++;
        }

        // --- Pages ---
        $pagesLues = 0;
        $pagesTotales = 0;
        foreach ($livres as $l) {
            if (!empty($l['pages'])) {
                $pagesTotales += (int) $l['pages'];
                if (($l['statut'] ?? '') === 'lu') {
                    $pagesLues += (int) $l['pages'];
                }
            }
        }

        // --- Par mois (12 derniers mois) : livres lus et pages lues ---
        $parMois = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts = strtotime("first day of -$i month");
            $cle = date('Y-m', $ts);
            $parMois[$cle] = ['libelle' => date('M Y', $ts), 'lus' => 0, 'pages' => 0, 'ajoutes' => 0];
        }
        foreach ($livres as $l) {
            // seuls les livres terminés (statut « lu ») comptent comme lus
            if (($l['statut'] ?? '') === 'lu' && !empty($l['date_lecture']) && preg_match('/^(\d{4}-\d{2})/', $l['date_lecture'], $m)) {
                if (isset($parMois[$m[1]])) {
                    $parMois[$m[1]]['lus']++;
                    $parMois[$m[1]]['pages'] += (int) ($l['pages'] ?? 0);
                }
            }
            if (!empty($l['date_ajout']) && preg_match('/^(\d{4}-\d{2})/', $l['date_ajout'], $m)) {
                if (isset($parMois[$m[1]])) {
                    $parMois[$m[1]]['ajoutes']++;
                }
            }
        }
        $maxLus = max([1, ...array_column($parMois, 'lus')]);
        $maxPages = max([1, ...array_column($parMois, 'pages')]);
        $maxNotes = max([1, ...array_values($repartitionNotes)]);

        // --- Top auteurs (livres lus d'abord, puis tous) ---
        $auteurs = [];
        foreach ($livres as $l) {
            $poids = ($l['statut'] ?? '') === 'lu' ? 2 : 1;
            foreach ($l['auteurs'] ?? [] as $a) {
                $auteurs[$a] = ($auteurs[$a] ?? 0) + $poids;
            }
        }
        arsort($auteurs);
        $topAuteurs = array_slice($auteurs, 0, 10, true);
        $maxAuteurs = max([1, ...array_values($topAuteurs)]);

        // --- Top étiquettes ---
        $tags = [];
        foreach ($livres as $l) {
            foreach ($l['tags'] ?? [] as $t) {
                $tags[$t] = ($tags[$t] ?? 0) + 1;
            }
        }
        arsort($tags);
        $topTags = array_slice($tags, 0, 10, true);
        $maxTags = max([1, ...array_values($topTags)]);

        // --- Fiches ---
        $nbFiches = count($fiches);
        $nbFichesPubliques = count(array_filter($fiches, fn ($f) => !empty($f['publique'])));

        // --- Donut SVG des statuts ---
        $total = max(1, count($livres));
        $couleurs = ['lu' => '#2e7d32', 'en_cours' => '#c98a12', 'a_lire' => '#2b4f8a', 'abandonne' => '#b3261e'];
        $segments = [];
        $angle = -90;
        foreach ($parStatut as $cle => $s) {
            if ($s['nb'] === 0) {
                continue;
            }
            $part = $s['nb'] / $total * 360;
            $segments[] = [
                'couleur' => $couleurs[$cle] ?? '#999',
                'libelle' => $s['libelle'],
                'nb' => $s['nb'],
                'angle' => $angle,
                'part' => $part,
            ];
            $angle += $part;
        }

        $html = $app->view()->render('stats', [
            'user' => $user,
            'total' => count($livres),
            'parStatut' => $parStatut,
            'segments' => $segments,
            'totalStatuts' => $total,
            'noteMoyenne' => $noteMoyenne,
            'repartitionNotes' => $repartitionNotes,
            'maxNotes' => $maxNotes,
            'pagesLues' => $pagesLues,
            'pagesTotales' => $pagesTotales,
            'parMois' => $parMois,
            'maxLus' => $maxLus,
            'maxPages' => $maxPages,
            'topAuteurs' => $topAuteurs,
            'maxAuteurs' => $maxAuteurs,
            'topTags' => $topTags,
            'maxTags' => $maxTags,
            'nbFiches' => $nbFiches,
            'nbFichesPubliques' => $nbFichesPubliques,
            'statuts' => $statuts,
            'page' => 'stats',
        ]);
        return Response::html($html);
    }

    /** Transforme un angle (degrés) en coordonnées sur un cercle de rayon r centré (cx,cy). */
    public static function polar(float $cx, float $cy, float $r, float $angleDeg): array
    {
        $rad = deg2rad($angleDeg);
        return [$cx + $r * cos($rad), $cy + $r * sin($rad)];
    }

    /** Génère le SVG d'un donut à partir des segments calculés. */
    public static function donutSvg(array $segments, int $taille = 180, int $epaisseur = 26): string
    {
        $cx = $cy = $taille / 2;
        $r = ($taille - $epaisseur) / 2;
        $parts = [];
        foreach ($segments as $s) {
            $a0 = $s['angle'];
            $a1 = $s['angle'] + $s['part'];
            if ($s['part'] >= 359.99) {
                // cercle complet
                $parts[] = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . $s['couleur'] . '" stroke-width="' . $epaisseur . '"/>';
                continue;
            }
            [$x0, $y0] = self::polar($cx, $cy, $r, $a0);
            [$x1, $y1] = self::polar($cx, $cy, $r, $a1);
            $grand = ($s['part'] > 180) ? 1 : 0;
            $parts[] = '<path d="M ' . $x0 . ' ' . $y0 . ' A ' . $r . ' ' . $r . ' 0 ' . $grand . ' 1 ' . $x1 . ' ' . $y1
                . '" fill="none" stroke="' . $s['couleur'] . '" stroke-width="' . $epaisseur . '" stroke-linecap="butt">'
                . '<title>' . htmlspecialchars($s['libelle'] . ' : ' . $s['nb'], ENT_QUOTES) . '</title></path>';
        }
        return '<svg viewBox="0 0 ' . $taille . ' ' . $taille . '" width="' . $taille . '" height="' . $taille . '" role="img">'
            . implode('', $parts)
            . '<text x="' . $cx . '" y="' . ($cy - 2) . '" text-anchor="middle" font-size="22" font-weight="700" fill="#2b2620">' . array_sum(array_column($segments, 'nb')) . '</text>'
            . '<text x="' . $cx . '" y="' . ($cy + 16) . '" text-anchor="middle" font-size="11" fill="#6b6257">livres</text>'
            . '</svg>';
    }
}
