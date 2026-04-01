<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores OAuth refresh tokens for supermarket scrapers.
 */
class ScraperToken extends Model
{
    protected $fillable = [
        'supermarket',
        'refresh_token',
        'token_obtained_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'token_obtained_at' => 'datetime',
        ];
    }

    /**
     * Get token for a specific supermarket.
     */
    public static function forSupermarket(string $supermarket): ?self
    {
        return self::where('supermarket', $supermarket)->first();
    }

    /**
     * Store or update token for a supermarket.
     */
    public static function storeToken(string $supermarket, string $refreshToken): self
    {
        return self::updateOrCreate(
            ['supermarket' => $supermarket],
            [
                'refresh_token' => $refreshToken,
                'token_obtained_at' => now(),
            ]
        );
    }
}
