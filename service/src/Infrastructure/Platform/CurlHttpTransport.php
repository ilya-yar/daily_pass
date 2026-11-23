<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform;

/**
 * Connect and read timeouts are separate. The body is JSON; credentials stay in that body
 * and are not copied into the error text.
 */
final class CurlHttpTransport implements HttpTransport
{
    public function post(string $url, string $body, int $connectTimeoutSeconds, int $readTimeoutSeconds): string
    {
        if ($connectTimeoutSeconds < 1 || $readTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('Platform timeouts must be at least one second.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Platform request failed.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $connectTimeoutSeconds + $readTimeoutSeconds,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($handle);
        $errno = curl_errno($handle);
        curl_close($handle);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new TransportTimeoutException('Platform request timed out.');
        }
        if (!is_string($response) || $errno !== 0) {
            throw new TransportException('Platform request failed.');
        }

        return $response;
    }
}
