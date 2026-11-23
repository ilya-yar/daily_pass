<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Webhook\HandleSubscriptionNotification;
use App\Domain\SubscriberId;
use App\Domain\SubscriptionEventKey;
use App\Domain\SubscriptionNotificationRepository;
use App\Domain\SubscriptionNotificationStatus;
use DateTimeImmutable;

final class HandleSubscriptionNotificationTest extends \Codeception\Test\Unit
{
    public function testDuplicateRegisteredDoesNotActivateTwice(): void
    {
        $subscribers = new InMemorySubscriberRepository();
        $events = new InMemorySubscriptionNotificationRepository();
        $handler = new HandleSubscriptionNotification(
            $events,
            $subscribers,
            new FixedClock(new DateTimeImmutable('2026-11-24 12:00:00 UTC')),
        );
        $key = $this->key(SubscriptionNotificationStatus::Registered, '2026-11-24T12:00:00.000+00:00');

        $handler->execute($key);
        $handler->execute($key);

        $this->assertSame(1, $subscribers->activateCalls);
        $this->assertSame(0, $subscribers->deactivateCalls);
        $stored = $subscribers->find(SubscriberId::fromString('tel:1000000001'));
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isActive());
    }

    public function testUnregisteredDeactivatesOnce(): void
    {
        $subscribers = new InMemorySubscriberRepository();
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers->activate($id, new DateTimeImmutable('2026-11-24 11:00:00 UTC'));
        $handler = new HandleSubscriptionNotification(
            new InMemorySubscriptionNotificationRepository(),
            $subscribers,
            new FixedClock(new DateTimeImmutable('2026-11-24 12:00:00 UTC')),
        );
        $key = $this->key(SubscriptionNotificationStatus::Unregistered, '2026-11-24T12:00:00.000+00:00');

        $handler->execute($key);
        $handler->execute($key);

        $this->assertSame(1, $subscribers->deactivateCalls);
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertSame('INACTIVE', $stored->status->value);
    }

    public function testPendingDoesNotChangeStatus(): void
    {
        $subscribers = new InMemorySubscriberRepository();
        $handler = new HandleSubscriptionNotification(
            new InMemorySubscriptionNotificationRepository(),
            $subscribers,
            new FixedClock(new DateTimeImmutable('2026-11-24 12:00:00 UTC')),
        );

        $handler->execute($this->key(SubscriptionNotificationStatus::Pending, '2026-11-24T12:00:00.000+00:00'));

        $this->assertSame(0, $subscribers->activateCalls);
        $this->assertNull($subscribers->find(SubscriberId::fromString('tel:1000000001')));
    }

    private function key(SubscriptionNotificationStatus $status, string $timeStamp): SubscriptionEventKey
    {
        return new SubscriptionEventKey(
            SubscriberId::fromString('tel:1000000001'),
            $status,
            $timeStamp,
            'DAILY',
        );
    }
}

final class InMemorySubscriptionNotificationRepository implements SubscriptionNotificationRepository
{
    /** @var array<string, true> */
    private array $seen = [];

    public function recordNew(SubscriptionEventKey $key): bool
    {
        $token = $key->subscriberId->value() . '|' . $key->status->value . '|' . $key->timeStamp . '|' . $key->frequency;
        if (isset($this->seen[$token])) {
            return false;
        }
        $this->seen[$token] = true;

        return true;
    }
}
