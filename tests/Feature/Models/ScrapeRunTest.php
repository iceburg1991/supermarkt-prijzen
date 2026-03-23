<?php

namespace Feature\Models;

use App\Models\ScrapeRun;
use App\Models\Supermarket;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test ScrapeRun model relationships, casts, and methods.
 */
class ScrapeRunTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test scrape run can be created with required fields.
     */
    public function test_scrape_run_can_be_created(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::create([
            'supermarket' => 'ah',
            'started_at' => now(),
            'status' => 'running',
        ]);

        $this->assertDatabaseHas('scrape_runs', [
            'supermarket' => 'ah',
            'status' => 'running',
        ]);
    }

    /**
     * Test started_at is cast to datetime.
     */
    public function test_started_at_cast_to_datetime(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::factory()->create([
            'supermarket' => 'ah',
            'started_at' => '2024-01-15 10:00:00',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $scrapeRun->started_at);
        $this->assertEquals('2024-01-15 10:00:00', $scrapeRun->started_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test completed_at is cast to datetime.
     */
    public function test_completed_at_cast_to_datetime(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::factory()->create([
            'supermarket' => 'ah',
            'completed_at' => '2024-01-15 11:00:00',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $scrapeRun->completed_at);
        $this->assertEquals('2024-01-15 11:00:00', $scrapeRun->completed_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test product_count is cast to integer.
     */
    public function test_product_count_cast_to_integer(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::factory()->create([
            'supermarket' => 'ah',
            'product_count' => '150',
        ]);

        $this->assertIsInt($scrapeRun->product_count);
        $this->assertEquals(150, $scrapeRun->product_count);
    }

    /**
     * Test supermarketModel relationship works correctly.
     */
    public function test_supermarket_model_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah', 'name' => 'Albert Heijn']);
        $scrapeRun = ScrapeRun::factory()->create(['supermarket' => 'ah']);

        $this->assertInstanceOf(Supermarket::class, $scrapeRun->supermarketModel);
        $this->assertEquals('Albert Heijn', $scrapeRun->supermarketModel->name);
    }

    /**
     * Test markCompleted updates status and sets completed_at.
     */
    public function test_mark_completed_updates_status_and_timestamp(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $scrapeRun = ScrapeRun::factory()->create([
            'supermarket' => 'ah',
            'status' => 'running',
            'product_count' => 0,
            'completed_at' => null,
        ]);

        $scrapeRun->markCompleted(150);

        $this->assertEquals('completed', $scrapeRun->status);
        $this->assertEquals(150, $scrapeRun->product_count);
        $this->assertNotNull($scrapeRun->completed_at);
        $this->assertInstanceOf(CarbonImmutable::class, $scrapeRun->completed_at);
    }

    /**
     * Test markFailed updates status and sets error message.
     */
    public function test_mark_failed_updates_status_and_error_message(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $scrapeRun = ScrapeRun::factory()->create([
            'supermarket' => 'ah',
            'status' => 'running',
            'error_message' => null,
            'completed_at' => null,
        ]);

        $scrapeRun->markFailed('API connection timeout');

        $this->assertEquals('failed', $scrapeRun->status);
        $this->assertEquals('API connection timeout', $scrapeRun->error_message);
        $this->assertNotNull($scrapeRun->completed_at);
        $this->assertInstanceOf(CarbonImmutable::class, $scrapeRun->completed_at);
    }

    /**
     * Test default status is running.
     */
    public function test_default_status_is_running(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::create([
            'supermarket' => 'ah',
            'started_at' => now(),
        ]);

        $this->assertEquals('running', $scrapeRun->fresh()->status);
    }

    /**
     * Test default product_count is 0.
     */
    public function test_default_product_count_is_zero(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::create([
            'supermarket' => 'ah',
            'started_at' => now(),
        ]);

        $this->assertEquals(0, $scrapeRun->product_count);
    }

    /**
     * Test foreign key constraint on supermarket.
     */
    public function test_foreign_key_constraint_on_supermarket(): void
    {
        $this->expectException(QueryException::class);

        // Try to create scrape run with non-existent supermarket
        ScrapeRun::create([
            'supermarket' => 'nonexistent',
            'started_at' => now(),
        ]);
    }

    /**
     * Test cascade delete when supermarket is deleted.
     */
    public function test_cascade_delete_when_supermarket_deleted(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $scrapeRun = ScrapeRun::factory()->create(['supermarket' => 'ah']);

        $this->assertDatabaseHas('scrape_runs', ['id' => $scrapeRun->id]);

        $supermarket->delete();

        $this->assertDatabaseMissing('scrape_runs', ['id' => $scrapeRun->id]);
    }

    /**
     * Test status can only be running, completed, or failed.
     */
    public function test_status_enum_values(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $scrapeRun = ScrapeRun::factory()->create(['supermarket' => 'ah', 'status' => 'running']);
        $this->assertEquals('running', $scrapeRun->status);

        $scrapeRun->update(['status' => 'completed']);
        $this->assertEquals('completed', $scrapeRun->status);

        $scrapeRun->update(['status' => 'failed']);
        $this->assertEquals('failed', $scrapeRun->status);
    }
}
