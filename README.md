# 🛒 Supermarkt Prijzen Scraper

Een Laravel applicatie die productprijzen van Nederlandse supermarkten (Albert Heijn en Jumbo) verzamelt, opslaat en analyseert. Met real-time updates via WebSockets wanneer een synchronisatie is afgerond.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4
- **Frontend:** Vue 3, Inertia.js v2, Tailwind CSS v4, TypeScript
- **WebSockets:** Laravel Reverb
- **Database:** SQLite (development), MySQL/PostgreSQL (production)
- **Queue:** Database driver
- **Testing:** PHPUnit

## Vereisten

- PHP 8.4+
- Composer
- Node.js 18+ & npm
- [Laravel Herd](https://herd.laravel.com/) (aanbevolen) of een andere lokale server

## Installatie

```bash
# 1. Clone de repository
git clone <repository-url>
cd supermarkt-prijzen

# 2. Installeer dependencies
composer install
npm install

# 3. Configureer environment
cp .env.example .env
php artisan key:generate

# 4. Database aanmaken en seeden
php artisan migrate
php artisan db:seed
```

## Environment Configuratie

Pas de volgende waarden aan in je `.env` bestand:

```env
# Broadcasting (verplicht voor real-time updates)
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

# Reverb WebSocket server
REVERB_APP_ID=155630
REVERB_APP_KEY=jouw-app-key
REVERB_APP_SECRET=jouw-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

> Als je Reverb nog niet hebt geïnstalleerd, run: `php artisan install:broadcasting`

## Albert Heijn OAuth Token

De Albert Heijn API vereist OAuth authenticatie. Jumbo heeft geen authenticatie nodig.

### Stap 1: Authorization code ophalen

1. Open de Albert Heijn website in je browser
2. Open Developer Tools (F12) → Network tab
3. Log in op je Albert Heijn account
4. Zoek een request naar het OAuth token endpoint
5. Kopieer de `code` parameter uit het request

### Stap 2: Token uitwisselen

```bash
php artisan scraper:auth:setup ah --code=JOUW_AUTHORIZATION_CODE
```

### Stap 3: Token toevoegen aan .env

Het commando geeft een encrypted token terug. Kopieer deze naar je `.env`:

```env
SCRAPER_AH_REFRESH_TOKEN=encrypted:eyJpdiI6...
```

### Stap 4: Testen

```bash
php artisan scrape:ah --max-results=10
```

## Development Starten

Je hebt **4 terminals** nodig:

```bash
# Terminal 1: Vite dev server (hot reload)
npm run dev

# Terminal 2: Queue worker (verwerkt scrape jobs en broadcast events)
php artisan queue:work

# Terminal 3: Reverb WebSocket server (real-time updates naar browser)
php artisan reverb:start

# Terminal 4: Log viewer (optioneel, handig voor debugging)
php artisan pail
```

> **Tip:** Met Laravel Herd is de site automatisch beschikbaar op `https://supermarkt-prijzen.test`. Geen `php artisan serve` nodig.

Of gebruik het `composer run dev` commando dat server, queue, logs en Vite tegelijk start (zonder Reverb).

## Scraper Commando's

```bash
# Alle beschikbare scrapers tonen
php artisan scrape:list

# Albert Heijn scrapen
php artisan scrape:ah

# Jumbo scrapen
php artisan scrape:jumbo

# Alle supermarkten scrapen
php artisan scrape:all

# Met opties
php artisan scrape:ah --max-results=50 --category=12345

# Categorieën scrapen
php artisan scrape:categories

# Categorieën mappen naar genormaliseerde categorieën
php artisan categories:map
```

## Real-Time Broadcasting

Wanneer een scrape job is afgerond, wordt automatisch een `ScrapeRunCompleted` event gebroadcast via Reverb. De dashboard pagina luistert hierop en vernieuwt automatisch de statistieken.

**Hoe het werkt:**

1. User klikt "Sync Nu" → `RunScraper` job wordt naar de queue gestuurd
2. Queue worker voert de scrape uit via `ExecuteScrapeRun`
3. Na afronding wordt `ScrapeRunCompleted` event gebroadcast
4. Reverb stuurt het event via WebSocket naar de browser
5. Vue component vangt het event op en refresht de data

**Relevante bestanden:**

| Bestand | Rol |
|---|---|
| `app/Events/ScrapeRunCompleted.php` | Broadcast event definitie |
| `app/Actions/ExecuteScrapeRun.php` | Dispatcht het event na succesvolle scrape |
| `routes/channels.php` | Channel authorization (private channel) |
| `resources/js/app.ts` | Echo/Reverb client configuratie |
| `resources/js/pages/Supermarkets/Dashboard.vue` | Luistert naar events en refresht UI |

## Testen

```bash
# Alle tests draaien
php artisan test --compact

# Specifiek test bestand
php artisan test --compact tests/Feature/Scraper/ExecuteScrapeRunTest.php

# Filter op test naam
php artisan test --compact --filter=testProductUpsert

# PHP code formatting checken
vendor/bin/pint --test

# Frontend formatting en linting
npm run lint:check
npm run format:check
npm run types:check

# Volledige CI check
composer run ci:check
```

## Project Structuur

Het project volgt een Laravel-friendly DDD structuur:

```
app/
├── Actions/                    # Use case orchestration
│   └── ExecuteScrapeRun.php
├── Console/Commands/Scraper/   # Artisan commando's
├── Contracts/Scraper/          # Interfaces
├── DataTransferObjects/Scraper/# Immutable DTOs
├── Enums/                      # Type-safe enumerations
├── Events/                     # Domain events (incl. broadcasting)
├── Exceptions/Scraper/         # Custom exceptions
├── Http/
│   ├── Controllers/            # Request handlers
│   └── Scrapers/               # API client implementaties
│       ├── BaseScraper.php     # Abstract base (rate limiting, retry)
│       ├── AhScraper.php       # Albert Heijn API
│       └── JumboScraper.php    # Jumbo API
├── Jobs/                       # Queue jobs
│   └── RunScraper.php
├── Models/                     # Eloquent models
├── Repositories/Scraper/       # Data access layer
└── Services/Scraper/           # Domain services
    ├── ScraperRegistry.php     # Auto-discovery van scrapers
    ├── TokenManager.php        # OAuth token management
    └── CategoryNormalizer.php  # Cross-supermarkt categorieën
```

## Nieuwe Supermarkt Toevoegen

1. Maak een nieuwe scraper class in `app/Http/Scrapers/` die `BaseScraper` extend en `SupermarketScraperInterface` implementeert
2. Voeg de supermarkt toe aan de `supermarkets` database tabel
3. Klaar — de `ScraperRegistry` ontdekt de scraper automatisch

## Historische Data Importeren

Als je data hebt van het legacy Python script:

```bash
php artisan import:python-data --file=pad/naar/prijzen.db
```

## Troubleshooting

**"Kon sync niet starten"** — Check of de queue worker draait: `php artisan queue:work`

**Broadcasting werkt niet** — Zorg dat Reverb draait: `php artisan reverb:start`. Check ook of `BROADCAST_CONNECTION=reverb` in je `.env` staat.

**AH authentication failed** — Je refresh token is verlopen. Haal een nieuwe op via `php artisan scraper:auth:setup ah --code=NIEUWE_CODE`

**Scraper not found** — Run `php artisan scrape:list` om te checken of scrapers worden ontdekt. Check of de bestanden in `app/Http/Scrapers/` staan.

**Queue worker pakt oude code op** — Herstart de queue worker na code wijzigingen (Ctrl+C en opnieuw starten).
