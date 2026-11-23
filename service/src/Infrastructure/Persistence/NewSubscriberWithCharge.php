<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\SubscriberStatus;
use DateTimeImmutable;

final readonly class NewSubscriberWithCharge
{
    public function __construct(
        public string $subscriberId,
        public SubscriberStatus $status,
        public DateTimeImmutable $createdAt,
        public string $attemptStatus,
        public string $externalTrxId,
        public ?string $referenceId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
