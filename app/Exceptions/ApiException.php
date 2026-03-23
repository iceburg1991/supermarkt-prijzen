<?php

namespace App\Exceptions;

/**
 * Exception thrown when API requests fail.
 */
class ApiException extends ScraperException
{
    public function __construct(
        string $message = 'API request failed',
        public readonly ?string $endpoint = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
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
            'endpoint' => $this->endpoint,
            'status_code' => $this->statusCode,
            'response_body' => $this->responseBody,
        ];
    }
}
