<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform;

use App\Application\Platform\PlatformClient;
use App\Application\Platform\PlatformRequestException;
use App\Application\Platform\PlatformResult;
use Psr\Log\LoggerInterface;

/**
 * One HTTP call per operation. A timeout or a bad response is returned to the caller;
 * this client does not retry and does not mint a new externalTrxId.
 */
final class HttpPlatformClient implements PlatformClient
{
    private const VERSION = '1.0';

    private const PAYMENT_INSTRUMENT = 'Mobile Account';

    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpTransport $transport,
        string $baseUrl,
        private readonly string $applicationId,
        private readonly string $password,
        private readonly int $connectTimeoutSeconds,
        private readonly int $readTimeoutSeconds,
        private readonly LoggerInterface $logger,
    ) {
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '' || $this->applicationId === '' || $this->password === '') {
            throw new \InvalidArgumentException('Platform URL, application id, and password are required.');
        }
        if ($this->connectTimeoutSeconds < 1 || $this->readTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('Platform timeouts must be at least one second.');
        }
        $this->baseUrl = $baseUrl;
    }

    public static function fromEnvironment(HttpTransport $transport, LoggerInterface $logger): self
    {
        return new self(
            $transport,
            self::requiredEnv('PLATFORM_BASE_URL'),
            self::requiredEnv('PLATFORM_APPLICATION_ID'),
            self::requiredEnv('PLATFORM_PASSWORD'),
            self::timeoutEnv('PLATFORM_CONNECT_TIMEOUT', 2),
            self::timeoutEnv('PLATFORM_READ_TIMEOUT', 5),
            $logger,
        );
    }

    public function optIn(string $subscriberId): PlatformResult
    {
        return $this->sendSubscription($subscriberId, '1');
    }

    public function optOut(string $subscriberId): PlatformResult
    {
        return $this->sendSubscription($subscriberId, '0');
    }

    public function sendSms(string $message, string $destinationAddress, string $sourceAddress): PlatformResult
    {
        $this->assertPresent($message, 'SMS message');
        $this->assertPresent($destinationAddress, 'SMS destination');
        $this->assertPresent($sourceAddress, 'SMS source address');

        return $this->post('/sms/send', [
            'applicationId' => $this->applicationId,
            'password' => $this->password,
            'message' => $message,
            'destinationAddresses' => [$destinationAddress],
            'version' => self::VERSION,
            'sourceAddress' => $sourceAddress,
            'encoding' => '0',
        ]);
    }

    public function debit(
        string $externalTrxId,
        string $subscriberId,
        string $amount,
        string $currency,
    ): PlatformResult {
        $this->assertPresent($externalTrxId, 'externalTrxId');
        $this->assertPresent($subscriberId, 'subscriberId');
        $this->assertPresent($amount, 'amount');
        $this->assertPresent($currency, 'currency');

        return $this->post('/caas/direct/debit', [
            'applicationId' => $this->applicationId,
            'password' => $this->password,
            'externalTrxId' => $externalTrxId,
            'subscriberId' => $subscriberId,
            'paymentInstrumentName' => self::PAYMENT_INSTRUMENT,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    private function sendSubscription(string $subscriberId, string $action): PlatformResult
    {
        $this->assertPresent($subscriberId, 'subscriberId');

        return $this->post('/subscription/send', [
            'applicationId' => $this->applicationId,
            'password' => $this->password,
            'version' => self::VERSION,
            'action' => $action,
            'subscriberId' => $subscriberId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $path, array $payload): PlatformResult
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->logger->info('Platform request sent.', ['path' => $path]);
        try {
            $raw = $this->transport->post(
                $this->baseUrl . $path,
                $body,
                $this->connectTimeoutSeconds,
                $this->readTimeoutSeconds,
            );
        } catch (TransportTimeoutException $exception) {
            $this->logger->error('Platform request timed out.', ['path' => $path]);
            throw new PlatformRequestException('Platform request timed out.', previous: $exception);
        } catch (TransportException $exception) {
            $this->logger->error('Platform request failed.', ['path' => $path]);
            throw new PlatformRequestException('Platform request failed.', previous: $exception);
        }

        try {
            $result = $this->decode($raw);
        } catch (PlatformRequestException $exception) {
            $this->logger->error('Platform response was rejected.', ['path' => $path]);
            throw $exception;
        }

        $this->logger->info('Platform response received.', [
            'path' => $path,
            'statusCode' => $result->statusCode,
        ]);

        return $result;
    }

    private function decode(string $raw): PlatformResult
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new PlatformRequestException('Platform response is not JSON.', previous: $exception);
        }
        if (!is_array($decoded)) {
            throw new PlatformRequestException('Platform response is not a JSON object.');
        }

        $statusCode = $decoded['statusCode'] ?? null;
        $statusDetail = $decoded['statusDetail'] ?? null;
        if (!is_string($statusCode) || $statusCode === '' || !is_string($statusDetail) || $statusDetail === '') {
            throw new PlatformRequestException('Platform response is missing statusCode or statusDetail.');
        }

        $fields = [];
        foreach ($decoded as $name => $value) {
            if (!is_string($name) || $name === 'statusCode' || $name === 'statusDetail' || !is_string($value)) {
                continue;
            }
            $fields[$name] = $value;
        }

        return new PlatformResult($statusCode, $statusDetail, $fields);
    }

    private function assertPresent(string $value, string $name): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException($name . ' is required.');
        }
    }

    private static function requiredEnv(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Environment variable %s is required.', $name));
        }

        return $value;
    }

    private static function timeoutEnv(string $name, int $default): int
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new \RuntimeException(sprintf('Environment variable %s must be a positive number of seconds.', $name));
        }

        return (int) $value;
    }
}
