<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\SubscriberId;
use App\Domain\SubscriptionEventKey;
use App\Domain\SubscriptionNotificationStatus;

final class SubscriptionEventKeyTest extends \Codeception\Test\Unit
{
    public function testSameFieldsAreTheSameEvent(): void
    {
        $first = $this->key('tel:1000000001', '2026-11-24T12:00:00.000+00:00');
        $second = $this->key('tel:1000000001', '2026-11-24T12:00:00.000+00:00');

        $this->assertTrue($first->equals($second));
    }

    public function testAnotherTimeStampIsADifferentEvent(): void
    {
        $first = $this->key('tel:1000000001', '2026-11-24T12:00:00.000+00:00');
        $second = $this->key('tel:1000000001', '2026-11-24T12:00:01.000+00:00');

        $this->assertFalse($first->equals($second));
    }

    private function key(string $subscriberId, string $timeStamp): SubscriptionEventKey
    {
        return new SubscriptionEventKey(
            SubscriberId::fromString($subscriberId),
            SubscriptionNotificationStatus::Registered,
            $timeStamp,
            'DAILY',
        );
    }
}
