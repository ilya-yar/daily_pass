<?php

declare(strict_types=1);

namespace App\Application\Charging;

interface Delay
{
    public function waitSeconds(int $seconds): void;
}
