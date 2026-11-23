<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

interface ChargeAttemptRepository
{
    /**
     * @param list<string> $externalTrxIds
     * @return list<string>
     */
    public function successfulExternalTrxIds(array $externalTrxIds): array;

    public function recordSuccess(
        SubscriberId $subscriberId,
        string $externalTrxId,
        string $referenceId,
        DateTimeImmutable $occurredAt,
    ): void;
}
