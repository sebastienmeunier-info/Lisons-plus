<?php
namespace App\Controller;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Model\Livre;

class ImportController
{
    /** GET /import : formulaire d'import (JSON export Calibre ou CSV). */
    public function form(Request $req): Response
    {
        $app = App::get();
        $html = $app->view()->render('import/form', [
            'page' => 'import',
            'message' => $req->input('message', ''),
            'erreurs' => [],
        ]);
        return Response::html($html);
    }

    /** POST /import : traite le fichier envoyé. */
    public function traiter(Request $req): Response
    {
        $app = App::get();
        $file = $req->file('fichier');
        $erreurs = [];
        $importes = 0;
        $doublons = 0;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = 'Aucun fichier reçu ou envoi interrompu.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $contenu = file_get_contents($file['tmp_name']);
            $livres = match ($ext) {
                'json' => $this->depuisJson($contenu, $erreurs),
                'csv' => $this->depuisCsv($contenu, $erreurs),
                default => null,
            };
            if ($livres === null) {
                if (!$erreurs) {
                    $erreurs[] = 'Format non supporté : fournir un .json (export Calibre via scripts/calibre_to_json.py) ou un .csv.';
                }
            } else {
                $store = $app->store('livres');
                $existants = $store->all();
                // Index des ISBN existants pour éviter les doublons
                $isbnExistants = [];
                foreach ($existants as $l) {
                    if (!empty($l['isbn'])) {
                        $isbnExistants[$l['isbn']] = true;
                    }
                }
                foreach ($livres as $l) {
                    $l = Livre::depuisFormulaire($l, $app->config()['statuts'], $l);
                    if (Livre::valide($l) !== []) {
                        continue;
                    }
                    if (!empty($l['isbn']) && isset($isbnExistants[$l['isbn']])) {
                        $doublons++;
                        continue;
                    }
                    $id = uuid_short();
                    $l['id'] = $id;
                    if (!empty($l['isbn'])) {
                        $isbnExistants[$l['isbn']] = true;
                    }
                    $store->save($id, $l);
                    $importes++;
                }
            }
        }

        if ($erreurs) {
            $html = $app->view()->render('import/form', ['page' => 'import', 'erreurs' => $erreurs]);
            return Response::html($html, 422);
        }
        return Response::redirect(url('/import?message=' . urlencode("Import terminé : $importes livre(s) ajouté(s), $doublons doublon(s) ignoré(s).")));
    }

    /** Export JSON produit par scripts/calibre_to_json.py : {"livres": [...]}. */
    private function depuisJson(string $contenu, array &$erreurs): ?array
    {
        $data = json_decode($contenu, true);
        if (!is_array($data)) {
            $erreurs[] = 'JSON illisible.';
            return null;
        }
        return $data['livres'] ?? $data;
    }

    /** CSV : en-têtes attendus (export Calibre) : title, authors, isbn, publisher, pubdate, series, tags, rating, comments. */
    private function depuisCsv(string $contenu, array &$erreurs): ?array
    {
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($contenu)) ?: []);
        if (count($rows) < 2) {
            $erreurs[] = 'CSV vide.';
            return null;
        }
        $header = array_map(fn ($h) => \mb_strtolower(trim($h)), array_shift($rows));
        $map = [
            'title' => 'titre', 'authors' => 'auteurs', 'isbn' => 'isbn', 'publisher' => 'editeur',
            'pubdate' => 'annee', 'series' => 'serie', 'tags' => 'tags', 'rating' => 'note',
            'comments' => 'commentaire', 'published' => 'annee',
        ];
        $livres = [];
        foreach ($rows as $row) {
            if (count($row) !== count($header)) {
                continue;
            }
            $l = [];
            foreach ($header as $i => $h) {
                if (isset($map[$h])) {
                    $l[$map[$h]] = $row[$i];
                }
            }
            if ($l) {
                $livres[] = $l;
            }
        }
        return $livres;
    }
}
