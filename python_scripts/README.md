# Python Scripts - Deprecated

## Overview

This directory contains the original Python-based supermarket price scraper scripts. These scripts have been **deprecated** and replaced by a Laravel implementation that provides the same functionality with improved architecture, maintainability, and extensibility.

**Status:** ⚠️ **DEPRECATED** - These scripts are preserved for reference only and should not be used for new development.

## Migration to Laravel

The Python scripts have been fully migrated to Laravel with the following mappings:

### Script Mappings

| Python Script | Laravel Component | Description |
|--------------|-------------------|-------------|
| `ah_scraper_v5 kopie.py` | `App\Infrastructure\Scraper\Http\AhScraper` | Albert Heijn API scraper with OAuth authentication |
| `jumbo_scraper kopie.py` | `App\Infrastructure\Scraper\Http\JumboScraper` | Jumbo API scraper (no authentication required) |
| `database kopie.py` | Eloquent Models (`Product`, `Price`, `ScrapeRun`, `Category`) | Database operations and ORM |
| `ah_api_test kopie.py` | `App\Domain\Scraper\Services\TokenManager` | OAuth token management and testing |
| `generate_html_report_all kopie.py` | `App\Infrastructure\Scraper\Repositories\AnalyticsRepository` | Analytics and reporting |
| `report_generator kopie.py` | `App\Infrastructure\Scraper\Repositories\AnalyticsRepository` | Report generation |

### Data Migration

The SQLite database (`prijzen kopie.db`) can be imported into the Laravel application using:

```bash
php artisan import:python-data --file=python_scripts/prijzen\ kopie.db
```

This command will:
- Import all products from the Python database
- Preserve historical price records
- Map Python schema fields to Laravel schema fields
- Handle duplicate products by updating existing records
- Preserve original `scraped_at` timestamps

## Laravel Commands

The Laravel implementation provides the following Artisan commands to replace Python script functionality:

### Scraping Commands

```bash
# Scrape Albert Heijn products
php artisan scrape:ah

# Scrape Albert Heijn products by category
php artisan scrape:ah --category=12345

# Scrape Albert Heijn with result limit
php artisan scrape:ah --max-results=500

# Scrape Jumbo products
php artisan scrape:jumbo

# Scrape Jumbo products by category
php artisan scrape:jumbo --category=67890

# Scrape all enabled supermarkets
php artisan scrape:all

# List all registered scrapers
php artisan scrape:list
```

### Authentication Commands

```bash
# Set up Albert Heijn OAuth authentication
php artisan scraper:auth:setup ah --code=YOUR_AUTH_CODE

# The command will output an encrypted refresh token to add to .env
```

### Category Management Commands

```bash
# Scrape categories from all supermarkets
php artisan scrape:categories

# Map supermarket categories to normalized categories
php artisan categories:map

# Auto-map categories based on name matching
php artisan categories:map --auto
```

### Data Import Commands

```bash
# Import historical data from Python SQLite database
php artisan import:python-data --file=python_scripts/prijzen\ kopie.db
```

## Architecture Improvements

The Laravel implementation provides several improvements over the Python scripts:

### 1. Domain-Driven Design (DDD)

- **Domain Layer**: Core business logic and rules
- **Application Layer**: Use case orchestration
- **Infrastructure Layer**: External API communication and data persistence

### 2. Extensibility

- **Interface-based scrapers**: Easy to add new supermarkets
- **Registry pattern**: Auto-discovery of scraper implementations
- **No code changes required**: New scrapers are automatically detected

### 3. Error Handling

- **Comprehensive logging**: Separate channels for errors, debug, and general logs
- **Retry logic**: Exponential backoff for failed requests
- **Graceful degradation**: Continue scraping even if individual products fail

### 4. Token Management

- **Automatic refresh**: Access tokens are refreshed automatically when expired
- **Secure storage**: Refresh tokens encrypted in `.env`, access tokens cached
- **Multi-server ready**: Uses Redis cache for distributed environments

### 5. Category System

- **Normalized categories**: Cross-supermarket product comparisons
- **Automatic mapping**: AI-assisted category mapping
- **Manual override**: Review and approve automatic mappings

### 6. Analytics

- **Optimized queries**: Database aggregations instead of in-memory operations
- **Caching layer**: Expensive queries cached with configurable TTL
- **Performance monitoring**: Track query execution times

### 7. Scheduling

- **Laravel scheduler**: Automated daily scraping
- **Overlap prevention**: Prevent concurrent scrape runs
- **Notifications**: Alert on failures via mail or Slack

## Running Python Scripts (Reference Only)

If you need to run the original Python scripts for reference or comparison:

### Prerequisites

```bash
# Install Python dependencies
pip install requests sqlite3 beautifulsoup4
```

### Running Scripts

```bash
# Albert Heijn scraper
python python_scripts/ah_scraper_v5\ kopie.py

# Jumbo scraper
python python_scripts/jumbo_scraper\ kopie.py

# Generate HTML report
python python_scripts/generate_html_report_all\ kopie.py
```

### Configuration

The Python scripts use hardcoded configuration. For the Laravel implementation, use environment variables in `.env`:

```env
# Albert Heijn Configuration
SCRAPER_AH_BASE_URL=https://api.ah.nl
SCRAPER_AH_OAUTH_URL=https://api.ah.nl/mobile-auth/v1/auth
SCRAPER_AH_CLIENT_ID=appie-ios
SCRAPER_AH_REFRESH_TOKEN=encrypted:...
SCRAPER_AH_RATE_LIMIT_DELAY=600

# Jumbo Configuration
SCRAPER_JUMBO_BASE_URL=https://mobileapi.jumbo.com/v17
SCRAPER_JUMBO_RATE_LIMIT_DELAY=600

# General Settings
SCRAPER_MAX_RETRIES=3
SCRAPER_TIMEOUT=10
SCRAPER_DEBUG=false
```

## Why Laravel?

The migration to Laravel provides:

1. **Better maintainability**: Clear separation of concerns with DDD
2. **Easier testing**: PHPUnit integration with feature and unit tests
3. **Scalability**: Queue jobs, caching, and database optimization
4. **Security**: Laravel's built-in security features and encryption
5. **Extensibility**: Easy to add new supermarkets without modifying existing code
6. **Integration**: Seamless integration with existing Laravel application
7. **Monitoring**: Comprehensive logging and error tracking
8. **Scheduling**: Built-in task scheduling with overlap prevention

## Support

For questions or issues with the Laravel implementation:

1. Check the main project README
2. Review the `config/scrapers.php` configuration
3. Check logs in `storage/logs/scraper*.log`
4. Run `php artisan scrape:list` to see available scrapers

## Future Enhancements

The Laravel implementation is designed to support future enhancements:

- Web-based OAuth login flow (no manual DevTools steps)
- Real-time price monitoring with webhooks
- Price prediction using machine learning
- API endpoints for external integrations
- Dashboard for monitoring scrape runs
- Additional supermarket integrations (Lidl, Aldi, etc.)

---

**Last Updated:** 2024
**Migration Status:** ✅ Complete
**Python Scripts Status:** ⚠️ Deprecated (preserved for reference)
