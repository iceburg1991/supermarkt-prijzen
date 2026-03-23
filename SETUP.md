# Supermarket Price Scraper - Setup Guide

This guide will walk you through setting up the supermarket price scraper, including obtaining OAuth tokens for Albert Heijn API authentication.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Database Setup](#database-setup)
4. [Albert Heijn OAuth Setup](#albert-heijn-oauth-setup)
5. [Configuration](#configuration)
6. [Testing the Setup](#testing-the-setup)
7. [Scheduling](#scheduling)
8. [Troubleshooting](#troubleshooting)

## Prerequisites

- PHP 8.4 or higher
- Composer
- MySQL or SQLite database
- Redis (recommended for production)
- Laravel 12

## Installation

1. Clone the repository and install dependencies:

```bash
composer install
```

2. Copy the environment file:

```bash
cp .env.example .env
```

3. Generate application key:

```bash
php artisan key:generate
```

## Database Setup

1. Run migrations to create the required tables:

```bash
php artisan migrate
```

2. Seed the database with initial data (supermarkets and normalized categories):

```bash
php artisan db:seed
```

3. (Optional) Import historical data from Python SQLite database:

```bash
php artisan import:python-data --file=python_scripts/prijzen\ kopie.db
```

## Albert Heijn OAuth Setup

Albert Heijn API requires OAuth authentication. Follow these steps to obtain a refresh token:

### Step 1: Get Authorization Code

1. Open your browser and navigate to the Albert Heijn website
2. Open Browser DevTools (F12 or Right-click → Inspect)
3. Go to the **Network** tab
4. Log in to your Albert Heijn account
5. Look for a request to `/mobile-auth/v1/auth/token` or similar OAuth endpoint
6. In the request payload or response, find the `code` parameter
7. Copy this authorization code (it will be a long string)

**Alternative Method (Using Mobile App):**

1. Install the Albert Heijn mobile app on your device
2. Use a network proxy tool (like Charles Proxy or mitmproxy) to intercept HTTPS traffic
3. Log in to the app
4. Capture the OAuth authorization code from the intercepted requests

### Step 2: Exchange Code for Refresh Token

Run the setup command with your authorization code:

```bash
php artisan scraper:auth:setup ah --code=YOUR_AUTHORIZATION_CODE_HERE
```

**Example:**

```bash
php artisan scraper:auth:setup ah --code=abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
```

### Step 3: Add Refresh Token to .env

The command will output an encrypted refresh token. Copy the entire line and add it to your `.env` file:

```env
SCRAPER_AH_REFRESH_TOKEN=encrypted:eyJpdiI6IjEyMzQ1Njc4OTBhYmNkZWYiLCJ2YWx1ZSI6ImFiY2RlZmdoaWprbG1ub3BxcnN0dXZ3eHl6IiwibWFjIjoiMTIzNDU2Nzg5MGFiY2RlZiIsInRhZyI6IiJ9
```

**Important Notes:**

- The refresh token is encrypted using Laravel's encryption
- Keep this token secure and never commit it to version control
- The refresh token is long-lived and rarely expires
- Access tokens are automatically refreshed using this refresh token
- Access tokens are cached in Redis/database (not stored in `.env`)

### Step 4: Test Authentication

Test that authentication works:

```bash
php artisan scrape:ah --max-results=10
```

If successful, you should see products being scraped.

## Configuration

### Required Configuration

Edit your `.env` file and configure the following:

```env
# Albert Heijn (REQUIRED for AH scraping)
SCRAPER_AH_REFRESH_TOKEN=encrypted:your_encrypted_token_here

# Jumbo (no authentication required)
SCRAPER_JUMBO_BASE_URL=https://mobileapi.jumbo.com/v17
```

### Optional Configuration

```env
# Rate Limiting (milliseconds between requests)
SCRAPER_AH_RATE_LIMIT_DELAY=600
SCRAPER_JUMBO_RATE_LIMIT_DELAY=600

# Retry Settings
SCRAPER_MAX_RETRIES=3
SCRAPER_TIMEOUT=10

# Debug Logging (WARNING: generates large log files)
SCRAPER_DEBUG=false

# Scheduling
SCRAPER_SCHEDULE_ENABLED=true
SCRAPER_SCHEDULE_TIME=02:00
SCRAPER_SCHEDULE_TIMEZONE=Europe/Amsterdam

# Notifications
SCRAPER_NOTIFICATIONS_ENABLED=true
SCRAPER_NOTIFY_MAIL=false
SCRAPER_NOTIFY_MAIL_TO=admin@example.com
SCRAPER_NOTIFY_SLACK=false
SCRAPER_NOTIFY_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Analytics Caching (recommended: use Redis in production)
CACHE_STORE=redis
SCRAPER_ANALYTICS_CACHE_ENABLED=true
SCRAPER_ANALYTICS_CACHE_TTL=3600
```

### Cache Driver Setup

For production environments with multiple servers, use Redis:

```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

For single-server or development environments, database cache is sufficient:

```env
CACHE_STORE=database
```

## Testing the Setup

### Test Individual Scrapers

```bash
# Test Albert Heijn scraper
php artisan scrape:ah --max-results=10

# Test Jumbo scraper
php artisan scrape:jumbo --max-results=10
```

### Test All Scrapers

```bash
php artisan scrape:all
```

### List Available Scrapers

```bash
php artisan scrape:list
```

### Check Logs

Monitor scraper activity in the logs:

```bash
# General scraper logs
tail -f storage/logs/scraper.log

# Error logs only
tail -f storage/logs/scraper-errors.log

# Debug logs (if SCRAPER_DEBUG=true)
tail -f storage/logs/scraper-debug.log
```

## Scheduling

The scraper can run automatically on a schedule using Laravel's task scheduler.

### Enable Scheduling

1. Ensure scheduling is enabled in `.env`:

```env
SCRAPER_SCHEDULE_ENABLED=true
SCRAPER_SCHEDULE_TIME=02:00
```

2. Add the Laravel scheduler to your crontab:

```bash
crontab -e
```

Add this line:

```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### Manual Scheduling

Alternatively, you can set up a cron job to run the scraper directly:

```cron
# Run scraper daily at 2:00 AM
0 2 * * * cd /path/to/your/project && php artisan scrape:all >> /dev/null 2>&1
```

## Troubleshooting

### Authentication Errors

**Problem:** `Authentication failed: 401` or `Invalid refresh token`

**Solution:**
1. Your refresh token may have expired
2. Run `php artisan scraper:auth:setup ah --code=NEW_CODE` to obtain a new refresh token
3. Update the `SCRAPER_AH_REFRESH_TOKEN` in your `.env` file

### Rate Limiting Errors

**Problem:** `Rate limit exceeded: 429`

**Solution:**
1. Increase the rate limit delay in `.env`:
   ```env
   SCRAPER_AH_RATE_LIMIT_DELAY=1000
   SCRAPER_JUMBO_RATE_LIMIT_DELAY=1000
   ```
2. The scraper will automatically retry with exponential backoff

### Token Refresh Failures

**Problem:** `Token refresh failed`

**Solution:**
1. Check that your refresh token is correctly formatted in `.env`
2. Ensure it starts with `encrypted:`
3. Verify the token hasn't been corrupted (no line breaks or extra spaces)
4. Obtain a new refresh token using `scraper:auth:setup`

### No Products Scraped

**Problem:** Scraper runs but no products are saved

**Solution:**
1. Check logs for errors: `tail -f storage/logs/scraper-errors.log`
2. Enable debug logging: `SCRAPER_DEBUG=true`
3. Check debug logs: `tail -f storage/logs/scraper-debug.log`
4. Verify database connection and migrations

### Cache Issues

**Problem:** Analytics queries return stale data

**Solution:**
1. Clear the cache: `php artisan cache:clear`
2. Disable analytics caching temporarily: `SCRAPER_ANALYTICS_CACHE_ENABLED=false`
3. Check Redis connection if using Redis cache

### Notification Failures

**Problem:** Not receiving failure notifications

**Solution:**
1. Verify notification settings in `.env`:
   ```env
   SCRAPER_NOTIFICATIONS_ENABLED=true
   SCRAPER_NOTIFY_MAIL=true
   SCRAPER_NOTIFY_MAIL_TO=your@email.com
   ```
2. Check mail configuration (MAIL_* variables)
3. Test mail sending: `php artisan tinker` then `Mail::raw('Test', fn($m) => $m->to('your@email.com')->subject('Test'));`
4. Check logs for notification errors

### Database Errors

**Problem:** Migration or query errors

**Solution:**
1. Ensure database is properly configured in `.env`
2. Run migrations: `php artisan migrate:fresh --seed`
3. Check database permissions
4. Verify foreign key constraints are supported (MySQL InnoDB)

## Advanced Configuration

### Category Mapping

Map supermarket-specific categories to normalized categories:

```bash
# Scrape categories from all supermarkets
php artisan scrape:categories

# Map categories interactively
php artisan categories:map

# Auto-map categories based on name matching
php artisan categories:map --auto
```

### Custom Scraper Development

To add a new supermarket scraper:

1. Create a new scraper class implementing `SupermarketScraperInterface`
2. Extend `BaseScraper` for common functionality
3. The scraper will be auto-discovered by `ScraperRegistry`
4. No configuration changes required

See `app/Infrastructure/Scraper/Http/AhScraper.php` for an example.

## Future Enhancements

Planned improvements:

- **Web-based OAuth flow**: No manual DevTools steps required
- **Real-time monitoring dashboard**: View scrape runs and metrics
- **Price alerts**: Notify when products go on sale
- **API endpoints**: Expose data via REST API
- **Additional supermarkets**: Lidl, Aldi, Plus, etc.

## Support

For issues or questions:

1. Check the logs in `storage/logs/scraper*.log`
2. Review the configuration in `config/scrapers.php`
3. Run `php artisan scrape:list` to verify scrapers are registered
4. Enable debug logging for detailed troubleshooting

---

**Last Updated:** 2024
**Laravel Version:** 12
**PHP Version:** 8.4
