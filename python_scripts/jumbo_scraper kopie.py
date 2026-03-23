"""
jumbo_scraper.py — Haalt producten en aanbiedingen op bij Jumbo

Schrijft resultaten naar prijzen.db via database.py.
Weet niks van HTML of rapportages.

Gebruik:
    python3 jumbo_scraper.py

Vereisten:
    pip3 install requests
"""

import requests
import time
from database import ProductPrijs, Database

# ─────────────────────────────────────────────
#  CONFIGURATIE
# ─────────────────────────────────────────────

ZOEKTERMEN       = ["melk", "brood", "kaas", "koffie", "eieren"]
MAX_PER_ZOEKTERM = 20
VERTRAGING       = 0.6

BASE_URL = "https://mobileapi.jumbo.com/v17"
HEADERS  = {
    "User-Agent": "Jumbo/13.0.0 (iPhone; iOS 17.0; Scale/3.00)",
    "Accept":     "application/json",
}

# ─────────────────────────────────────────────
#  API
# ─────────────────────────────────────────────

def get(path, params=None):
    try:
        r = requests.get(f"{BASE_URL}{path}", headers=HEADERS,
                         params=params, timeout=10)
        r.raise_for_status()
        return r.json()
    except Exception as e:
        print(f"  x {path}: {e}")
        return None


# ─────────────────────────────────────────────
#  VERTALING API -> GEDEELD SCHEMA
# ─────────────────────────────────────────────

def naar_schema(p: dict) -> ProductPrijs:
    """Vertaal één Jumbo API-product naar het gedeelde ProductPrijs schema."""
    prijs_info = p.get("prices", {})
    prijs_obj  = prijs_info.get("price", {})
    eenheid    = prijs_info.get("unitPrice", {})
    promo      = prijs_info.get("promotionalPrice", {})

    prijs_cent = prijs_obj.get("amount", 0)
    promo_cent = promo.get("amount", 0) if promo else 0

    eenheidsprijs = ""
    if eenheid:
        ep_cent = eenheid.get("price", {}).get("amount", 0)
        unit    = eenheid.get("unit", "")
        if ep_cent:
            eenheidsprijs = "€ {:.2f} / {}".format(ep_cent / 100, unit).replace(".", ",")

    badge = ""
    b = p.get("badge")
    if isinstance(b, dict):
        badge = b.get("text", "")
    elif isinstance(b, str):
        badge = b

    afbeelding = ""
    views = p.get("imageInfo", {}).get("primaryView", [])
    if views:
        afbeelding = views[0].get("url", "")

    product_id = str(p.get("id", ""))

    return ProductPrijs(
        product_id       = product_id,
        supermarkt       = "jumbo",
        naam             = p.get("title", "Onbekend"),
        hoeveelheid      = p.get("quantity", ""),
        prijs_cent       = prijs_cent,
        promo_prijs_cent = promo_cent,
        beschikbaar      = p.get("available", False),
        badge            = badge,
        eenheidsprijs    = eenheidsprijs,
        afbeelding_url   = afbeelding,
        product_url      = "https://www.jumbo.com/producten/{}".format(product_id),
    )


# ─────────────────────────────────────────────
#  SCRAPE FUNCTIES
# ─────────────────────────────────────────────

def zoek_producten(zoekterm: str, max_resultaten: int = 20) -> list:
    print("\n  Zoeken: '{}'...".format(zoekterm))
    alle   = []
    offset = 0

    while len(alle) < max_resultaten:
        limit = min(30, max_resultaten - len(alle))
        data  = get("/search", {"q": zoekterm, "offset": offset, "limit": limit})
        if not data:
            break

        ruw = data.get("products", {}).get("data", [])
        if not ruw:
            break

        for p in ruw:
            product = naar_schema(p)
            alle.append(product)
            promo_str = " -> € {:.2f}".format(product.promo_prijs_cent / 100) if product.promo_prijs_cent else ""
            print("    + {:<50} € {:.2f}{}".format(product.naam[:50], product.prijs_cent / 100, promo_str))

        offset += len(ruw)
        if offset >= data.get("products", {}).get("total", 0):
            break
        time.sleep(VERTRAGING)

    return alle


def haal_aanbieding_producten_op() -> list:
    print("\n  Aanbieding-categorieen ophalen...")
    data = get("/categories")
    if not data:
        return []

    trefwoorden = ["goedkoopjes", "aanbieding", "actie", "korting"]
    cats = [
        c for c in data.get("categories", {}).get("data", [])
        if any(t in c.get("title", "").lower() for t in trefwoorden)
    ]
    print("  -> {} aanbieding-categorieen gevonden".format(len(cats)))

    alle = []
    for cat in cats[:3]:
        print("  -> Producten ophalen uit: {}".format(cat["title"]))
        r = get("/search", {"category": cat["catId"], "limit": 20, "offset": 0})
        if r:
            for p in r.get("products", {}).get("data", []):
                alle.append(naar_schema(p))
        time.sleep(VERTRAGING)

    return alle


# ─────────────────────────────────────────────
#  HOOFDPROGRAMMA
# ─────────────────────────────────────────────

def main():
    print("=" * 54)
    print("  JUMBO SCRAPER")
    print("=" * 54)

    alle = []
    alle += haal_aanbieding_producten_op()

    for term in ZOEKTERMEN:
        alle += zoek_producten(term, max_resultaten=MAX_PER_ZOEKTERM)
        time.sleep(VERTRAGING)

    # Dedupliceren op product_id
    gezien = set()
    uniek  = []
    for p in alle:
        if p.product_id not in gezien:
            gezien.add(p.product_id)
            uniek.append(p)

    print("\n  {} unieke producten (van {} totaal)".format(len(uniek), len(alle)))

    db = Database()
    opgeslagen = db.sla_op(uniek)
    print("  Opgeslagen in prijzen.db: {} producten".format(opgeslagen))
    print("\n" + "=" * 54 + "\n")


if __name__ == "__main__":
    main()
