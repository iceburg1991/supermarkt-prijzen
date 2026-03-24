<?php

namespace App\Exceptions\Scraper;

/**
 * Exception thrown when required configuration is missing.
 */
class MissingConfigException extends ConfigurationException
{
    public function __construct(
        string $configKey,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = "Required configuration key '{$configKey}' is missing";
        parent::__construct($message, $configKey, $code, $previous);
    }
}
