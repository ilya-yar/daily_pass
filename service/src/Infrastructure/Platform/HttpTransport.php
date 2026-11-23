<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform;

interface HttpTransport
{
    /**
     * @throws TransportException
     */
    public function post(string $url, string $body, int $connectTimeoutSeconds, int $readTimeoutSeconds): string;
}
