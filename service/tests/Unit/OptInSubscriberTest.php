<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\OptIn\OptInFailedException;
use App\Application\OptIn\OptInSubscriber;
use App\Application\Platform\PlatformClient;
use App\Application\Platform\PlatformRequestException;
use App\Application\Platform\PlatformResult;
use App\Domain\Clock;
use App\Domain\MtMessage;
use App\Domain\Subscriber;
use App\Domain\SubscriberId;
use App\Domain\SubscriberRepository;
use App\Domain\SubscriberStatus;
use DateTimeImmutable;

final class OptInSubscriberTest extends \Codeception\Test\Unit
{
    public function testAlreadyActiveDoesNotCallPlatformOrSendWelcome(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, new DateTimeImmutable('2026-11-23 00:00:00 UTC'));
        $platform = new RecordingPlatformClient();

        $result = $this->useCase($subscribers, $platform)->execute($id);

        $this->assertTrue($result->alreadyActive);
        $this->assertSame('ACTIVE', $result->status->value);
        $this->assertSame([], $platform->optIns);
        $this->assertSame([], $platform->sms);
    }

    public function testNewSubscriberRegistersThenPersistsThenSendsWelcome(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $platform = new RecordingPlatformClient();

        $result = $this->useCase($subscribers, $platform)->execute($id);

        $this->assertFalse($result->alreadyActive);
        $this->assertSame('ACTIVE', $result->status->value);
        $this->assertSame(['tel:1000000001'], $platform->optIns);
        $this->assertSame(
            [['Daily Pass is active', 'tel:1000000001', '1234']],
            $platform->sms,
        );
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isActive());
        $this->assertSame(MtMessage::WELCOME, $platform->sms[0][0]);
    }

    public function testPlatformTimeoutLeavesNoSubscriberAndSendsNoWelcome(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $platform = new RecordingPlatformClient();
        $platform->optInError = new PlatformRequestException('Platform request timed out.');

        try {
            $this->useCase($subscribers, $platform)->execute($id);
            $this->fail('A platform timeout must fail the opt-in.');
        } catch (OptInFailedException $exception) {
            $this->assertSame('Platform did not confirm opt-in.', $exception->getMessage());
            $this->assertInstanceOf(PlatformRequestException::class, $exception->getPrevious());
        }

        $this->assertNull($subscribers->find($id));
        $this->assertSame([], $platform->sms);
        $this->assertFalse($subscribers->activated);
    }

    public function testNonSuccessPlatformStatusLeavesNoSubscriber(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $platform = new RecordingPlatformClient();
        $platform->optInResult = new PlatformResult('E1000', 'Invalid request', []);

        $this->expectException(OptInFailedException::class);
        try {
            $this->useCase($subscribers, $platform)->execute($id);
        } finally {
            $this->assertNull($subscribers->find($id));
            $this->assertSame([], $platform->sms);
        }
    }

    private function useCase(SubscriberRepository $subscribers, RecordingPlatformClient $platform): OptInSubscriber
    {
        return new OptInSubscriber(
            $subscribers,
            $platform,
            new FixedClock(new DateTimeImmutable('2026-11-23 12:00:00 UTC')),
            '1234',
        );
    }
}

final class InMemorySubscriberRepository implements SubscriberRepository
{
    /** @var array<string, Subscriber> */
    private array $rows = [];

    public bool $activated = false;

    public ?DateTimeImmutable $activatedAt = null;

    public function find(SubscriberId $id): ?Subscriber
    {
        return $this->rows[$id->value()] ?? null;
    }

    public function listActive(): array
    {
        $active = [];
        foreach ($this->rows as $subscriber) {
            if ($subscriber->isActive()) {
                $active[] = $subscriber;
            }
        }

        return $active;
    }

    public function add(Subscriber $subscriber): void
    {
        $this->rows[$subscriber->id->value()] = $subscriber;
    }

    public function activate(SubscriberId $id, DateTimeImmutable $at): void
    {
        $this->activated = true;
        $this->activatedAt = $at;
        $this->rows[$id->value()] = new Subscriber($id, SubscriberStatus::Active);
    }
}

final class RecordingPlatformClient implements PlatformClient
{
    /** @var list<string> */
    public array $optIns = [];

    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $sms = [];

    public ?PlatformRequestException $optInError = null;

    public PlatformResult $optInResult;

    public PlatformResult $smsResult;

    public function __construct()
    {
        $this->optInResult = new PlatformResult('S1000', 'SUCCESS', ['subscriptionStatus' => 'REGISTERED']);
        $this->smsResult = new PlatformResult('S1000', 'SUCCESS', []);
    }

    public function optIn(string $subscriberId): PlatformResult
    {
        $this->optIns[] = $subscriberId;
        if ($this->optInError !== null) {
            throw $this->optInError;
        }

        return $this->optInResult;
    }

    public function optOut(string $subscriberId): PlatformResult
    {
        throw new \RuntimeException('opt-out is not part of this use case.');
    }

    public function sendSms(string $message, string $destinationAddress, string $sourceAddress): PlatformResult
    {
        $this->sms[] = [$message, $destinationAddress, $sourceAddress];

        return $this->smsResult;
    }

    public function debit(
        string $externalTrxId,
        string $subscriberId,
        string $amount,
        string $currency,
    ): PlatformResult {
        throw new \RuntimeException('debit is not part of this use case.');
    }
}

final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
