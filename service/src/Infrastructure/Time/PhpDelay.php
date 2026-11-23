<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use App\Application\Charging\Delay;

final class PhpDelay implements Delay
{
    public function waitSeconds(int $seconds): void
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Delay must be at least one second.');
        }
        sleep($seconds);
    }
}
