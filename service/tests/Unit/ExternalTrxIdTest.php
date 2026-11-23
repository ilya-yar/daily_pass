<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Clock;
use App\Domain\ExternalTrxId;
use App\Domain\SubscriberId;
use DateTimeImmutable;
use DateTimeZone;

final class ExternalTrxIdTest extends \Codeception\Test\Unit
{
    public function testSameSubscriberAndSameUtcDayStayStable(): void
    {
        $subscriber = SubscriberId::fromString('tel:1000000001');
        $morning = new DateTimeImmutable('2026-11-23 00:00:00 UTC');
        $evening = new DateTimeImmutable('2026-11-23 23:59:59 UTC');

        $first = ExternalTrxId::forBillingDay($subscriber, $morning);
        $second = ExternalTrxId::forBillingDay($subscriber, $evening);

        $this->assertSame($first->value(), $second->value());
        $this->assertSame('2026-11-23:' . hash('sha256', 'tel:1000000001'), $first->value());
    }

    public function testUtcDateIgnoresTheLocalOffset(): void
    {
        $subscriber = SubscriberId::fromString('tel:1000000001');
        $utc = new DateTimeImmutable('2026-11-23 22:30:00 UTC');
        $moscow = $utc->setTimezone(new DateTimeZone('Europe/Moscow'));

        $this->assertSame(
            ExternalTrxId::forBillingDay($subscriber, $utc)->value(),
            ExternalTrxId::forBillingDay($subscriber, $moscow)->value(),
        );
        $this->assertStringStartsWith('2026-11-23:', ExternalTrxId::forBillingDay($subscriber, $moscow)->value());
    }

    public function testNextUtcDayChangesTheKey(): void
    {
        $subscriber = SubscriberId::fromString('tel:1000000001');
        $today = ExternalTrxId::forBillingDay($subscriber, new DateTimeImmutable('2026-11-23 23:59:59 UTC'));
        $tomorrow = ExternalTrxId::forBillingDay($subscriber, new DateTimeImmutable('2026-11-24 00:00:00 UTC'));

        $this->assertNotSame($today->value(), $tomorrow->value());
        $this->assertStringStartsWith('2026-11-24:', $tomorrow->value());
    }

    public function testAnotherSubscriberChangesTheKey(): void
    {
        $instant = new DateTimeImmutable('2026-11-23 12:00:00 UTC');
        $first = ExternalTrxId::forBillingDay(SubscriberId::fromString('tel:1000000001'), $instant);
        $second = ExternalTrxId::forBillingDay(SubscriberId::fromString('tel:1000000002'), $instant);

        $this->assertNotSame($first->value(), $second->value());
    }

    public function testReadsTheInjectedClockAndOmitsSecrets(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2020-01-02 15:04:05 Europe/Moscow'));
        $subscriber = SubscriberId::fromString('tel:0042');
        $key = ExternalTrxId::forSubscriberNow($subscriber, $clock);

        $this->assertSame($key->value(), ExternalTrxId::forSubscriberNow($subscriber, $clock)->value());
        $this->assertStringStartsWith('2020-01-02:', $key->value());
        $this->assertStringNotContainsString('tel:0042', $key->value());
        $this->assertStringNotContainsString('password', $key->value());
        $this->assertLessThanOrEqual(128, strlen($key->value()));
    }
}

final class FrozenClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $instant)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
