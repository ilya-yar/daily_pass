<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

interface SubscriberRepository
{
    public function find(SubscriberId $id): ?Subscriber;

    public function activate(SubscriberId $id, DateTimeImmutable $at): void;
}
