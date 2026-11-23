<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\SubscriptionEventKey;
use App\Domain\SubscriptionNotificationRepository;
use yii\db\Connection;
use yii\db\Exception;

final class PgsqlSubscriptionNotificationRepository implements SubscriptionNotificationRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function recordNew(SubscriptionEventKey $key): bool
    {
        try {
            $inserted = $this->connection->createCommand(
                'INSERT INTO subscription_notification (subscriber_id, status, time_stamp, frequency)
                 VALUES (:subscriber_id, :status, :time_stamp, :frequency)',
                [
                    ':subscriber_id' => $key->subscriberId->value(),
                    ':status' => $key->status->value,
                    ':time_stamp' => $key->timeStamp,
                    ':frequency' => $key->frequency,
                ],
            )->execute();
        } catch (Exception $exception) {
            if (str_contains($exception->getMessage(), 'subscription_notification_event_unique')) {
                return false;
            }
            throw $exception;
        }

        return $inserted === 1;
    }
}
