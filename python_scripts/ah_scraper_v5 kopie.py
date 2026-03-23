"""
ah_scraper.py — Haalt producten op bij Albert Heijn

Gebaseerd op de bevestigde AH REST API:
  GET /mobile-services/product/search/v2

Veldnamen geverifieerd via appie-go broncode (gwillem/appie-go).

Authenticatie: token wordt opgeslagen in ah_token.json.
Eerste keer: script vraagt om een login-code.

Gebruik:
    python3 ah_scraper.py

Vereisten:
    pip3 install requests
"""

import requests
import json
import time
import os
from typing import Optional
from database import ProductPrijs, Database

# ─────────────────────────────────────────────
#  CONFIGURATIE
# ─────────────────────────────────────────────

ZOEKTERMEN       = ["melk", "brood", "kaas", "koffie", "eieren"]
MAX_PER_ZOEKTERM = 20
VERTRAGING       = 0.6
TOKEN_BESTAND    = os.path.join(os.path.dirname(__file__), "ah_token.json")

BASE_URL  = "https://api.ah.nl"
AUTH_URL  = "https://api.ah.nl/mobile-auth/v1/auth"
HEADERS   = {
    "User-Agent":       "Appie/9.28 (iPhone17,3; iPhone; CPU OS 26_1 like Mac OS X)",
    "Content-Type":     "application/json",
    "Accept":           "application/json",
    "x-client-name":    "appie-ios",
    "x-client-version": "9.28",
    "x-application":    "AHWEBSHOP",
}


# ─────────────────────────────────────────────
#  TOKEN BEHEER
# ─────────────────────────────────────────────

def laad_token() -> Optional[dict]:
    if os.path.exists(TOKEN_BESTAND):
        with open(TOKEN_BESTAND) as f:
            return json.load(f)
    return None


def sla_token_op(token_data: dict):
    with open(TOKEN_BESTAND, "w") as f:
        json.dump(token_data, f, indent=2)
    print("  Token opgeslagen in ah_token.json")


def token_via_code(code: str) -> Optional[dict]:
    try:
        r = requests.post(
            "{}/token".format(AUTH_URL),
            headers=HEADERS,
            json={"clientId": "appie-ios", "code": code},
            timeout=10,
        )
        r.raise_for_status()
        return r.json()
    except Exception as e:
        print("  x Token via code mislukt: {}".format(e))
        return None


def vernieuw_token(refresh_token: str) -> Optional[dict]:
    try:
        r = requests.post(
            "{}/token/refresh".format(AUTH_URL),
            headers=HEADERS,
            json={"clientId": "appie-ios", "refreshToken": refresh_token},
            timeout=10,
        )
        r.raise_for_status()
        return r.json()
    except Exception as e:
        print("  x Token vernieuwen mislukt: {}".format(e))
        return None


def zorg_voor_token() -> Optional[str]:
    opgeslagen = laad_token()

    if opgeslagen and opgeslagen.get("refresh_token"):
        print("  Token vernieuwen...")
        nieuw = vernieuw_token(opgeslagen["refresh_token"])
        if nieuw and nieuw.get("access_token"):
            sla_token_op(nieuw)
            print("  Token vernieuwd")
            return nieuw["access_token"]
        print("  Vernieuwen mislukt, opnieuw inloggen vereist")

    print()
    print("  Geen geldig token. Volg deze stappen:")
    print()
    print("  1. Open in je browser:")
    print("     https://login.ah.nl/secure/oauth/authorize"
          "?client_id=appie&redirect_uri=appie://login-exit&response_type=code")
    print()
    print("  2. Log in met je AH-account")
    print()
    print("  3. Open DevTools (F12) -> Network")
    print("     Zoek het mislukte request naar 'appie://login-exit?code=...'")
    print("     Kopieer de waarde na 'code='")
    print()
    code = input("  Plak hier de code: ").strip()
    if not code:
        return None

    token_data = token_via_code(code)
    if token_data and token_data.get("access_token"):
        sla_token_op(token_data)
        return token_data["access_token"]

    print("  x Inloggen mislukt")
    return None


# ─────────────────────────────────────────────
#  REST API
#  Endpoint: /mobile-services/product/search/v2
#  Params:   query, page, size, sortOn
# ─────────────────────────────────────────────

def zoek(zoekterm: str, pagina: int, grootte: int, token: str) -> Optional[dict]:
    params = {
        "query":  zoekterm,
        "page":   pagina,
        "size":   grootte,
        "sortOn": "RELEVANCE",
    }
    try:
        r = requests.get(
            "{}/mobile-services/product/search/v2".format(BASE_URL),
            headers={**HEADERS, "Authorization": "Bearer {}".format(token)},
            params=params,
            timeout=10,
        )
        if not r.ok:
            print("  x HTTP {} bij zoeken: {}".format(r.status_code, r.text[:150]))
            return None
        return r.json()
    except Exception as e:
        print("  x Verzoek mislukt: {}".format(e))
        return None


def zoek_bonus(pagina: int, grootte: int, token: str) -> Optional[dict]:
    """Bonusproducten via isBonus filter."""
    params = {
        "query":       "",
        "page":        pagina,
        "size":        grootte,
        "sortOn":      "RELEVANCE",
        "filters":     "bonus:true",
    }
    try:
        r = requests.get(
            "{}/mobile-services/product/search/v2".format(BASE_URL),
            headers={**HEADERS, "Authorization": "Bearer {}".format(token)},
            params=params,
            timeout=10,
        )
        if not r.ok:
            return None
        return r.json()
    except Exception:
        return None


# ─────────────────────────────────────────────
#  VERTALING API -> GEDEELD SCHEMA
#
#  Velden geverifieerd via appie-go/products.go:
#    webshopId, title, brand, salesUnitSize,
#    unitPriceDescription, images[].url,
#    currentPrice, priceBeforeBonus,
#    isBonus, bonusMechanism, availableOnline
# ─────────────────────────────────────────────

def naar_schema(p: dict) -> ProductPrijs:
    product_id  = str(p.get("webshopId") or p.get("hqId") or "")
    naam        = p.get("title", "Onbekend")
    hoeveelheid = p.get("salesUnitSize", "")
    brand       = p.get("brand", "")
    if brand and brand.lower() not in naam.lower():
        naam = "{} {}".format(brand, naam).strip()

    # Prijslogica (geverifieerd via debug output):
    # - currentPrice bestaat NIET in de search response
    # - priceBeforeBonus = altijd de normale (reguliere) prijs
    # - isBonus = true betekent er is een actie actief
    # - bij bonus: actieprijs staat in bonusMechanism tekst (bv. "2 voor 4.49")
    #   maar een exacte actieprijs als getal is niet beschikbaar in search v2
    prijs_was = p.get("priceBeforeBonus") or 0
    prijs_cent = round(prijs_was * 100)

    # Actieprijs: alleen invullen als er een bonusprijs beschikbaar is
    is_bonus = p.get("isBonus", False)
    promo_prijs_cent = 0
    if is_bonus:
        # isBonusPrice geeft aan of de currentPrice de actieprijs is
        # In search v2 is de exacte actieprijs helaas niet als getal beschikbaar
        # We markeren het product wel als actie via de badge
        promo_prijs_cent = 0

    # Badge: bonusMechanism bevat al leesbare tekst ("2 Voor 4.49", "25% Korting")
    badge = ""
    if is_bonus:
        bonus_mechanisme = p.get("bonusMechanism", "") or ""
        badge = bonus_mechanisme.strip()

    # Eenheidsprijs (bv. "€ 0,99 / l")
    eenheidsprijs = p.get("unitPriceDescription", "") or ""

    # Afbeelding
    afbeelding = ""
    images = p.get("images", []) or []
    if images:
        afbeelding = images[0].get("url", "")

    return ProductPrijs(
        product_id       = product_id,
        supermarkt       = "ah",
        naam             = naam,
        hoeveelheid      = hoeveelheid,
        prijs_cent       = prijs_cent,
        promo_prijs_cent = promo_prijs_cent,
        beschikbaar      = bool(p.get("availableOnline", True)),
        badge            = badge,
        eenheidsprijs    = eenheidsprijs,
        afbeelding_url   = afbeelding,
        product_url      = "https://www.ah.nl/producten/product/wi{}".format(product_id),
    )


# ─────────────────────────────────────────────
#  SCRAPE FUNCTIES
# ─────────────────────────────────────────────

def haal_producten_op(zoekterm: str, token: str, max_resultaten: int = 20) -> list:
    print("\n  Zoeken: '{}'...".format(zoekterm))
    alle   = []
    pagina = 0

    while len(alle) < max_resultaten:
        grootte = min(30, max_resultaten - len(alle))
        data    = zoek(zoekterm, pagina, grootte, token)
        if not data:
            break

        ruw = data.get("products", [])
        if not ruw:
            break

        for p in ruw:
            product = naar_schema(p)
            alle.append(product)
            promo_str = " -> {} actie".format(
                "€ {:.2f}".format(product.promo_prijs_cent / 100)
            ) if product.promo_prijs_cent else ""
            print("    + {:<50} € {:.2f}{}".format(
                product.naam[:50], product.prijs_cent / 100, promo_str
            ))

        pagina_info   = data.get("page", {})
        totaal_paginas = pagina_info.get("totalPages", 1)
        pagina += 1
        if pagina >= totaal_paginas:
            break
        time.sleep(VERTRAGING)

    return alle


def haal_bonus_op(token: str, max_resultaten: int = 30) -> list:
    print("\n  Bonusproducten ophalen...")
    alle   = []
    pagina = 0

    while len(alle) < max_resultaten:
        data = zoek_bonus(pagina, min(30, max_resultaten - len(alle)), token)
        if not data:
            # Fallback: gewoon zoeken en client-side filteren op isBonus
            data = zoek("", pagina, 30, token)
            if not data:
                break

        ruw = data.get("products", [])
        if not ruw:
            break

        for p in ruw:
            if p.get("isBonus"):
                product = naar_schema(p)
                alle.append(product)
                print("    * {:<50} {}".format(product.naam[:50], product.badge))

        pagina_info    = data.get("page", {})
        totaal_paginas = pagina_info.get("totalPages", 1)
        pagina += 1
        if pagina >= totaal_paginas:
            break
        time.sleep(VERTRAGING)

    print("  -> {} bonusproducten gevonden".format(len(alle)))
    return alle


# ─────────────────────────────────────────────
#  HOOFDPROGRAMMA
# ─────────────────────────────────────────────

def main():
    print("=" * 54)
    print("  ALBERT HEIJN SCRAPER")
    print("=" * 54)

    token = zorg_voor_token()
    if not token:
        print("\n  Gestopt: geen geldig token.")
        return

    alle = []
    alle += haal_bonus_op(token, max_resultaten=30)

    for term in ZOEKTERMEN:
        alle += haal_producten_op(term, token, max_resultaten=MAX_PER_ZOEKTERM)
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
