<?php

declare(strict_types=1);

namespace App\Application\Charging;

use App\Application\Platform\PlatformClient;
use App\Application\Platform\PlatformRequestException;
use App\Domain\Amount;
use App\Domain\ChargeAttemptRepository;
use App\Domain\Clock;
use App\Domain\DuplicateChargeAttemptException;
use App\Domain\ExternalTrxId;
use App\Domain\MtMessage;
use App\Domain\Subscriber;
use App\Domain\SubscriberRepository;

/**
 * One Direct Debit per ACTIVE subscriber per UTC day.
 * A second run reuses the same externalTrxId and does not mint a new key.
 */
final class RunDailyCharge
{
    public function __construct(
        private readonly SubscriberRepository $subscribers,
        private readonly ChargeAttemptRepository $attempts,
        private readonly PlatformClient $platform,
        private readonly Clock $clock,
        private readonly Amount $fee,
        private readonly string $currency,
        private readonly string $smsSourceAddress,
    ) {
        if ($this->currency === '' || $this->smsSourceAddress === '') {
            throw new \InvalidArgumentException('Currency and SMS source address are required.');
        }
    }

    public function execute(): DailyChargeResult
    {
        $now = $this->clock->now();
        $active = $this->subscribers->listActive();
        $keysBySubscriber = [];
        $keys = [];
        foreach ($active as $subscriber) {
            $key = ExternalTrxId::forBillingDay($subscriber->id, $now)->value();
            $keysBySubscriber[$subscriber->id->value()] = $key;
            $keys[] = $key;
        }
        $alreadyCharged = array_fill_keys($this->attempts->successfulExternalTrxIds($keys), true);

        $charged = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($active as $subscriber) {
            $key = $keysBySubscriber[$subscriber->id->value()];
            if (isset($alreadyCharged[$key])) {
                $skipped++;
                continue;
            }
            if ($this->chargeOne($subscriber, $key, $now)) {
                $alreadyCharged[$key] = true;
                $charged++;
                continue;
            }
            $failed++;
        }

        return new DailyChargeResult($charged, $skipped, $failed);
    }

    private function chargeOne(Subscriber $subscriber, string $key, \DateTimeImmutable $now): bool
    {
        try {
            $result = $this->platform->debit(
                $key,
                $subscriber->id->value(),
                $this->fee->toDecimal(),
                $this->currency,
            );
        } catch (PlatformRequestException) {
            return false;
        }
        if ($result->statusCode !== 'S1000') {
            return false;
        }
        $referenceId = $result->field('referenceId');
        if ($referenceId === null || $referenceId === '') {
            return false;
        }
        try {
            $this->attempts->recordSuccess($subscriber->id, $key, $referenceId, $now);
        } catch (DuplicateChargeAttemptException) {
            return false;
        }
        $this->sendReceipt($subscriber, $referenceId);

        return true;
    }

    private function sendReceipt(Subscriber $subscriber, string $referenceId): void
    {
        try {
            $this->platform->sendSms(
                MtMessage::receipt($referenceId),
                $subscriber->id->value(),
                $this->smsSourceAddress,
            );
        } catch (PlatformRequestException) {
            return;
        }
    }
}
