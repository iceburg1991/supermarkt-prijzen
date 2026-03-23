"""
database.py — Gedeeld schema + SQLite opslaglaag

Alle scrapers importeren dit bestand.
Bevat:
  - ProductPrijs  (dataclass: het gedeelde schema)
  - Database      (klasse: schrijven en lezen)

Gebruik:
    from database import ProductPrijs, Database

    db = Database()
    db.sla_op([ProductPrijs(...), ...])
"""

import sqlite3
import os
from dataclasses import dataclass
from datetime import datetime

DB_PAD = os.path.join(os.path.dirname(__file__), "prijzen.db")


# ─────────────────────────────────────────────
#  GEDEELD SCHEMA
#  Elke scraper levert een lijst van deze objecten.
#  Voeg hier velden toe als alle scrapers ze kunnen leveren.
# ─────────────────────────────────────────────

@dataclass
class ProductPrijs:
    product_id:       str    # Stabiele ID uit de bron-API  (bv. "67649PAK")
    supermarkt:       str    # Naam van de keten            (bv. "jumbo")
    naam:             str    # Productnaam
    hoeveelheid:      str    # Eenheid/gewicht              (bv. "1 liter")
    prijs_cent:       int    # Prijs in centen              (bv. 99)
    promo_prijs_cent: int    # Actieprijs in centen, 0 = geen actie
    beschikbaar:      bool
    badge:            str    # Actietekst                   (bv. "2e halve prijs")
    eenheidsprijs:    str    # Bv. "€ 0,99 / l"
    afbeelding_url:   str
    product_url:      str
    scraped_at:       str = ""   # Wordt automatisch ingevuld door Database.sla_op()

    def __post_init__(self):
        if not self.scraped_at:
            self.scraped_at = datetime.now().isoformat()


# ─────────────────────────────────────────────
#  DATABASE
# ─────────────────────────────────────────────

class Database:
    def __init__(self, pad: str = DB_PAD):
        self.pad = pad
        self._initialiseer()

    def _verbinding(self) -> sqlite3.Connection:
        conn = sqlite3.connect(self.pad)
        conn.row_factory = sqlite3.Row
        conn.execute("PRAGMA journal_mode=WAL")
        return conn

    def _initialiseer(self):
        """Maak tabellen aan als ze nog niet bestaan."""
        with self._verbinding() as conn:
            conn.executescript("""
                CREATE TABLE IF NOT EXISTS scrape_runs (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    supermarkt  TEXT    NOT NULL,
                    gestart_op  TEXT    NOT NULL,
                    aantal      INTEGER DEFAULT 0
                );

                CREATE TABLE IF NOT EXISTS products (
                    product_id      TEXT NOT NULL,
                    supermarkt      TEXT NOT NULL,
                    naam            TEXT NOT NULL,
                    hoeveelheid     TEXT,
                    afbeelding_url  TEXT,
                    product_url     TEXT,
                    PRIMARY KEY (product_id, supermarkt)
                );

                CREATE TABLE IF NOT EXISTS prices (
                    id                INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id        TEXT    NOT NULL,
                    supermarkt        TEXT    NOT NULL,
                    prijs_cent        INTEGER NOT NULL,
                    promo_prijs_cent  INTEGER DEFAULT 0,
                    beschikbaar       INTEGER DEFAULT 1,
                    badge             TEXT,
                    eenheidsprijs     TEXT,
                    scraped_at        TEXT    NOT NULL,
                    FOREIGN KEY (product_id, supermarkt)
                        REFERENCES products (product_id, supermarkt)
                );

                CREATE INDEX IF NOT EXISTS idx_prices_product
                    ON prices (product_id, supermarkt, scraped_at);
            """)

    # ── Schrijven ─────────────────────────────

    def sla_op(self, producten: list[ProductPrijs]) -> int:
        """
        Sla een lijst ProductPrijs op.
        - Upsert stamdata in `products`
        - Voeg nieuwe prijsrij toe aan `prices`
        - Log de run in `scrape_runs`
        Geeft het aantal opgeslagen rijen terug.
        """
        if not producten:
            return 0

        supermarkt = producten[0].supermarkt
        gestart_op = datetime.now().isoformat()

        with self._verbinding() as conn:
            for p in producten:
                # Stamdata: upsert (update naam/afbeelding als die veranderd is)
                conn.execute("""
                    INSERT INTO products
                        (product_id, supermarkt, naam, hoeveelheid, afbeelding_url, product_url)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT (product_id, supermarkt) DO UPDATE SET
                        naam           = excluded.naam,
                        hoeveelheid    = excluded.hoeveelheid,
                        afbeelding_url = excluded.afbeelding_url,
                        product_url    = excluded.product_url
                """, (
                    p.product_id, p.supermarkt, p.naam,
                    p.hoeveelheid, p.afbeelding_url, p.product_url
                ))

                # Prijsrij toevoegen
                conn.execute("""
                    INSERT INTO prices
                        (product_id, supermarkt, prijs_cent, promo_prijs_cent,
                         beschikbaar, badge, eenheidsprijs, scraped_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    p.product_id, p.supermarkt, p.prijs_cent,
                    p.promo_prijs_cent, int(p.beschikbaar),
                    p.badge, p.eenheidsprijs, p.scraped_at
                ))

            # Run loggen
            conn.execute("""
                INSERT INTO scrape_runs (supermarkt, gestart_op, aantal)
                VALUES (?, ?, ?)
            """, (supermarkt, gestart_op, len(producten)))

        return len(producten)

    # ── Lezen ─────────────────────────────────

    def huidige_prijzen(self, supermarkt: str = None) -> list[dict]:
        """
        Laatste bekende prijs per product.
        Optioneel gefilterd op supermarkt.
        """
        filter_sql = "WHERE p.supermarkt = ?" if supermarkt else ""
        params     = (supermarkt,) if supermarkt else ()

        with self._verbinding() as conn:
            rows = conn.execute(f"""
                SELECT
                    pr.product_id,
                    pr.supermarkt,
                    p.naam,
                    p.hoeveelheid,
                    pr.prijs_cent,
                    pr.promo_prijs_cent,
                    pr.beschikbaar,
                    pr.badge,
                    pr.eenheidsprijs,
                    p.afbeelding_url,
                    p.product_url,
                    pr.scraped_at
                FROM prices pr
                JOIN products p
                    ON pr.product_id = p.product_id
                    AND pr.supermarkt = p.supermarkt
                INNER JOIN (
                    SELECT product_id, supermarkt, MAX(scraped_at) AS laatste
                    FROM prices
                    GROUP BY product_id, supermarkt
                ) latest
                    ON pr.product_id  = latest.product_id
                    AND pr.supermarkt = latest.supermarkt
                    AND pr.scraped_at = latest.laatste
                {filter_sql}
                ORDER BY p.naam
            """, params).fetchall()
        return [dict(r) for r in rows]

    def prijstrend(self, product_id: str, supermarkt: str, dagen: int = 90) -> list[dict]:
        """
        Prijsgeschiedenis van één product over de afgelopen N dagen.
        Bruikbaar voor een lijngrafiek.
        """
        with self._verbinding() as conn:
            rows = conn.execute("""
                SELECT
                    pr.prijs_cent,
                    pr.promo_prijs_cent,
                    pr.beschikbaar,
                    pr.badge,
                    pr.scraped_at
                FROM prices pr
                WHERE pr.product_id = ?
                  AND pr.supermarkt  = ?
                  AND pr.scraped_at >= datetime('now', ? || ' days')
                ORDER BY pr.scraped_at ASC
            """, (product_id, supermarkt, f"-{dagen}")).fetchall()
        return [dict(r) for r in rows]

    def vergelijk_product(self, naam_zoekterm: str) -> list[dict]:
        """
        Zoek een product op naam en vergelijk de huidige prijs
        over alle supermarkten.
        """
        with self._verbinding() as conn:
            rows = conn.execute("""
                SELECT
                    pr.supermarkt,
                    p.naam,
                    p.hoeveelheid,
                    pr.prijs_cent,
                    pr.promo_prijs_cent,
                    pr.beschikbaar,
                    pr.scraped_at
                FROM prices pr
                JOIN products p
                    ON pr.product_id = p.product_id
                    AND pr.supermarkt = p.supermarkt
                INNER JOIN (
                    SELECT product_id, supermarkt, MAX(scraped_at) AS laatste
                    FROM prices GROUP BY product_id, supermarkt
                ) latest
                    ON pr.product_id  = latest.product_id
                    AND pr.supermarkt = latest.supermarkt
                    AND pr.scraped_at = latest.laatste
                WHERE LOWER(p.naam) LIKE LOWER(?)
                ORDER BY pr.prijs_cent ASC
            """, (f"%{naam_zoekterm}%",)).fetchall()
        return [dict(r) for r in rows]

    def scrape_runs(self, supermarkt: str = None) -> list[dict]:
        """Overzicht van alle scrape-runs, nieuwste eerst."""
        filter_sql = "WHERE supermarkt = ?" if supermarkt else ""
        params     = (supermarkt,) if supermarkt else ()
        with self._verbinding() as conn:
            rows = conn.execute(f"""
                SELECT * FROM scrape_runs
                {filter_sql}
                ORDER BY gestart_op DESC
                LIMIT 50
            """, params).fetchall()
        return [dict(r) for r in rows]
