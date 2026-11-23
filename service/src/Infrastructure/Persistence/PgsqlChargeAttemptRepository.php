<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\ChargeAttemptRepository;
use App\Domain\DuplicateChargeAttemptException;
use App\Domain\SubscriberId;
use DateTimeImmutable;
use DateTimeZone;
use yii\db\Connection;
use yii\db\Exception;

final class PgsqlChargeAttemptRepository implements ChargeAttemptRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function successfulExternalTrxIds(array $externalTrxIds): array
    {
        if ($externalTrxIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [':status' => 'S1000'];
        foreach ($externalTrxIds as $index => $externalTrxId) {
            $name = ':trx' . $index;
            $placeholders[] = $name;
            $params[$name] = $externalTrxId;
        }
        $rows = $this->connection->createCommand(
            'SELECT external_trx_id FROM charge_attempt
             WHERE status = :status AND external_trx_id IN (' . implode(', ', $placeholders) . ')',
            $params,
        )->queryAll();

        $found = [];
        foreach ($rows as $row) {
            $value = $row['external_trx_id'] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \RuntimeException('Charge attempt row is incomplete.');
            }
            $found[] = $value;
        }

        return $found;
    }

    public function recordSuccess(
        SubscriberId $subscriberId,
        string $externalTrxId,
        string $referenceId,
        DateTimeImmutable $occurredAt,
    ): void {
        if ($externalTrxId === '' || $referenceId === '') {
            throw new \InvalidArgumentException('externalTrxId and referenceId are required for a successful charge.');
        }

        $occurredUtc = $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        try {
            $inserted = $this->connection->createCommand(
                'INSERT INTO charge_attempt (subscriber_id, status, external_trx_id, reference_id, occurred_at)
                 SELECT id, :status, :external_trx_id, :reference_id, :occurred_at
                 FROM subscriber WHERE subscriber_id = :subscriber_id',
                [
                    ':status' => 'S1000',
                    ':external_trx_id' => $externalTrxId,
                    ':reference_id' => $referenceId,
                    ':occurred_at' => $occurredUtc,
                    ':subscriber_id' => $subscriberId->value(),
                ],
            )->execute();
        } catch (Exception $exception) {
            if (str_contains($exception->getMessage(), 'charge_attempt_external_trx_id_unique')) {
                throw new DuplicateChargeAttemptException(
                    'A charge attempt with this externalTrxId already exists.',
                    previous: $exception,
                );
            }
            throw $exception;
        }
        if ($inserted !== 1) {
            throw new \RuntimeException('Successful charge was not stored.');
        }
    }
}
