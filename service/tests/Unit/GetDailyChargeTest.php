<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Audit\GetDailyCharge;
use App\Domain\ExternalTrxId;
use App\Domain\SubscriberId;
use DateTimeImmutable;

final class GetDailyChargeTest extends \Codeception\Test\Unit
{
    public function testSuccessfulChargeReturnsTheStoredReference(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $day = new DateTimeImmutable('2026-11-24 15:00:00 UTC');
        $key = ExternalTrxId::forBillingDay($id, $day)->value();
        $attempts = new InMemoryChargeAttemptRepository();
        $attempts->recordSuccess($id, $key, 'ref-fin', $day);

        $lookup = (new GetDailyCharge($attempts))->execute($id, '2026-11-24');

        $this->assertTrue($lookup->charged);
        $this->assertSame('ref-fin', $lookup->referenceId);
        $this->assertSame('tel:1000000001', $lookup->msisdn);
        $this->assertSame('2026-11-24', $lookup->date);
    }

    public function testMissingAttemptIsNotCharged(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $attempts = new InMemoryChargeAttemptRepository();

        $lookup = (new GetDailyCharge($attempts))->execute($id, '2026-11-24');

        $this->assertFalse($lookup->charged);
        $this->assertNull($lookup->referenceId);
    }

    public function testDuplicateAttemptIsNotCharged(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $day = new DateTimeImmutable('2026-11-24 15:00:00 UTC');
        $key = ExternalTrxId::forBillingDay($id, $day)->value();
        $attempts = new InMemoryChargeAttemptRepository();
        $attempts->recordDuplicate($id, $key, 'ref-dup', $day);

        $lookup = (new GetDailyCharge($attempts))->execute($id, '2026-11-24');

        $this->assertFalse($lookup->charged);
        $this->assertNull($lookup->referenceId);
    }

    public function testAnotherUtcDayDoesNotUseTodaysReference(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $day = new DateTimeImmutable('2026-11-24 15:00:00 UTC');
        $key = ExternalTrxId::forBillingDay($id, $day)->value();
        $attempts = new InMemoryChargeAttemptRepository();
        $attempts->recordSuccess($id, $key, 'ref-fin', $day);

        $lookup = (new GetDailyCharge($attempts))->execute($id, '2026-11-25');

        $this->assertFalse($lookup->charged);
        $this->assertNull($lookup->referenceId);
    }
}
