<?php

declare(strict_types=1);

namespace App\Contracts\Scraper;

use Illuminate\Support\Collection;

/**
 * Interface for supermarket scraper implementations.
 *
 * Defines the contract that all supermarket scrapers must implement,
 * enabling extensibility and consistent behavior across different
 * supermarket APIs.
 */
interface SupermarketScraperInterface
{
    /**
     * Authenticate with the supermarket API if required.
     *
     * @param string|null $authCode Optional authorization code for OAuth flow
     * @return bool True if authentication successful or not required
     */
    public function authenticate(?string $authCode = null): bool;

    /**
     * Search for products by query term.
     *
     * @param string $query Search term
     * @param int $maxResults Maximum number of results to return
     * @return Collection<int, \App\Domain\Scraper\ValueObjects\ProductData>
     */
    public function searchProducts(string $query, int $maxResults = 20): Collection;

    /**
     * Get all available categories from the supermarket.
     *
     * @return Collection<int, array{id: string, name: string, parent_id: string|null}>
     */
    public function getCategories(): Collection;

    /**
     * Get products within a specific category.
     *
     * @param string $categoryId Category identifier
     * @param int $maxResults Maximum number of results to return
     * @return Collection<int, \App\Domain\Scraper\ValueObjects\ProductData>
     */
    public function getProductsByCategory(string $categoryId, int $maxResults = 50): Collection;

    /**
     * Get products currently on promotion.
     *
     * @param int $maxResults Maximum number of results to return
     * @return Collection<int, \App\Domain\Scraper\ValueObjects\ProductData>
     */
    public function getPromotionalProducts(int $maxResults = 30): Collection;

    /**
     * Get the supermarket identifier.
     *
     * @return string Unique identifier (e.g., 'ah', 'jumbo')
     */
    public function getIdentifier(): string;
}
