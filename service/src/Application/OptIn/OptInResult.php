<?php

declare(strict_types=1);

namespace App\Application\OptIn;

use App\Domain\SubscriberStatus;

final readonly class OptInResult
{
    public function __construct(
        public string $subscriberId,
        public SubscriberStatus $status,
        public bool $alreadyActive,
    ) {
    }
}
