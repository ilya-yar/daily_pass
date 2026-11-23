<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Status\GetSubscriberStatus;
use App\Application\Status\SubscriberNotFoundException;
use App\Domain\SubscriberId;
use DateTimeImmutable;

final class GetSubscriberStatusTest extends \Codeception\Test\Unit
{
    public function testReturnsTheStoredLocalStatus(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, new DateTimeImmutable('2026-11-23 00:00:00 UTC'));

        $subscriber = (new GetSubscriberStatus($subscribers))->execute($id);

        $this->assertSame('tel:1000000001', $subscriber->id->value());
        $this->assertTrue($subscriber->isActive());
    }

    public function testUnknownSubscriberIsNotInvented(): void
    {
        $subscribers = new InMemorySubscriberRepository();

        $this->expectException(SubscriberNotFoundException::class);
        (new GetSubscriberStatus($subscribers))->execute(SubscriberId::fromString('tel:1999999999'));
    }
}
