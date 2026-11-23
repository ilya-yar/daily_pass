<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use yii\db\Connection;

/**
 * Subscriber and charge attempt are one transaction.
 * A failure on the attempt rolls the new subscriber back.
 */
final class SubscriberChargeWriter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function insert(NewSubscriberWithCharge $record): void
    {
        $this->assertRequired($record);
        $transaction = $this->connection->beginTransaction();
        try {
            $subscriberRowId = $this->insertSubscriber($record);
            $this->insertAttempt($subscriberRowId, $record);
            $transaction->commit();
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }
    }

    private function assertRequired(NewSubscriberWithCharge $record): void
    {
        if ($record->subscriberId === '' || $record->attemptStatus === '' || $record->externalTrxId === '') {
            throw new \InvalidArgumentException('Subscriber id, attempt status, and externalTrxId are required.');
        }
    }

    private function insertSubscriber(NewSubscriberWithCharge $record): string
    {
        $createdAt = $this->utcTimestamp($record->createdAt);
        $subscriberRowId = $this->connection->createCommand(
            'INSERT INTO subscriber (subscriber_id, status, created_at, status_changed_at)
             VALUES (:subscriber_id, :status, :created_at, :status_changed_at)
             RETURNING id',
            [
                ':subscriber_id' => $record->subscriberId,
                ':status' => $record->status->value,
                ':created_at' => $createdAt,
                ':status_changed_at' => $createdAt,
            ],
        )->queryScalar();

        if (is_int($subscriberRowId)) {
            return (string) $subscriberRowId;
        }
        if (!is_string($subscriberRowId) || !ctype_digit($subscriberRowId)) {
            throw new \RuntimeException('Subscriber insert did not return an id.');
        }

        return $subscriberRowId;
    }

    private function insertAttempt(string $subscriberRowId, NewSubscriberWithCharge $record): void
    {
        $referenceId = $record->referenceId === '' ? null : $record->referenceId;
        $this->connection->createCommand(
            'INSERT INTO charge_attempt (subscriber_id, status, external_trx_id, reference_id, occurred_at)
             VALUES (:subscriber_id, :status, :external_trx_id, :reference_id, :occurred_at)',
            [
                ':subscriber_id' => $subscriberRowId,
                ':status' => $record->attemptStatus,
                ':external_trx_id' => $record->externalTrxId,
                ':reference_id' => $referenceId,
                ':occurred_at' => $this->utcTimestamp($record->occurredAt),
            ],
        )->execute();
    }

    private function utcTimestamp(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
