<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Q10: subscriberId + status + timeStamp + frequency identifies one Platform event.
 */
final readonly class SubscriptionEventKey
{
    public function __construct(
        public SubscriberId $subscriberId,
        public SubscriptionNotificationStatus $status,
        public string $timeStamp,
        public string $frequency,
    ) {
        if ($this->timeStamp === '' || $this->frequency === '') {
            throw new \InvalidArgumentException('Webhook timeStamp and frequency are required.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->subscriberId->value() === $other->subscriberId->value()
            && $this->status === $other->status
            && $this->timeStamp === $other->timeStamp
            && $this->frequency === $other->frequency;
    }
}
