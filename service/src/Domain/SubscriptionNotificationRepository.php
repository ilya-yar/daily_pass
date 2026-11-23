<?php

declare(strict_types=1);

namespace App\Domain;

interface SubscriptionNotificationRepository
{
    /**
     * @return bool true when this event was stored for the first time
     */
    public function recordNew(SubscriptionEventKey $key): bool;
}
