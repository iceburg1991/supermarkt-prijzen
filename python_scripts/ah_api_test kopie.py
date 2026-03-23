"""
ah_api_test.py — Test of de Albert Heijn API endpoints bereikbaar zijn

Gebruik:
    python3 ah_api_test.py
"""

import requests
import json

GROEN = "\033[92m"; ROOD = "\033[91m"; GEEL = "\033[93m"; RESET = "\033[0m"; BOLD = "\033[1m"

BASE_AUTH = "https://api.ah.nl/mobile-auth/v1/auth"
BASE_API  = "https://api.ah.nl/mobile-services"
HEADERS   = {"User-Agent": "Appie/8.22.3", "Content-Type": "application/json", "Accept": "application/json"}


def haal_token():
    try:
        r = requests.post(f"{BASE_AUTH}/token/anonymous", headers=HEADERS,
                          json={"clientId": "appie"}, timeout=8)
        r.raise_for_status()
        return r.json().get("access_token")
    except Exception as e:
        return None


def test(label, url, params, token, verwachte_sleutel=None):
    hdrs = {**HEADERS, "Authorization": f"Bearer {token}"} if token else HEADERS
    try:
        r = requests.get(url, headers=hdrs, params=params, timeout=8)
        status = r.status_code
        if status == 200:
            data = r.json()
            extra = ""
            if verwachte_sleutel and verwachte_sleutel not in data:
                return "DEELS", status, f"Sleutel '{verwachte_sleutel}' ontbreekt", data
            items = data.get("products", data.get("cards", []))
            if isinstance(items, list):
                extra = f"{len(items)} items"
            elif isinstance(items, dict):
                extra = f"{items.get('totalElements', '?')} totaal"
            return "OK", status, extra or "JSON ok", data
        elif status == 401:
            return "AUTH", status, "Token verlopen of ongeldig", {}
        elif status == 403:
            return "GEBLOKKEERD", status, "Toegang geweigerd", {}
        elif status == 404:
            return "404", status, "Endpoint niet gevonden", {}
        else:
            return "FOUT", status, r.text[:100], {}
    except Exception as e:
        return "FOUT", 0, str(e), {}


def druk_af(label, type_, code, bericht):
    icoon = {"OK": f"{GROEN}✓ WERKT    {RESET}", "DEELS": f"{GEEL}~ DEELS    {RESET}",
             "AUTH": f"{GEEL}🔐 AUTH     {RESET}", "GEBLOKKEERD": f"{ROOD}✗ GEBLOKKEERD{RESET}",
             "404": f"{ROOD}✗ 404       {RESET}", "FOUT": f"{ROOD}✗ FOUT      {RESET}"}.get(type_, "?")
    print(f"  {icoon} [{code or '---'}]  {label:<38} {bericht}")


def main():
    print(f"\n{BOLD}{'='*62}")
    print("  ALBERT HEIJN API TESTER")
    print(f"{'='*62}{RESET}\n")

    # Stap 1: token
    print("  Stap 1: anoniem token ophalen...")
    token = haal_token()
    if token:
        print(f"  {GROEN}Token verkregen{RESET} ({token[:20]}...)\n")
    else:
        print(f"  {ROOD}Token ophalen mislukt — verdere tests zinloos{RESET}\n")

    ENDPOINTS = [
        ("Zoeken: melk",          f"{BASE_API}/product/search/v2",    {"query": "melk",  "size": 5}, "products"),
        ("Zoeken: brood",         f"{BASE_API}/product/search/v2",    {"query": "brood", "size": 5}, "products"),
        ("Bonus taxonomy",        f"{BASE_API}/product/search/v2",    {"taxonomyId": "bonus", "size": 10}, "products"),
        ("Categorieën",           f"{BASE_API}/product/taxonomy/v1",  {}, None),
        ("Product detail (wi123)",f"{BASE_API}/product/detail/v4/fir/123", {}, None),
    ]

    resultaten = {}
    werkende_data = {}

    print("  Stap 2: endpoints testen...\n")
    for (label, url, params, sleutel) in ENDPOINTS:
        type_, code, bericht, data = test(label, url, params, token, sleutel)
        druk_af(label, type_, code, bericht)
        resultaten[type_] = resultaten.get(type_, 0) + 1
        if type_ == "OK" and data:
            werkende_data[label] = data

    print(f"\n{BOLD}{'─'*62}\n  SAMENVATTING{RESET}")
    print(f"  {GROEN}Werkend:    {resultaten.get('OK', 0)}{RESET}")
    print(f"  {GEEL}Gedeeltelijk: {resultaten.get('DEELS', 0)}{RESET}")
    print(f"  {ROOD}Niet werkend: {sum(v for k,v in resultaten.items() if k not in ('OK','DEELS'))}{RESET}")

    if werkende_data:
        print(f"\n{BOLD}  DATA PREVIEW{RESET}\n{'─'*62}")
        eerste_data = list(werkende_data.values())[0]
        producten = eerste_data.get("products", [])[:2]
        for p in producten:
            print(f"  Naam:   {p.get('title', '?')}")
            print(f"  Prijs:  € {p.get('price', {}).get('now', '?')}")
            print(f"  Bonus:  {p.get('bonusType', '-')}")
            print(f"  Beschikbaar: {p.get('available', '?')}")
            print()

    print(f"{'='*62}\n")


if __name__ == "__main__":
    main()
