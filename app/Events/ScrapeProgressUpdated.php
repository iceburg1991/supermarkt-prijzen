<?php

namespace App\Events;

use App\Models\ScrapeRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast scrape progress updates to the frontend.
 */
class ScrapeProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ScrapeRun $scrapeRun,
        public int $productsScraped,
        public int $currentPage,
        public ?int $totalPages = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('scrape-progress');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'progress.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $percentage = null;
        if ($this->totalPages && $this->totalPages > 0) {
            $percentage = min(100, round(($this->currentPage / $this->totalPages) * 100));
        }

        return [
            'scrape_run_id' => $this->scrapeRun->id,
            'supermarket' => $this->scrapeRun->supermarket,
            'products_scraped' => $this->productsScraped,
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'percentage' => $percentage,
        ];
    }
}
