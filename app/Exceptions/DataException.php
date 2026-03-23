<?php

namespace App\Exceptions;

/**
 * Exception thrown when data processing fails.
 */
class DataException extends ScraperException
{
    public function __construct(
        string $message = 'Data processing failed',
        public readonly ?string $productId = null,
        public readonly ?array $rawData = null,
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
            'product_id' => $this->productId,
            'raw_data' => $this->rawData,
        ];
    }
}
