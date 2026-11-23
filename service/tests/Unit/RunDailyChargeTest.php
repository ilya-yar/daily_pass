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

    public function testOptOutBeforeDebitSkipsChargeAndKeepsTheSameKeyForThePeer(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $peer = SubscriberId::fromString('tel:1000000002');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $peerKey = ExternalTrxId::forBillingDay($peer, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $subscribers->activate($peer, $clock->now());
        $subscribers->inactiveOnFindId = $id->value();
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();

        $result = $this->job($subscribers, $attempts, $platform, $clock)->execute();

        $this->assertSame(1, $result->charged);
        $this->assertSame([$peerKey], $platform->debitKeys);
        $this->assertSame(['S1000'], $attempts->statuses);
        $this->assertNotSame($expectedKey, $peerKey);
        $this->assertSame([], $attempts->successfulExternalTrxIds([$expectedKey]));
    }

    public function testInFlightDebitRecordsAtMostOneSuccessWithoutANewKey(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $peer = SubscriberId::fromString('tel:1000000002');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $peerKey = ExternalTrxId::forBillingDay($peer, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $subscribers->activate($peer, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->beforeDebit = static function (string $subscriberId) use ($subscribers, $id, $clock): void {
            if ($subscriberId === $id->value()) {
                $subscribers->deactivate($id, $clock->now());
            }
        };

        $first = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(2, $first->charged);
        $this->assertSame([$expectedKey, $peerKey], $platform->debitKeys);
        $this->assertSame(1, count($attempts->successfulExternalTrxIds([$expectedKey])));
        $this->assertSame(1, count($attempts->successfulExternalTrxIds([$peerKey])));

        $second = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(0, $second->charged);
        $this->assertSame([$expectedKey, $peerKey], $platform->debitKeys);
        $this->assertSame(1, count($attempts->successfulExternalTrxIds([$expectedKey])));
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

    public function testDuplicateDoesNotMintANewKeyOrASecondSuccess(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->debitCodes = ['E1337', 'S1000'];
        $delay = new RecordingDelay();

        $first = $this->job($subscribers, $attempts, $platform, $clock, $delay)->execute();
        $this->assertSame(0, $first->charged);
        $this->assertSame(1, $first->failed);
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertSame([], $delay->waits);
        $this->assertSame([], $platform->sms);
        $this->assertSame(['E1337'], $attempts->statuses);

        $second = $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(0, $second->charged);
        $this->assertSame(1, $second->skipped);
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertSame(['E1337'], $attempts->statuses);
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isActive());
    }

    public function testDuplicateWithReferenceIsStoredOnceWithoutASecondDebit(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->debitCodes = ['E1337'];
        $platform->debitReferences = ['ref-dup'];

        $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame(['E1337'], $attempts->statuses);
        $this->assertSame([MtMessage::receipt('ref-dup')], array_column($platform->sms, 0));

        $this->job($subscribers, $attempts, $platform, $clock)->execute();
        $this->assertSame([$expectedKey], $platform->debitKeys);
        $this->assertCount(1, $platform->sms);
        $this->assertCount(0, $attempts->successfulExternalTrxIds([$expectedKey]));
    }

    public function testThrottleRetriesSameKeyAndDoesNotSuspend(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-24 15:00:00 UTC'));
        $expectedKey = ExternalTrxId::forBillingDay($id, $clock->now())->value();
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, $clock->now());
        $attempts = new InMemoryChargeAttemptRepository();
        $platform = new ChargingPlatformClient();
        $platform->debitCodes = ['E1318', 'E1318', 'E1318'];
        $delay = new RecordingDelay();

        $result = $this->job($subscribers, $attempts, $platform, $clock, $delay)->execute();
        $this->assertSame(0, $result->charged);
        $this->assertSame(1, $result->failed);
        $this->assertSame([$expectedKey, $expectedKey, $expectedKey], $platform->debitKeys);
        $this->assertSame([1, 2], $delay->waits);
        $this->assertSame([], $platform->sms);
        $this->assertSame([], $attempts->statuses);
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isActive());
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

    /** @var array<string, string> */
    private array $completed = [];

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

    public function completedExternalTrxIds(array $externalTrxIds): array
    {
        $found = [];
        foreach ($externalTrxIds as $externalTrxId) {
            if (isset($this->completed[$externalTrxId])) {
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
        $this->guardUnique($externalTrxId);
        $this->successful[$externalTrxId] = $subscriberId->value() . ':' . $referenceId . ':' . $occurredAt->format('c');
        $this->completed[$externalTrxId] = 'S1000';
        $this->statuses[] = 'S1000';
    }

    public function recordDuplicate(
        SubscriberId $subscriberId,
        string $externalTrxId,
        ?string $referenceId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->guardUnique($externalTrxId);
        $this->completed[$externalTrxId] = 'E1337:' . ($referenceId ?? '') . ':' . $subscriberId->value() . ':' . $occurredAt->format('c');
        $this->statuses[] = 'E1337';
    }

    private function guardUnique(string $externalTrxId): void
    {
        if (isset($this->completed[$externalTrxId])) {
            throw new DuplicateChargeAttemptException('A charge attempt with this externalTrxId already exists.');
        }
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

    /** @var list<string> */
    public array $debitReferences = [];

    /** @var (\Closure(string): void)|null */
    public $beforeDebit = null;

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
        if ($this->beforeDebit !== null) {
            ($this->beforeDebit)($subscriberId);
        }
        $this->debitKeys[] = $externalTrxId;
        if ($this->debitError !== null) {
            throw $this->debitError;
        }
        $index = count($this->debitKeys) - 1;
        $code = $this->debitCodes[$index] ?? 'S1000';
        $fields = [];
        if ($code === 'S1000') {
            $fields['referenceId'] = 'ref-1';
        } elseif (isset($this->debitReferences[$index]) && $this->debitReferences[$index] !== '') {
            $fields['referenceId'] = $this->debitReferences[$index];
        }

        return new PlatformResult($code, $code === 'S1000' ? 'SUCCESS' : 'FAILED', $fields);
    }
}
