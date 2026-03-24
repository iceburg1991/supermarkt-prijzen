<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Supermarket model representing a supermarket chain.
 *
 * @property int $id
 * @property string $identifier
 * @property string $name
 * @property string|null $base_url
 * @property bool $requires_auth
 * @property bool $enabled
 */
class Supermarket extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'base_url',
        'requires_auth',
        'enabled',
    ];

    protected $casts = [
        'requires_auth' => 'boolean',
        'enabled' => 'boolean',
    ];

    /**
     * Get all products for this supermarket.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supermarket', 'identifier');
    }

    /**
     * Get all scrape runs for this supermarket.
     */
    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class, 'supermarket', 'identifier');
    }

    /**
     * Get all categories for this supermarket.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'supermarket', 'identifier');
    }
}
