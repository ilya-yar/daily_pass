<?php

declare(strict_types=1);

namespace App\Application\Webhook;

use App\Domain\Clock;
use App\Domain\SubscriptionEventKey;
use App\Domain\SubscriptionNotificationRepository;
use App\Domain\SubscriptionNotificationStatus;
use App\Domain\SubscriberRepository;

/**
 * Q5: REGISTERED → ACTIVE, UNREGISTERED → INACTIVE, PENDING/CHARGE stored only.
 * No Direct Debit and no extra welcome/goodbye SMS.
 */
final class HandleSubscriptionNotification
{
    public function __construct(
        private readonly SubscriptionNotificationRepository $events,
        private readonly SubscriberRepository $subscribers,
        private readonly Clock $clock,
    ) {
    }

    public function execute(SubscriptionEventKey $key): void
    {
        if (!$this->events->recordNew($key)) {
            return;
        }

        match ($key->status) {
            SubscriptionNotificationStatus::Registered => $this->subscribers->activate(
                $key->subscriberId,
                $this->clock->now(),
            ),
            SubscriptionNotificationStatus::Unregistered => $this->subscribers->deactivate(
                $key->subscriberId,
                $this->clock->now(),
            ),
            SubscriptionNotificationStatus::Pending, SubscriptionNotificationStatus::Charge => null,
        };
    }
}
