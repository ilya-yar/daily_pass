<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Q4 default until that question is answered on its own.
 * The same three values are enforced by subscriber_status_check.
 */
enum SubscriberStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Inactive = 'INACTIVE';
}
