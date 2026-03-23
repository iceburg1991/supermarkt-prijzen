<?php

namespace Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test migration structure and database schema.
 */
class MigrationStructureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test supermarkets table structure.
     */
    public function test_supermarkets_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('supermarkets'));

        $columns = ['id', 'identifier', 'name', 'base_url', 'requires_auth', 'enabled', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('supermarkets', $column), "Column {$column} missing");
        }
    }

    /**
     * Test supermarkets table indexes.
     */
    public function test_supermarkets_table_indexes(): void
    {
        $indexes = $this->getIndexes('supermarkets');

        $this->assertContains('identifier', $indexes, 'Missing index on identifier');
        $this->assertContains('enabled', $indexes, 'Missing index on enabled');
    }

    /**
     * Test products table structure.
     */
    public function test_products_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('products'));

        $columns = ['id', 'product_id', 'supermarket', 'name', 'quantity', 'image_url', 'product_url', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "Column {$column} missing");
        }
    }

    /**
     * Test products table indexes.
     */
    public function test_products_table_indexes(): void
    {
        $indexes = $this->getIndexes('products');

        $this->assertContains('supermarket', $indexes, 'Missing index on supermarket');
        $this->assertContains('name', $indexes, 'Missing index on name');
    }

    /**
     * Test products table unique constraint.
     */
    public function test_products_table_unique_constraint(): void
    {
        $indexes = $this->getCompositeIndexes('products');

        // Check for unique constraint on product_id and supermarket
        $uniqueIndexFound = false;
        foreach ($indexes as $indexName => $columns) {
            if (in_array('product_id', $columns) && in_array('supermarket', $columns)) {
                $uniqueIndexFound = true;
                break;
            }
        }

        $this->assertTrue($uniqueIndexFound, 'Missing unique constraint on (product_id, supermarket)');
    }

    /**
     * Test prices table structure.
     */
    public function test_prices_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('prices'));

        $columns = ['id', 'product_id', 'supermarket', 'price_cents', 'promo_price_cents', 'available', 'badge', 'unit_price', 'scraped_at', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('prices', $column), "Column {$column} missing");
        }
    }

    /**
     * Test prices table indexes.
     */
    public function test_prices_table_indexes(): void
    {
        $indexes = $this->getIndexes('prices');

        $this->assertContains('scraped_at', $indexes, 'Missing index on scraped_at');
        $this->assertContains('promo_price_cents', $indexes, 'Missing index on promo_price_cents');

        // Check for composite index on product_id, supermarket, scraped_at
        $compositeIndexes = $this->getCompositeIndexes('prices');
        $compositeIndexFound = false;
        foreach ($compositeIndexes as $indexName => $columns) {
            if (in_array('product_id', $columns) && in_array('supermarket', $columns) && in_array('scraped_at', $columns)) {
                $compositeIndexFound = true;
                break;
            }
        }

        $this->assertTrue($compositeIndexFound, 'Missing composite index on (product_id, supermarket, scraped_at)');
    }

    /**
     * Test scrape_runs table structure.
     */
    public function test_scrape_runs_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('scrape_runs'));

        $columns = ['id', 'supermarket', 'started_at', 'completed_at', 'product_count', 'status', 'error_message', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('scrape_runs', $column), "Column {$column} missing");
        }
    }

    /**
     * Test scrape_runs table indexes.
     */
    public function test_scrape_runs_table_indexes(): void
    {
        $indexes = $this->getIndexes('scrape_runs');

        $this->assertContains('status', $indexes, 'Missing index on status');

        // Check for composite index on supermarket and started_at
        $compositeIndexes = $this->getCompositeIndexes('scrape_runs');
        $compositeIndexFound = false;
        foreach ($compositeIndexes as $indexName => $columns) {
            if (in_array('supermarket', $columns) && in_array('started_at', $columns)) {
                $compositeIndexFound = true;
                break;
            }
        }

        $this->assertTrue($compositeIndexFound, 'Missing composite index on (supermarket, started_at)');
    }

    /**
     * Test categories table structure.
     */
    public function test_categories_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));

        $columns = ['id', 'supermarket', 'category_id', 'name', 'parent_id', 'last_scraped_at', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('categories', $column), "Column {$column} missing");
        }
    }

    /**
     * Test categories table indexes.
     */
    public function test_categories_table_indexes(): void
    {
        $indexes = $this->getIndexes('categories');

        $this->assertContains('supermarket', $indexes, 'Missing index on supermarket');
        $this->assertContains('parent_id', $indexes, 'Missing index on parent_id');
    }

    /**
     * Test normalized_categories table structure.
     */
    public function test_normalized_categories_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('normalized_categories'));

        $columns = ['id', 'name', 'slug', 'parent_id', 'description', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('normalized_categories', $column), "Column {$column} missing");
        }
    }

    /**
     * Test normalized_categories table indexes.
     */
    public function test_normalized_categories_table_indexes(): void
    {
        $indexes = $this->getIndexes('normalized_categories');

        $this->assertContains('slug', $indexes, 'Missing index on slug');
        $this->assertContains('parent_id', $indexes, 'Missing index on parent_id');
    }

    /**
     * Test category_mappings table structure.
     */
    public function test_category_mappings_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('category_mappings'));

        $columns = ['id', 'category_id', 'normalized_category_id', 'mapped_by', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('category_mappings', $column), "Column {$column} missing");
        }
    }

    /**
     * Test category_mappings table indexes.
     */
    public function test_category_mappings_table_indexes(): void
    {
        $indexes = $this->getIndexes('category_mappings');

        $this->assertContains('normalized_category_id', $indexes, 'Missing index on normalized_category_id');
    }

    /**
     * Test product_categories pivot table structure.
     */
    public function test_product_categories_pivot_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('product_categories'));

        $columns = ['product_id', 'category_id', 'created_at'];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('product_categories', $column), "Column {$column} missing");
        }
    }

    /**
     * Get indexes for a table (returns array of column names).
     */
    private function getIndexes(string $table): array
    {
        $indexes = [];
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA index_list({$table})");
            foreach ($results as $result) {
                $indexInfo = DB::select("PRAGMA index_info({$result->name})");
                foreach ($indexInfo as $info) {
                    $indexes[] = $info->name;
                }
            }
        } elseif ($driver === 'mysql') {
            $results = DB::select("SHOW INDEX FROM {$table}");
            foreach ($results as $result) {
                $indexes[] = $result->Column_name;
            }
        }

        return $indexes;
    }

    /**
     * Get composite indexes for a table (returns array of index_name => [columns]).
     */
    private function getCompositeIndexes(string $table): array
    {
        $indexes = [];
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA index_list({$table})");
            foreach ($results as $result) {
                $indexInfo = DB::select("PRAGMA index_info({$result->name})");
                $columns = [];
                foreach ($indexInfo as $info) {
                    $columns[] = $info->name;
                }
                $indexes[$result->name] = $columns;
            }
        } elseif ($driver === 'mysql') {
            $results = DB::select("SHOW INDEX FROM {$table}");
            foreach ($results as $result) {
                if (! isset($indexes[$result->Key_name])) {
                    $indexes[$result->Key_name] = [];
                }
                $indexes[$result->Key_name][] = $result->Column_name;
            }
        }

        return $indexes;
    }
}
