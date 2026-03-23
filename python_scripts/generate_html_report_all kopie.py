"""
run_all.py — Draai alle scrapers en genereer het rapport

Gebruik:
    python3 run_all.py              # alles
    python3 run_all.py --jumbo      # alleen Jumbo
    python3 run_all.py --ah         # alleen AH
    python3 run_all.py --rapport    # alleen rapport genereren
"""

import sys
import importlib
import traceback
from datetime import datetime

def run(naam: str, module_naam: str) -> bool:
    print("\n" + "─" * 54)
    print("  Starten: {}  ({})".format(naam, datetime.now().strftime("%H:%M:%S")))
    print("─" * 54)
    try:
        module = importlib.import_module(module_naam)
        module.main()
        print("  ✓ {} klaar".format(naam))
        return True
    except Exception as e:
        print("  ✗ {} mislukt: {}".format(naam, e))
        traceback.print_exc()
        return False


def main():
    args = sys.argv[1:]

    alles    = not args
    jumbo    = alles or "--jumbo"   in args
    ah       = alles or "--ah"      in args
    rapport  = alles or "--rapport" in args

    print("=" * 54)
    print("  SUPERMARKT DASHBOARD — {}".format(
        datetime.now().strftime("%d-%m-%Y %H:%M")
    ))
    print("=" * 54)

    resultaten = {}

    if jumbo:
        resultaten["Jumbo"] = run("Jumbo scraper", "jumbo_scraper")

    if ah:
        resultaten["AH"] = run("Albert Heijn scraper", "ah_scraper")

    if rapport:
        resultaten["Rapport"] = run("Rapport generator", "report_generator")

    # Samenvatting
    print("\n" + "=" * 54)
    print("  KLAAR")
    print("=" * 54)
    for naam, succes in resultaten.items():
        status = "✓" if succes else "✗"
        print("  {}  {}".format(status, naam))
    print()


if __name__ == "__main__":
    main()
