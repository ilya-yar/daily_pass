<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Q10 event identity. No foreign key: PENDING/CHARGE may arrive before a local subscriber row.
 */
final class m261124_000100_create_subscription_notification extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%subscription_notification}}', [
            'id' => $this->bigPrimaryKey(),
            'subscriber_id' => $this->string(128)->notNull(),
            'status' => $this->string(32)->notNull(),
            'time_stamp' => $this->string(128)->notNull(),
            'frequency' => $this->string(32)->notNull(),
        ]);
        $this->createIndex(
            'subscription_notification_event_unique',
            '{{%subscription_notification}}',
            ['subscriber_id', 'status', 'time_stamp', 'frequency'],
            true,
        );
        $this->execute(
            "ALTER TABLE {{%subscription_notification}} ADD CONSTRAINT subscription_notification_status_check "
            . "CHECK (status IN ('REGISTERED', 'UNREGISTERED', 'PENDING', 'CHARGE'))",
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%subscription_notification}}');
    }
}
