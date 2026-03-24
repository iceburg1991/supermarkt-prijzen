<?php

namespace App\Enums;

enum ScrapeStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Running => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    /**
     * Check if scrape is finished.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed]);
    }

    /**
     * Get all status values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
