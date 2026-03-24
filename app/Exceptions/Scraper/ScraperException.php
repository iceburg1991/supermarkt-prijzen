<?php

namespace App\Exceptions\Scraper;

use Exception;

/**
 * Base exception for all scraper-related errors.
 */
class ScraperException extends Exception
{
    /**
     * Create a new scraper exception.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the exception context for logging.
     */
    public function context(): array
    {
        return [];
    }
}
