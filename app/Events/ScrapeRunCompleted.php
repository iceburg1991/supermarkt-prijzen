<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ScrapeRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a scrape run completes successfully.
 */
class ScrapeRunCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ScrapeRun $scrapeRun
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // Broadcast to a private channel for authenticated users
        return new PrivateChannel('scrape-runs');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'scrape.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'scrape_run_id' => $this->scrapeRun->id,
            'supermarket' => $this->scrapeRun->supermarket,
            'status' => $this->scrapeRun->status->value,
            'products_scraped' => $this->scrapeRun->products_scraped,
            'duration_seconds' => $this->scrapeRun->duration_seconds,
            'completed_at' => $this->scrapeRun->completed_at?->toIso8601String(),
        ];
    }
}
