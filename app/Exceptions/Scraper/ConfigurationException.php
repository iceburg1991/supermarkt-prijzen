<?php

namespace App\Exceptions\Scraper;

/**
 * Exception thrown when configuration is invalid or missing.
 */
class ConfigurationException extends ScraperException
{
    public function __construct(
        string $message = 'Invalid or missing configuration',
        public readonly ?string $configKey = null,
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
            'config_key' => $this->configKey,
        ];
    }
}
