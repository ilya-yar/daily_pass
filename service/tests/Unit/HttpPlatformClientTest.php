<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Platform\PlatformRequestException;
use App\Infrastructure\Platform\HttpPlatformClient;
use App\Infrastructure\Platform\HttpTransport;
use App\Infrastructure\Platform\TransportException;
use App\Infrastructure\Platform\TransportTimeoutException;
use Psr\Log\AbstractLogger;

final class HttpPlatformClientTest extends \Codeception\Test\Unit
{
    private const PASSWORD = 'p@ss-secret';

    private const APPLICATION_ID = 'APP_000123';

    public function testOptInSendsActionVersionAndSubscriber(): void
    {
        $transport = new FakeHttpTransport();
        $this->client($transport)->optIn('tel:1000000001');

        $body = $this->onlyBody($transport);
        $this->assertSame('http://platform.test/subscription/send', $transport->urls[0]);
        $this->assertSame('1', $body['action']);
        $this->assertSame('1.0', $body['version']);
        $this->assertSame('tel:1000000001', $body['subscriberId']);
        $this->assertSame(self::APPLICATION_ID, $body['applicationId']);
        $this->assertSame(2, $transport->connectTimeouts[0]);
        $this->assertSame(5, $transport->readTimeouts[0]);
    }

    public function testOptOutSendsActionZero(): void
    {
        $transport = new FakeHttpTransport();
        $this->client($transport)->optOut('tel:1000000001');

        $this->assertSame('0', $this->onlyBody($transport)['action']);
    }

    public function testSmsSendsEncodingAndDestinationArray(): void
    {
        $transport = new FakeHttpTransport();
        $this->client($transport)->sendSms('Daily Pass is active', 'tel:1000000001', '1234');

        $body = $this->onlyBody($transport);
        $this->assertSame('http://platform.test/sms/send', $transport->urls[0]);
        $this->assertSame('0', $body['encoding']);
        $this->assertSame(['tel:1000000001'], $body['destinationAddresses']);
        $this->assertSame('Daily Pass is active', $body['message']);
        $this->assertSame('1234', $body['sourceAddress']);
        $this->assertSame('1.0', $body['version']);
    }

    public function testDebitSendsTheContractFieldsAndKeepsExternalTrxId(): void
    {
        $transport = new FakeHttpTransport();
        $result = $this->client($transport)->debit('25609', 'tel:1000000001', '5.00', 'XTS');

        $body = $this->onlyBody($transport);
        $this->assertSame('http://platform.test/caas/direct/debit', $transport->urls[0]);
        $this->assertSame('25609', $body['externalTrxId']);
        $this->assertSame('tel:1000000001', $body['subscriberId']);
        $this->assertSame('Mobile Account', $body['paymentInstrumentName']);
        $this->assertSame('5.00', $body['amount']);
        $this->assertSame('XTS', $body['currency']);
        $this->assertSame('S1000', $result->statusCode);
        $this->assertSame('80430074', $result->field('referenceId'));
    }

    public function testPasswordIsAbsentFromSuccessAndFailureLogs(): void
    {
        $successTransport = new FakeHttpTransport();
        $successLog = new RecordingLogger();
        $this->client($successTransport, $successLog)->optIn('tel:1000000001');

        $failureTransport = new FakeHttpTransport();
        $failureTransport->error = new TransportException('connection refused');
        $failureLog = new RecordingLogger();
        try {
            $this->client($failureTransport, $failureLog)->debit('25609', 'tel:1000000001', '5.00', 'XTS');
            $this->fail('A transport failure must surface to the caller.');
        } catch (PlatformRequestException $exception) {
            $this->assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }

        $this->assertStringNotContainsString(self::PASSWORD, $successLog->text());
        $this->assertStringNotContainsString(self::PASSWORD, $failureLog->text());
        $this->assertStringNotContainsString(self::APPLICATION_ID, $successLog->text());
        $this->assertStringNotContainsString(self::APPLICATION_ID, $failureLog->text());
    }

    public function testTimeoutIsReportedOnceAndDoesNotChangeExternalTrxId(): void
    {
        $transport = new FakeHttpTransport();
        $transport->error = new TransportTimeoutException('timed out');

        try {
            $this->client($transport)->debit('25609', 'tel:1000000001', '5.00', 'XTS');
            $this->fail('A timeout must surface to the caller.');
        } catch (PlatformRequestException $exception) {
            $this->assertSame('Platform request timed out.', $exception->getMessage());
            $this->assertInstanceOf(TransportTimeoutException::class, $exception->getPrevious());
        }

        $this->assertCount(1, $transport->bodies);
        $this->assertSame('25609', $this->onlyBody($transport)['externalTrxId']);
    }

    public function testUnavailableTransportIsNotRetried(): void
    {
        $transport = new FakeHttpTransport();
        $transport->error = new TransportException('connection refused');

        try {
            $this->client($transport)->optIn('tel:1000000001');
            $this->fail('An unavailable transport must surface to the caller.');
        } catch (PlatformRequestException $exception) {
            $this->assertSame('Platform request failed.', $exception->getMessage());
        }

        $this->assertCount(1, $transport->bodies);
    }

    public function testResponseWithoutStatusIsRejectedWithoutASecondCall(): void
    {
        $transport = new FakeHttpTransport();
        $transport->responseBody = '{"statusDetail":"SUCCESS","password":"' . self::PASSWORD . '"}';
        $logger = new RecordingLogger();

        try {
            $this->client($transport, $logger)->optIn('tel:1000000001');
            $this->fail('A response without statusCode must be rejected.');
        } catch (PlatformRequestException $exception) {
            $this->assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }

        $this->assertCount(1, $transport->bodies);
        $this->assertStringNotContainsString(self::PASSWORD, $logger->text());
    }

    public function testEnvironmentSuppliesUrlCredentialsAndDefaultTimeouts(): void
    {
        $previous = [
            'PLATFORM_BASE_URL' => getenv('PLATFORM_BASE_URL'),
            'PLATFORM_APPLICATION_ID' => getenv('PLATFORM_APPLICATION_ID'),
            'PLATFORM_PASSWORD' => getenv('PLATFORM_PASSWORD'),
            'PLATFORM_CONNECT_TIMEOUT' => getenv('PLATFORM_CONNECT_TIMEOUT'),
            'PLATFORM_READ_TIMEOUT' => getenv('PLATFORM_READ_TIMEOUT'),
        ];
        putenv('PLATFORM_BASE_URL=http://platform.test/');
        putenv('PLATFORM_APPLICATION_ID=' . self::APPLICATION_ID);
        putenv('PLATFORM_PASSWORD=' . self::PASSWORD);
        putenv('PLATFORM_CONNECT_TIMEOUT');
        putenv('PLATFORM_READ_TIMEOUT');
        try {
            $transport = new FakeHttpTransport();
            HttpPlatformClient::fromEnvironment($transport, new RecordingLogger())->optIn('tel:1000000001');
            $this->assertSame('http://platform.test/subscription/send', $transport->urls[0]);
            $this->assertSame(self::PASSWORD, $this->onlyBody($transport)['password']);
            $this->assertSame(2, $transport->connectTimeouts[0]);
            $this->assertSame(5, $transport->readTimeouts[0]);
        } finally {
            foreach ($previous as $name => $value) {
                if (!is_string($value) || $value === '') {
                    putenv($name);
                    continue;
                }
                putenv($name . '=' . $value);
            }
        }
    }

    public function testEmptySubscriberDoesNotCallPlatform(): void
    {
        $transport = new FakeHttpTransport();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->client($transport)->optIn('');
        } finally {
            $this->assertSame([], $transport->bodies);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyBody(FakeHttpTransport $transport): array
    {
        $this->assertCount(1, $transport->bodies);
        $decoded = json_decode($transport->bodies[0], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function client(FakeHttpTransport $transport, ?RecordingLogger $logger = null): HttpPlatformClient
    {
        return new HttpPlatformClient(
            $transport,
            'http://platform.test',
            self::APPLICATION_ID,
            self::PASSWORD,
            2,
            5,
            $logger ?? new RecordingLogger(),
        );
    }
}

final class FakeHttpTransport implements HttpTransport
{
    public ?TransportException $error = null;

    public string $responseBody = '{"statusCode":"S1000","statusDetail":"SUCCESS","referenceId":"80430074"}';

    /** @var list<string> */
    public array $urls = [];

    /** @var list<string> */
    public array $bodies = [];

    /** @var list<int> */
    public array $connectTimeouts = [];

    /** @var list<int> */
    public array $readTimeouts = [];

    public function post(string $url, string $body, int $connectTimeoutSeconds, int $readTimeoutSeconds): string
    {
        $this->urls[] = $url;
        $this->bodies[] = $body;
        $this->connectTimeouts[] = $connectTimeoutSeconds;
        $this->readTimeouts[] = $readTimeoutSeconds;
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->responseBody;
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $lines = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        $this->lines[] = (string) $level . ' ' . $message . ' ' . $encoded;
    }

    public function text(): string
    {
        return implode("\n", $this->lines);
    }
}
