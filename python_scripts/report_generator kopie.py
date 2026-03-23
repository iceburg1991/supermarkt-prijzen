"""
report_generator.py — Leest prijzen.db en bouwt rapport.html

Weet niks van individuele supermarkt-APIs.
Leest via Database() en genereert HTML met:
  - Huidige prijzen per supermarkt
  - Prijstrend per product (minigrafieken)
  - Scrape-run geschiedenis

Gebruik:
    python3 report_generator.py
"""

import os
import json
import webbrowser
from datetime import datetime
from database import Database

RAPPORT_PAD = os.path.join(os.path.dirname(__file__), "rapport.html")

SUPERMARKT_KLEUR = {
    "jumbo":   "#ffd800",
    "ah":      "#00a1e0",
    "plus":    "#e4202a",
    "lidl":    "#0050aa",
    "aldi":    "#0066cc",
}

def euro(cent: int) -> str:
    return "€ {:.2f}".format(cent / 100).replace(".", ",")


# ─────────────────────────────────────────────
#  HTML BOUWSTENEN
# ─────────────────────────────────────────────

def product_rij(p: dict) -> str:
    naam       = p["naam"]
    badge      = '<span class="badge">{}</span>'.format(p["badge"]) if p["badge"] else ""
    prijs      = euro(p["prijs_cent"])
    promo      = '<span class="promo">{}</span>'.format(euro(p["promo_prijs_cent"])) if p["promo_prijs_cent"] else ""
    eenheid    = p["eenheidsprijs"] or ""
    winkel     = p["supermarkt"]
    kleur      = SUPERMARKT_KLEUR.get(winkel, "#888")
    img        = '<img src="{}" class="thumb" alt="">'.format(p["afbeelding_url"]) if p["afbeelding_url"] else '<div class="thumb-ph"></div>'
    na_class   = " na" if not p["beschikbaar"] else ""
    status     = '<span class="s-ja">Beschikbaar</span>' if p["beschikbaar"] else '<span class="s-nee">Niet beschikbaar</span>'

    return (
        '<tr class="rij{na}">'
        '<td>{img}</td>'
        '<td><strong>{naam}</strong>{badge}<br><small class="sub">{hoeveelheid}</small></td>'
        '<td class="prijs">{prijs}{promo}</td>'
        '<td class="sub">{eenheid}</td>'
        '<td><span class="winkel-badge" style="background:{kleur}">{winkel}</span></td>'
        '<td>{status}</td>'
        '<td><a href="{url}" target="_blank" class="link">↗</a></td>'
        '</tr>'
    ).format(
        na=na_class, img=img, naam=naam, badge=badge,
        hoeveelheid=p["hoeveelheid"], prijs=prijs, promo=promo,
        eenheid=eenheid, kleur=kleur, winkel=winkel.upper(),
        status=status, url=p["product_url"]
    )


def run_rij(r: dict) -> str:
    return (
        '<tr>'
        '<td><span class="winkel-badge" style="background:{kleur}">{winkel}</span></td>'
        '<td>{gestart}</td>'
        '<td>{aantal}</td>'
        '</tr>'
    ).format(
        kleur=SUPERMARKT_KLEUR.get(r["supermarkt"], "#888"),
        winkel=r["supermarkt"].upper(),
        gestart=r["gestart_op"][:16].replace("T", " "),
        aantal=r["aantal"],
    )


# ─────────────────────────────────────────────
#  COMPLETE HTML
# ─────────────────────────────────────────────

def genereer_html(db: Database) -> str:
    producten    = db.huidige_prijzen()
    runs         = db.scrape_runs()
    timestamp    = datetime.now().strftime("%d-%m-%Y %H:%M")

    supermarkten = sorted(set(p["supermarkt"] for p in producten))
    beschikbaar  = sum(1 for p in producten if p["beschikbaar"])
    met_actie    = sum(1 for p in producten if p["badge"])
    met_promo    = sum(1 for p in producten if p["promo_prijs_cent"])

    product_rijen = "\n".join(product_rij(p) for p in producten)
    run_rijen     = "\n".join(run_rij(r) for r in runs[:20])

    winkel_pills = "".join(
        '<span class="winkel-badge" style="background:{}">{}</span> '.format(
            SUPERMARKT_KLEUR.get(w, "#888"), w.upper()
        )
        for w in supermarkten
    )

    # Trenddata als JSON voor inline grafiek (eerste 5 producten als voorbeeld)
    trenddata = {}
    for p in producten[:5]:
        trend = db.prijstrend(p["product_id"], p["supermarkt"], dagen=30)
        if len(trend) > 1:
            trenddata[p["naam"][:30]] = {
                "labels": [t["scraped_at"][:10] for t in trend],
                "data":   [t["prijs_cent"] / 100 for t in trend],
                "kleur":  SUPERMARKT_KLEUR.get(p["supermarkt"], "#888"),
            }

    trenddata_json = json.dumps(trenddata, ensure_ascii=False)

    return """<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supermarkt Prijzen Dashboard</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
  :root {{
    --bg:#0f0f1a; --kaart:#17172a; --rand:#2a2a45;
    --geel:#ffd800; --rood:#e94560; --groen:#27ae60;
    --sub:#7a7a9a; --wit:#eaeaea;
  }}
  * {{ box-sizing:border-box; margin:0; padding:0 }}
  body {{ font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--wit); }}
  header {{ background:linear-gradient(120deg,#1a1a35,#2a2a50); padding:1.5rem 2rem; border-bottom:1px solid var(--rand); display:flex; align-items:center; gap:1rem; }}
  .logo {{ font-size:1.6rem; font-weight:700; color:var(--geel); letter-spacing:-0.5px; }}
  .meta {{ margin-left:auto; font-size:.82rem; color:var(--sub); text-align:right; }}
  main {{ padding:1.5rem 2rem; max-width:1400px; margin:0 auto; }}
  .stats {{ display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; margin-bottom:2rem; }}
  .stat {{ background:var(--kaart); border:1px solid var(--rand); border-radius:10px; padding:1rem; text-align:center; }}
  .stat .n {{ font-size:2rem; font-weight:700; color:var(--geel); line-height:1; }}
  .stat .l {{ color:var(--sub); font-size:.78rem; margin-top:.25rem; }}
  section {{ margin-bottom:2.5rem; }}
  h2 {{ font-size:1.1rem; font-weight:600; margin-bottom:1rem; padding-bottom:.4rem; border-bottom:2px solid var(--geel); display:inline-block; }}
  .tabel-wrap {{ overflow-x:auto; border-radius:10px; border:1px solid var(--rand); }}
  table {{ width:100%; border-collapse:collapse; }}
  thead tr {{ background:var(--rand); }}
  th {{ padding:.7rem 1rem; text-align:left; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--sub); font-weight:600; }}
  td {{ padding:.65rem 1rem; font-size:.85rem; border-top:1px solid rgba(255,255,255,.04); vertical-align:middle; }}
  tr.rij:hover td {{ background:rgba(255,255,255,.03); }}
  tr.na td {{ opacity:.45; }}
  tr.na {{ background:rgba(233,69,96,.05); }}
  tr.na .prijs {{ text-decoration:line-through; color:var(--sub) !important; }}
  .thumb {{ width:40px; height:40px; object-fit:contain; border-radius:5px; background:#fff; }}
  .thumb-ph {{ width:40px; height:40px; background:var(--rand); border-radius:5px; }}
  .prijs {{ font-weight:700; color:var(--geel); }}
  .promo {{ display:block; font-size:.72rem; color:var(--rood); font-weight:600; }}
  .badge {{ background:var(--rood); color:#fff; font-size:.6rem; padding:.1rem .3rem; border-radius:3px; margin-left:.3rem; font-weight:700; text-transform:uppercase; vertical-align:middle; }}
  .sub {{ color:var(--sub); font-size:.78rem; }}
  .s-ja {{ color:var(--groen); font-size:.78rem; font-weight:600; }}
  .s-nee {{ color:var(--rood); font-size:.78rem; font-weight:600; }}
  .link {{ color:var(--geel); text-decoration:none; font-weight:600; font-size:.82rem; }}
  .winkel-badge {{ display:inline-block; padding:.15rem .5rem; border-radius:4px; font-size:.7rem; font-weight:700; color:#111; letter-spacing:.03em; }}
  .grafiek-grid {{ display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }}
  .grafiek-kaart {{ background:var(--kaart); border:1px solid var(--rand); border-radius:10px; padding:1rem; }}
  .grafiek-kaart h3 {{ font-size:.82rem; color:var(--sub); margin-bottom:.75rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }}
  .leeg {{ color:var(--sub); font-style:italic; font-size:.88rem; padding:.5rem 0; }}
  footer {{ text-align:center; padding:1.5rem; color:var(--sub); font-size:.76rem; border-top:1px solid var(--rand); margin-top:1rem; }}
  .filter-balk {{ display:flex; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }}
  .filter-btn {{ background:var(--kaart); border:1px solid var(--rand); color:var(--sub); padding:.3rem .8rem; border-radius:20px; font-size:.78rem; cursor:pointer; transition:all .15s; }}
  .filter-btn.actief, .filter-btn:hover {{ border-color:var(--geel); color:var(--geel); }}
</style>
</head>
<body>
<header>
  <div>
    <div class="logo">Supermarkt Dashboard</div>
    <div style="font-size:.8rem;color:var(--sub);margin-top:.2rem">{winkel_pills}</div>
  </div>
  <div class="meta">Gegenereerd op<br><strong>{timestamp}</strong></div>
</header>

<main>
  <div class="stats">
    <div class="stat"><div class="n">{totaal}</div><div class="l">Producten</div></div>
    <div class="stat"><div class="n">{beschikbaar}</div><div class="l">Beschikbaar</div></div>
    <div class="stat"><div class="n">{met_actie}</div><div class="l">Met actie</div></div>
    <div class="stat"><div class="n">{met_promo}</div><div class="l">Met promoproijs</div></div>
    <div class="stat"><div class="n">{n_runs}</div><div class="l">Scrape-runs</div></div>
    <div class="stat"><div class="n">{n_winkels}</div><div class="l">Supermarkten</div></div>
  </div>

  <section>
    <h2>Prijstrends (laatste 30 dagen)</h2>
    <div id="trend-sectie">
      <p class="leeg" id="trend-leeg" style="display:none">Nog niet genoeg historische data. Draai de scraper meerdere dagen om trends te zien.</p>
      <div class="grafiek-grid" id="grafiek-grid"></div>
    </div>
  </section>

  <section>
    <h2>Huidige prijzen</h2>
    <div class="filter-balk" id="filter-balk"></div>
    <div class="tabel-wrap">
      <table>
        <thead>
          <tr>
            <th></th><th>Product</th><th>Prijs</th>
            <th>Per eenheid</th><th>Winkel</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody id="product-tabel">
{product_rijen}
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <h2>Scrape-runs</h2>
    <div class="tabel-wrap">
      <table>
        <thead><tr><th>Supermarkt</th><th>Tijdstip</th><th>Producten</th></tr></thead>
        <tbody>
{run_rijen}
        </tbody>
      </table>
    </div>
  </section>
</main>

<footer>
  Supermarkt Dashboard &nbsp;·&nbsp; Alleen voor persoonlijk gebruik
</footer>

<script>
const TRENDDATA = {trenddata_json};
const PRODUCTEN_HTML = document.getElementById('product-tabel').innerHTML;

// Trendgrafieken
const grid = document.getElementById('grafiek-grid');
const entries = Object.entries(TRENDDATA);

if (entries.length === 0) {{
  document.getElementById('trend-leeg').style.display = 'block';
}} else {{
  entries.forEach(([naam, serie]) => {{
    const kaart = document.createElement('div');
    kaart.className = 'grafiek-kaart';
    kaart.innerHTML = '<h3>' + naam + '</h3><canvas height="100"></canvas>';
    grid.appendChild(kaart);
    const ctx = kaart.querySelector('canvas').getContext('2d');
    new Chart(ctx, {{
      type: 'line',
      data: {{
        labels: serie.labels,
        datasets: [{{
          data: serie.data,
          borderColor: serie.kleur,
          backgroundColor: serie.kleur + '22',
          borderWidth: 2,
          pointRadius: 3,
          tension: 0.3,
          fill: true,
        }}]
      }},
      options: {{
        responsive: true,
        plugins: {{ legend: {{ display: false }} }},
        scales: {{
          x: {{ ticks: {{ color: '#7a7a9a', font: {{ size: 10 }} }}, grid: {{ color: '#2a2a45' }} }},
          y: {{ ticks: {{ color: '#7a7a9a', font: {{ size: 10 }}, callback: v => '€' + v.toFixed(2) }}, grid: {{ color: '#2a2a45' }} }}
        }}
      }}
    }});
  }});
}}

// Filter-knoppen per supermarkt
const rijen = document.querySelectorAll('#product-tabel tr');
const winkels = [...new Set([...rijen].map(r => {{
  const b = r.querySelector('.winkel-badge');
  return b ? b.textContent.toLowerCase() : null;
}}).filter(Boolean))];

const balk = document.getElementById('filter-balk');
const alles = document.createElement('button');
alles.className = 'filter-btn actief';
alles.textContent = 'Alles';
alles.onclick = () => {{
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('actief'));
  alles.classList.add('actief');
  rijen.forEach(r => r.style.display = '');
}};
balk.appendChild(alles);

winkels.forEach(winkel => {{
  const btn = document.createElement('button');
  btn.className = 'filter-btn';
  btn.textContent = winkel.toUpperCase();
  btn.onclick = () => {{
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('actief'));
    btn.classList.add('actief');
    rijen.forEach(r => {{
      const b = r.querySelector('.winkel-badge');
      r.style.display = (b && b.textContent.toLowerCase() === winkel) ? '' : 'none';
    }});
  }};
  balk.appendChild(btn);
}});
</script>
</body>
</html>""".format(
        winkel_pills=winkel_pills,
        timestamp=timestamp,
        totaal=len(producten),
        beschikbaar=beschikbaar,
        met_actie=met_actie,
        met_promo=met_promo,
        n_runs=len(runs),
        n_winkels=len(supermarkten),
        product_rijen=product_rijen,
        run_rijen=run_rijen,
        trenddata_json=trenddata_json,
    )


# ─────────────────────────────────────────────
#  HOOFDPROGRAMMA
# ─────────────────────────────────────────────

def main():
    print("=" * 54)
    print("  RAPPORT GENERATOR")
    print("=" * 54)

    db = Database()

    print("  Huidige prijzen ophalen...")
    producten = db.huidige_prijzen()
    print("  -> {} producten".format(len(producten)))

    runs = db.scrape_runs()
    print("  -> {} scrape-runs in geschiedenis".format(len(runs)))

    print("  HTML genereren...")
    html = genereer_html(db)

    with open(RAPPORT_PAD, "w", encoding="utf-8") as f:
        f.write(html)
    print("  Opgeslagen: rapport.html")

    webbrowser.open("file://{}".format(os.path.abspath(RAPPORT_PAD)))
    print("  Browser geopend!")
    print("\n" + "=" * 54 + "\n")


if __name__ == "__main__":
    main()
