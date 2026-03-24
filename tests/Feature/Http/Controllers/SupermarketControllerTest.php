<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Jobs\RunScraper;
use App\Models\Supermarket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Test SupermarketController sync functionality.
 */
class SupermarketControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test sync dispatches job successfully.
     */
    public function test_sync_dispatches_job_successfully(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $supermarket = Supermarket::factory()->create([
            'identifier' => 'ah',
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('supermarkets.sync', ['identifier' => 'ah']));

        $response->assertRedirect(route('supermarkets.dashboard'));
        $response->assertSessionHas('success');

        Queue::assertPushed(RunScraper::class);
    }

    /**
     * Test sync shows warning when many jobs are pending.
     */
    public function test_sync_shows_warning_when_many_jobs_pending(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $supermarket = Supermarket::factory()->create([
            'identifier' => 'ah',
            'enabled' => true,
        ]);

        // Create 10 pending jobs in database
        for ($i = 0; $i < 10; $i++) {
            \DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => json_encode(['job' => 'test']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ]);
        }

        $response = $this->actingAs($user)
            ->post(route('supermarkets.sync', ['identifier' => 'ah']));

        $response->assertRedirect(route('supermarkets.dashboard'));
        $response->assertSessionHas('warning');

        Queue::assertPushed(RunScraper::class);
    }

    /**
     * Test sync shows error when queue worker is not running.
     */
    public function test_sync_shows_error_when_queue_worker_not_running(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $supermarket = Supermarket::factory()->create([
            'identifier' => 'ah',
            'enabled' => true,
        ]);

        // Create old pending job (older than 5 minutes)
        \DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'test']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(10)->timestamp,
            'created_at' => now()->subMinutes(10)->timestamp,
        ]);

        $response = $this->actingAs($user)
            ->post(route('supermarkets.sync', ['identifier' => 'ah']));

        $response->assertRedirect(route('supermarkets.dashboard'));
        $response->assertSessionHas('error');

        Queue::assertPushed(RunScraper::class);
    }

    /**
     * Test sync fails for disabled supermarket.
     */
    public function test_sync_fails_for_disabled_supermarket(): void
    {
        $user = User::factory()->create();
        $supermarket = Supermarket::factory()->create([
            'identifier' => 'ah',
            'enabled' => false,
        ]);

        $response = $this->actingAs($user)
            ->post(route('supermarkets.sync', ['identifier' => 'ah']));

        $response->assertNotFound();
    }

    /**
     * Test sync requires authentication.
     */
    public function test_sync_requires_authentication(): void
    {
        $supermarket = Supermarket::factory()->create([
            'identifier' => 'ah',
            'enabled' => true,
        ]);

        $response = $this->post(route('supermarkets.sync', ['identifier' => 'ah']));

        $response->assertRedirect(route('login'));
    }
}
