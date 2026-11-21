#!/usr/bin/env python3
"""
Export d'une bibliothèque Calibre (metadata.db, SQLite) vers un JSON importable
par Lisons-plus (page /import).

Gère les anciens et les nouveaux schémas Calibre (colonnes de lien `author`
ou `author_id`, ISBN dans `books.isbn` ou dans la table `identifiers`).

Usage :
    python3 scripts/calibre_to_json.py "/chemin/vers/Bibliothèque calibre/metadata.db" data/import-calibre.json
"""
import json
import re
import sqlite3
import sys


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2
    db_path, out_path = sys.argv[1], sys.argv[2]
    conn = sqlite3.connect(f"file:{db_path}?mode=ro", uri=True)
    conn.row_factory = sqlite3.Row
    cur = conn.cursor()

    def table_existe(nom: str) -> bool:
        cur.execute("SELECT name FROM sqlite_master WHERE type='table' AND name=?", (nom,))
        return cur.fetchone() is not None

    def colonnes(nom: str) -> list[str]:
        return [r[1] for r in cur.execute(f"PRAGMA table_info({nom})")]

    def lien_colonne(table_cible: str, table_lien: str) -> str:
        """Colonne de la table de lien qui fait référence à la table cible."""
        cols = colonnes(table_lien)
        candidats = [table_cible, table_cible[:-1] if table_cible.endswith("s") else table_cible,
                     f"{table_cible}_id", f"{table_cible[:-1]}_id" if table_cible.endswith("s") else f"{table_cible}_id"]
        for c in candidats:
            if c in cols:
                return c
        raise RuntimeError(f"Colonne de lien introuvable entre {table_lien} et {table_cible}: {cols}")

    def noms(table: str, lien: str, bid: int) -> list[str]:
        if not table_existe(table) or not table_existe(lien):
            return []
        col = lien_colonne(table, lien)
        cur.execute(f"SELECT t.name AS n FROM {table} t JOIN {lien} l ON l.{col}=t.id WHERE l.book=? ORDER BY l.id", (bid,))
        return [r["n"] for r in cur.fetchall() if r["n"]]

    livres = []
    for b in cur.execute("SELECT * FROM books").fetchall():
        bid = b["id"]
        auteurs = noms("authors", "books_authors_link", bid)
        editeurs = noms("publishers", "books_publishers_link", bid)
        series = noms("series", "books_series_link", bid)
        tags = noms("tags", "books_tags_link", bid)

        # ISBN : colonne books.isbn (schémas récents) ou table identifiers (anciens schémas)
        isbn = ""
        if "isbn" in b.keys() and b["isbn"]:
            isbn = b["isbn"].strip()
        elif table_existe("identifiers"):
            cur.execute("SELECT val FROM identifiers WHERE book=? AND lower(type) LIKE 'isbn%' ORDER BY id", (bid,))
            row = cur.fetchone()
            if row:
                isbn = (row["val"] or "").strip()

        # Note : ratings (0-100) -> 0-5
        note = 0
        if table_existe("ratings") and table_existe("books_ratings_link"):
            col = lien_colonne("ratings", "books_ratings_link")
            cur.execute(f"SELECT r.rating FROM ratings r JOIN books_ratings_link l ON l.{col}=r.id WHERE l.book=?", (bid,))
            row = cur.fetchone()
            if row and row["rating"]:
                note = min(5, max(0, round(row["rating"] / 20)))

        # Commentaire : table comments (schémas récents: colonne books.comments)
        commentaire = ""
        if table_existe("comments"):
            cur.execute("SELECT text FROM comments WHERE book=?", (bid,))
            row = cur.fetchone()
            if row and row["text"]:
                commentaire = row["text"]
        elif "comments" in b.keys() and b["comments"]:
            commentaire = b["comments"]
        commentaire = re.sub(r"<[^>]+>", "", commentaire or "").strip()

        # Nombre de pages si disponible
        pages = None
        if table_existe("books_pages_link"):
            cur.execute("SELECT pages FROM books_pages_link WHERE book=?", (bid,))
            row = cur.fetchone()
            if row and row["pages"]:
                pages = int(row["pages"])

        annee = None
        if b["pubdate"]:
            m = re.search(r"(\d{4})", b["pubdate"])
            if m:
                annee = int(m.group(1))

        livres.append({
            "titre": (b["title"] or "").strip(),
            "auteurs": auteurs,
            "isbn": isbn,
            "editeur": editeurs[0] if editeurs else "",
            "annee": annee,
            "pages": pages,
            "serie": series[0] if series else "",
            "tags": tags,
            "note": note,
            "commentaire": commentaire,
            "statut": "lu",  # une bibliothèque Calibre contient en général des livres acquis/lus
            "date_ajout": (b["timestamp"] or "")[:10] or None,
        })

    with open(out_path, "w", encoding="utf-8") as f:
        json.dump({"livres": livres}, f, ensure_ascii=False, indent=2)
    print(f"{len(livres)} livre(s) exporté(s) vers {out_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
