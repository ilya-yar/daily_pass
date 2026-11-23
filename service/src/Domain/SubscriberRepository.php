<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

interface SubscriberRepository
{
    public function find(SubscriberId $id): ?Subscriber;

    /**
     * @return list<Subscriber>
     */
    public function listActive(): array;

    public function activate(SubscriberId $id, DateTimeImmutable $at): void;
}
