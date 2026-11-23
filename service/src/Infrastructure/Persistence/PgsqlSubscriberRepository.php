<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Subscriber;
use App\Domain\SubscriberId;
use App\Domain\SubscriberRepository;
use App\Domain\SubscriberStatus;
use DateTimeImmutable;
use DateTimeZone;
use yii\db\Connection;

final class PgsqlSubscriberRepository implements SubscriberRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(SubscriberId $id): ?Subscriber
    {
        $row = $this->connection->createCommand(
            'SELECT subscriber_id, status FROM subscriber WHERE subscriber_id = :subscriber_id',
            [':subscriber_id' => $id->value()],
        )->queryOne();
        if ($row === false) {
            return null;
        }
        $subscriberId = $row['subscriber_id'] ?? null;
        $status = $row['status'] ?? null;
        if (!is_string($subscriberId) || !is_string($status)) {
            throw new \RuntimeException('Subscriber row is incomplete.');
        }

        return new Subscriber(
            SubscriberId::fromString($subscriberId),
            SubscriberStatus::from($status),
        );
    }

    public function listActive(): array
    {
        $rows = $this->connection->createCommand(
            'SELECT subscriber_id, status FROM subscriber WHERE status = :status ORDER BY id',
            [':status' => SubscriberStatus::Active->value],
        )->queryAll();
        $subscribers = [];
        foreach ($rows as $row) {
            $subscriberId = $row['subscriber_id'] ?? null;
            $status = $row['status'] ?? null;
            if (!is_string($subscriberId) || !is_string($status)) {
                throw new \RuntimeException('Subscriber row is incomplete.');
            }
            $subscribers[] = new Subscriber(
                SubscriberId::fromString($subscriberId),
                SubscriberStatus::from($status),
            );
        }

        return $subscribers;
    }

    public function activate(SubscriberId $id, DateTimeImmutable $at): void
    {
        $atUtc = $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->connection->createCommand(
            'INSERT INTO subscriber (subscriber_id, status, created_at, status_changed_at)
             VALUES (:subscriber_id, :status, :created_at, :status_changed_at)
             ON CONFLICT (subscriber_id) DO UPDATE
             SET status = EXCLUDED.status, status_changed_at = EXCLUDED.status_changed_at',
            [
                ':subscriber_id' => $id->value(),
                ':status' => SubscriberStatus::Active->value,
                ':created_at' => $atUtc,
                ':status_changed_at' => $atUtc,
            ],
        )->execute();
    }
}
