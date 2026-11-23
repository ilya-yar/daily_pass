<?php

declare(strict_types=1);

namespace App\Domain;

enum ChargeConsequence: string
{
    case Suspend = 'suspend';
    case GraceRetry = 'grace-retry';
    case ThrottleBackoff = 'throttle-backoff';
}
