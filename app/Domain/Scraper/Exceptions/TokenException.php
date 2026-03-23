<?php

namespace App\Domain\Scraper\Exceptions;

/**
 * Exception thrown when token operations fail.
 */
class TokenException extends ScraperException
{
    public function __construct(
        string $message = 'Token operation failed',
        public readonly ?string $supermarket = null,
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
        return [
            'supermarket' => $this->supermarket,
        ];
    }
}
