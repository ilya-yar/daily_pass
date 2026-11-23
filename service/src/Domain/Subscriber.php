<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Subscriber
{
    public function __construct(
        public SubscriberId $id,
        public SubscriberStatus $status,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === SubscriberStatus::Active;
    }
}
