<?php

declare(strict_types=1);

namespace App\Domain;

enum SubscriptionNotificationStatus: string
{
    case Registered = 'REGISTERED';
    case Unregistered = 'UNREGISTERED';
    case Pending = 'PENDING';
    case Charge = 'CHARGE';
}
