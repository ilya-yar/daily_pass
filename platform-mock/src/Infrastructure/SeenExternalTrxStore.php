<?php

declare(strict_types=1);

namespace Mock\Infrastructure;

use yii\db\Connection;
use yii\db\Exception;
use yii\db\IntegrityException;

/**
 * Seen keys live in the compose Postgres so a mock restart still answers E1337.
 */
final class SeenExternalTrxStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return bool true when this process recorded the key first
     */
    public function recordIfNew(string $externalTrxId): bool
    {
        $this->ensureTable();
        try {
            $this->connection->createCommand(
                'INSERT INTO mock_seen_external_trx (external_trx_id, seen_at)
                 VALUES (:external_trx_id, :seen_at)',
                [
                    ':external_trx_id' => $externalTrxId,
                    ':seen_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                ],
            )->execute();
        } catch (IntegrityException) {
            return false;
        } catch (Exception $exception) {
            if ($this->isUniqueViolation($exception)) {
                return false;
            }
            throw $exception;
        }

        return true;
    }

    private function ensureTable(): void
    {
        $this->connection->createCommand(
            'CREATE TABLE IF NOT EXISTS mock_seen_external_trx (
                external_trx_id varchar(128) PRIMARY KEY,
                seen_at timestamp NOT NULL
            )',
        )->execute();
    }

    private function isUniqueViolation(Exception $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '23505';
    }
}
