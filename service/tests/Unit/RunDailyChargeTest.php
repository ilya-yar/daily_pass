<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Charging\Delay;
use App\Application\Charging\RunDailyCharge;
use App\Application\Platform\PlatformClient;
use App\Application\Platform\PlatformRequestException;
use App\Application\Platform\PlatformResult;
use App\Domain\Amount;
use App\Domain\ChargeAttemptRepository;
use App\Domain\DuplicateChargeAttemptException;
use App\Domain\ExternalTrxId;
use App\Domain\MtMessage;
use App\Domain\Subscriber;
use App\Domain\SubscriberId;
use App\Domain\SubscriberStatus;
use DateTimeImmutable;

final class RunDailyChargeTest extends \Codeception\Test\Unit
{
    public function testChargesAnActiveSubscriberOnceWithTheDayKey(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();

        $first = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(1, $first->charged);
        $this->assertSame(0, $first->skipped);
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertSame(
            [MtMessage::receipt('ref-1')],
            array_column($platform->sms, 0),
        );

        $second = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(0, $second->charged);
        $this->assertSame(1, $second->skipped);
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertCount(1, $platform->sms);
        $this->assertSame(['S1000'], $attempts->statuses);
    }

    public function testInactiveSubscriberIsNotCharged(): void
    {
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->add(new Subscriber(
            SubscriberId::fromString('tel:1999999999'),
            SubscriberStatus::Inactive,
        ));
        $platform = new ChargingPlatformClient();
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));

        $result = $this->job($subscribers, new InMemoryChargeAttemptRepository(), $platform, $clock)->execute();

        $this->assertSame(0, $result->charged);
        $this->assertSame([], $platform->debitKeys);
        $this->assertSame([], $platform->sms);
    }

    public function testTimeoutLeavesNoAttemptAndRetryReusesTheKey(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->debitError = new PlatformRequestException('Platform request timed out.');

        $delay = new RecordingDelay();
        $failed = $this->job($subscribers, $attempts, $platform, $clock, $delay)->execute();
        $this->assertSame(1, $failed->failed);
        $this->assertSame([], $attempts->statuses);
        $this->assertSame([$expectedKey, $expectedKey, $expectedKey], $platform->debitKeys);
        $this->assertSame([1, 2], $delay->waits);

        $platform->debitError = null;
        $retry = $this->job($subscribers, $attempts, $platform, $clock)->execute();

        $this->assertSame(1, $retry->charged);
        $this->assertSame([$expectedKey, $expectedKey, $expectedKey, $expectedKey], $platform->debitKeys);
        $this->assertSame(['S1000'], $attempts->statuses);
    }

    public function testInsufficientBalanceRetriesSameKeyAndDoesNotSuspend(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->debitCodes = ['E1326', 'E1326', 'E1326', 'S1000'];
        $delay = new RecordingDelay();

        $first = $this->job($subscribers, $attempts, $platform, $clock, $delay)->execute();
        $this->assertSame(0, $first->charged);
        $this->assertSame(1, $first->failed);
        $this->assertSame([$expectedKey, $expectedKey, $expectedKey], $platform->debitKeys);
        $this->assertSame([1, 2], $delay->waits);
        $this->assertSame([], $platform->sms);
        $this->assertSame([], $attempts->statuses);
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isActive());

        $second = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(1, $second->charged);
        $this->assertSame([$expectedKey, $expectedKey, $expectedKey, $expectedKey], $platform->debitKeys);
        $this->assertSame(['S1000'], $attempts->statuses);
        $this->assertCount(1, $platform->sms);
    }

    public function testPermanentErrorSuspendsWithoutRetryOrReceipt(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->debitCodes = ['E1308', 'S1000'];
        $delay = new RecordingDelay();

        $first = $this->job($subscribers, $attempts, $platform, $clock, $delay)->execute();
        $this->assertSame(0, $first->charged);
        $this->assertSame(1, $first->failed);
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertSame([], $delay->waits);
        $this->assertSame([], $platform->sms);
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertSame(SubscriberStatus::Suspended, $stored->status);

        $second = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(0, $second->charged);
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertSame([], $attempts->statuses);
    }

    private function job(
        InMemorySubscriberRepository $subscribers,
        ChargeAttemptRepository $attempts,
        ChargingPlatformClient $platform,
        FixedClock $clock,
        ?RecordingDelay $delay = null,
    ): RunDailyCharge {
        return new RunDailyCharge(
            $subscribers,
            $attempts,
            $platform,
            $clock,
            $delay ?? new RecordingDelay(),
            Amount::fromDecimal('5.00'),
            'XTS',
            '1234',
        );
    }
}

final class InMemoryChargeAttemptRepository implements ChargeAttemptRepository
{
    /** @var array<string, string> */
    private array $successful = [];

    /** @var list<string> */
    public array $statuses = [];

    public function successfulExternalTrxIds(array $externalTrxIds): array
    {
        $found = [];
        foreach ($externalTrxIds as $externalTrxId) {
            if (isset($this->successful[$externalTrxId])) {
                $found[] = $externalTrxId;
            }
        }

        return $found;
    }

    public function recordSuccess(
        SubscriberId $subscriberId,
        string $externalTrxId,
        string $referenceId,
        DateTimeImmutable $occurredAt,
    ): void {
        if (isset($this->successful[$externalTrxId])) {
            throw new DuplicateChargeAttemptException('A charge attempt with this externalTrxId already exists.');
        }
        $this->successful[$externalTrxId] = $subscriberId->value() . ':' . $referenceId . ':' . $occurredAt->format('c');
        $this->statuses[] = 'S1000';
    }
}

final class RecordingDelay implements Delay
{
    /** @var list<int> */
    public array $waits = [];

    public function waitSeconds(int $seconds): void
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Delay must be at least one second.');
        }
        $this->waits[] = $seconds;
    }
}

final class ChargingPlatformClient implements PlatformClient
{
    /** @var list<string> */
    public array $debitKeys = [];

    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $sms = [];

    public ?PlatformRequestException $debitError = null;

    /** @var list<string> */
    public array $debitCodes = ['S1000'];

    public function optIn(string $subscriberId): PlatformResult
    {
        throw new \RuntimeException('opt-in is not part of this use case.');
    }

    public function optOut(string $subscriberId): PlatformResult
    {
        throw new \RuntimeException('opt-out is not part of this use case.');
    }

    public function sendSms(string $message, string $destinationAddress, string $sourceAddress): PlatformResult
    {
        $this->sms[] = [$message, $destinationAddress, $sourceAddress];

        return new PlatformResult('S1000', 'SUCCESS', []);
    }

    public function debit(
        string $externalTrxId,
        string $subscriberId,
        string $amount,
        string $currency,
    ): PlatformResult {
        $this->debitKeys[] = $externalTrxId;
        if ($this->debitError !== null) {
            throw $this->debitError;
        }
        $index = count($this->debitKeys) - 1;
        $code = $this->debitCodes[$index] ?? 'S1000';
        $fields = $code === 'S1000' ? ['referenceId' => 'ref-1'] : [];

        return new PlatformResult($code, $code === 'S1000' ? 'SUCCESS' : 'FAILED', $fields);
    }
}
