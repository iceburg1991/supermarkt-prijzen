<?php

namespace App\Domain\Scraper\Exceptions;

/**
 * Exception thrown when an access token has expired.
 */
class TokenExpiredException extends TokenException
{
    public function __construct(
        string $message = 'Access token has expired',
        ?string $supermarket = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $supermarket, $code, $previous);
    }
}
