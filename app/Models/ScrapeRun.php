<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ScrapeRun model representing a scraping execution.
 *
 * @property int $id
 * @property string $supermarket
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int $product_count
 * @property string $status
 * @property string|null $error_message
 */
class ScrapeRun extends Model
{
    use HasFactory;
    protected $fillable = [
        'supermarket',
        'started_at',
        'completed_at',
        'product_count',
        'status',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'product_count' => 'integer',
    ];

    /**
     * Get the supermarket this scrape run belongs to.
     */
    public function supermarketModel(): BelongsTo
    {
        return $this->belongsTo(Supermarket::class, 'supermarket', 'identifier');
    }

    /**
     * Mark this scrape run as completed.
     */
    public function markCompleted(int $productCount): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'product_count' => $productCount,
        ]);
    }

    /**
     * Mark this scrape run as failed.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }
}
