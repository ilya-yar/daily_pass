<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\ChargeAttemptRepository;
use App\Domain\ExternalTrxId;
use App\Domain\SubscriberId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Finance lookup: only a stored S1000 counts as charged. E1326 and E1337 do not.
 */
final class GetDailyCharge
{
    public function __construct(private readonly ChargeAttemptRepository $attempts)
    {
    }

    public function execute(SubscriberId $subscriberId, string $billingDay): DailyChargeLookup
    {
        $instant = $this->utcDay($billingDay);
        $key = ExternalTrxId::forBillingDay($subscriberId, $instant)->value();
        $referenceId = $this->attempts->findSuccessfulReference($key);
        if ($referenceId === null) {
            return new DailyChargeLookup($subscriberId->value(), $billingDay, false, null);
        }

        return new DailyChargeLookup($subscriberId->value(), $billingDay, true, $referenceId);
    }

    private function utcDay(string $billingDay): DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d', $billingDay, new DateTimeZone('UTC'));
        if ($instant === false || $instant->format('Y-m-d') !== $billingDay) {
            throw new \InvalidArgumentException('Billing day must be a UTC calendar date.');
        }

        return $instant;
    }
}
