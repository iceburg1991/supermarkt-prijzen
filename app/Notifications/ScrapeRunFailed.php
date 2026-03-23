<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ScrapeRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a scrape run fails.
 *
 * Supports mail and Slack channels based on configuration.
 */
class ScrapeRunFailed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly ScrapeRun $scrapeRun
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('scrapers.notifications.channels.mail', false)) {
            $channels[] = 'mail';
        }

        if (config('scrapers.notifications.channels.slack', false)) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $duration = $this->scrapeRun->completed_at
            ? $this->scrapeRun->started_at->diffInSeconds($this->scrapeRun->completed_at)
            : null;

        return (new MailMessage)
            ->error()
            ->subject("Scrape Run Failed: {$this->scrapeRun->supermarket}")
            ->line("A scrape run for {$this->scrapeRun->supermarket} has failed.")
            ->line("**Error:** {$this->scrapeRun->error_message}")
            ->line("**Started At:** {$this->scrapeRun->started_at->format('Y-m-d H:i:s')}")
            ->line('**Duration:** '.($duration ? "{$duration} seconds" : 'N/A'))
            ->line("**Products Scraped:** {$this->scrapeRun->product_count}")
            ->action('View Logs', url('/'))
            ->line('Please investigate the error and take appropriate action.');
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $duration = $this->scrapeRun->completed_at
            ? $this->scrapeRun->started_at->diffInSeconds($this->scrapeRun->completed_at)
            : null;

        return (new SlackMessage)
            ->error()
            ->content("Scrape Run Failed: {$this->scrapeRun->supermarket}")
            ->attachment(function ($attachment) use ($duration) {
                $attachment->title('Scrape Run Details')
                    ->fields([
                        'Supermarket' => $this->scrapeRun->supermarket,
                        'Error' => $this->scrapeRun->error_message,
                        'Started At' => $this->scrapeRun->started_at->format('Y-m-d H:i:s'),
                        'Duration' => $duration ? "{$duration} seconds" : 'N/A',
                        'Products Scraped' => $this->scrapeRun->product_count,
                    ])
                    ->color('danger');
            });
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'scrape_run_id' => $this->scrapeRun->id,
            'supermarket' => $this->scrapeRun->supermarket,
            'error_message' => $this->scrapeRun->error_message,
            'started_at' => $this->scrapeRun->started_at->toIso8601String(),
            'product_count' => $this->scrapeRun->product_count,
        ];
    }
}
