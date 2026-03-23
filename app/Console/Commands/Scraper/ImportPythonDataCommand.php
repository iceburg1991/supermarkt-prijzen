<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Domain\Scraper\ValueObjects\ProductData;
use App\Infrastructure\Scraper\Repositories\ProductRepository;
use App\Models\Price;
use App\Models\ScrapeRun;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('import:python-data {--file=python_scripts/prijzen kopie.db : Path to SQLite database file}')]
#[Description('Import historical data from Python SQLite database')]
class ImportPythonDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProductRepository $productRepository): int
    {
        $filePath = $this->option('file');

        // Check if file exists
        if (! file_exists($filePath)) {
            $this->error("✗ SQLite file not found: {$filePath}");

            return Command::FAILURE;
        }

        $this->info('Starting Python data import...');
        $this->info("Source: {$filePath}");
        $this->newLine();

        try {
            // Connect to SQLite database
            $sqlite = new \PDO("sqlite:{$filePath}");
            $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Import products
            $this->info('Importing products...');
            $productsImported = $this->importProducts($sqlite, $productRepository);
            $this->info("✓ Imported {$productsImported} products");

            // Import prices
            $this->info('Importing prices...');
            $pricesImported = $this->importPrices($sqlite);
            $this->info("✓ Imported {$pricesImported} price records");

            // Import scrape runs
            $this->info('Importing scrape runs...');
            $runsImported = $this->importScrapeRuns($sqlite);
            $this->info("✓ Imported {$runsImported} scrape runs");

            $this->newLine();
            $this->info('✓ Import completed successfully!');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("✗ Import failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Import products from Python database.
     */
    private function importProducts(\PDO $sqlite, ProductRepository $productRepository): int
    {
        $stmt = $sqlite->query('SELECT * FROM products');
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        $imported = 0;

        foreach ($products as $product) {
            // Map Python field names to Laravel ProductData value object
            $productData = new ProductData(
                productId: $product['product_id'],
                supermarket: $product['supermarkt'],
                name: $product['naam'],
                quantity: $product['hoeveelheid'] ?? '',
                priceCents: 0, // Will be set from prices table
                promoPriceCents: 0,
                available: true,
                badge: '',
                unitPrice: '',
                imageUrl: $product['afbeelding_url'] ?? '',
                productUrl: $product['product_url'] ?? '',
                scrapedAt: Carbon::now()
            );

            $productRepository->upsert($productData);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $imported;
    }

    /**
     * Import prices from Python database.
     */
    private function importPrices(\PDO $sqlite): int
    {
        $stmt = $sqlite->query('SELECT * FROM prices ORDER BY scraped_at ASC');
        $prices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $bar = $this->output->createProgressBar(count($prices));
        $bar->start();

        $imported = 0;

        foreach ($prices as $price) {
            // Map Python field names to Laravel field names
            // Preserve original scraped_at timestamp
            Price::create([
                'product_id' => $price['product_id'],
                'supermarket' => $price['supermarkt'],
                'price_cents' => $price['prijs_cent'],
                'promo_price_cents' => $price['promo_prijs_cent'] ?: 0,
                'available' => (bool) $price['beschikbaar'],
                'badge' => $price['badge'] ?? '',
                'unit_price' => $price['eenheidsprijs'] ?? '',
                'scraped_at' => $price['scraped_at'],
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $imported;
    }

    /**
     * Import scrape runs from Python database.
     */
    private function importScrapeRuns(\PDO $sqlite): int
    {
        $stmt = $sqlite->query('SELECT * FROM scrape_runs ORDER BY gestart_op ASC');
        $runs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $bar = $this->output->createProgressBar(count($runs));
        $bar->start();

        $imported = 0;

        foreach ($runs as $run) {
            // Map Python field names to Laravel field names
            ScrapeRun::create([
                'supermarket' => $run['supermarkt'],
                'started_at' => $run['gestart_op'],
                'completed_at' => $run['gestart_op'], // Python didn't track completion time
                'product_count' => $run['aantal'],
                'status' => 'completed', // All historical runs are completed
                'error_message' => null,
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $imported;
    }
}
