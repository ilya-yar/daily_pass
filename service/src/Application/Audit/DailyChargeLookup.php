<?php

declare(strict_types=1);

namespace App\Application\Audit;

final readonly class DailyChargeLookup
{
    public function __construct(
        public string $msisdn,
        public string $date,
        public bool $charged,
        public ?string $referenceId,
    ) {
    }
}
