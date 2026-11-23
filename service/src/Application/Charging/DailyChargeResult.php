<?php

declare(strict_types=1);

namespace App\Application\Charging;

final readonly class DailyChargeResult
{
    public function __construct(
        public int $charged,
        public int $skipped,
        public int $failed,
    ) {
    }
}
