<?php

namespace App\Domain\Scraper\Exceptions;

/**
 * Exception thrown when API rate limit is exceeded.
 */
class ApiRateLimitException extends ApiException
{
    public function __construct(
        string $message = 'API rate limit exceeded',
        ?string $endpoint = null,
        public readonly ?int $retryAfter = null,
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $endpoint, $code, null, $code, $previous);
    }

    /**
     * Get the exception context for logging.
     */
    public function context(): array
    {
        return array_merge(parent::context(), [
            'retry_after' => $this->retryAfter,
        ]);
    }
}
