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

    /**
     * Keys that must not be debited again: a local S1000 or a recorded E1337 (Q11).
     *
     * @param list<string> $externalTrxIds
     * @return list<string>
     */
    public function completedExternalTrxIds(array $externalTrxIds): array;

    public function recordSuccess(
        SubscriberId $subscriberId,
        string $externalTrxId,
        string $referenceId,
        DateTimeImmutable $occurredAt,
    ): void;

    public function recordDuplicate(
        SubscriberId $subscriberId,
        string $externalTrxId,
        ?string $referenceId,
        DateTimeImmutable $occurredAt,
    ): void;
}
