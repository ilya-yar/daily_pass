<?php

declare(strict_types=1);

namespace Mock\Infrastructure;

/**
 * One POST, explicit timeouts. A transport failure is returned to the caller.
 */
final class NotificationClient
{
    public function post(string $url, string $body, int $connectTimeoutSeconds, int $readTimeoutSeconds): string
    {
        if ($connectTimeoutSeconds < 1 || $readTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('Notification timeouts must be at least one second.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new NotificationDeliveryException('Subscription notification could not be sent.');
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

        if (!is_string($response) || $errno !== 0) {
            throw new NotificationDeliveryException('Subscription notification could not be sent.');
        }

        return $response;
    }
}
