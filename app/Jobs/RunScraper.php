<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunScraper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $supermarket
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $command = match ($this->supermarket) {
            'ah' => 'scrape:ah',
            'jumbo' => 'scrape:jumbo',
            default => null,
        };

        if ($command) {
            Log::channel('scraper')->info('Starting scraper job', [
                'supermarket' => $this->supermarket,
                'command' => $command,
            ]);

            Artisan::call($command);

            Log::channel('scraper')->info('Scraper job completed', [
                'supermarket' => $this->supermarket,
            ]);
        }
    }
}
