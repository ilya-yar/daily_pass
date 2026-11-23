<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Status literals match App\Domain\SubscriberStatus (Q4 default).
 * The migration does not load the domain so it stays a schema change only.
 */
final class m261123_120000_create_subscriber_and_charge_attempt extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%subscriber}}', [
            'id' => $this->bigPrimaryKey(),
            'subscriber_id' => $this->string(128)->notNull(),
            'status' => $this->string(32)->notNull(),
            'created_at' => $this->timestamp(0)->notNull(),
            'status_changed_at' => $this->timestamp(0)->notNull(),
        ]);
        $this->createIndex('subscriber_subscriber_id_unique', '{{%subscriber}}', 'subscriber_id', true);
        $this->execute(
            "ALTER TABLE {{%subscriber}} ADD CONSTRAINT subscriber_status_check "
            . "CHECK (status IN ('ACTIVE', 'SUSPENDED', 'INACTIVE'))",
        );

        $this->createTable('{{%charge_attempt}}', [
            'id' => $this->bigPrimaryKey(),
            'subscriber_id' => $this->bigInteger()->notNull(),
            'status' => $this->string(32)->notNull(),
            'external_trx_id' => $this->string(128)->notNull(),
            'reference_id' => $this->string(128)->null(),
            'occurred_at' => $this->timestamp(0)->notNull(),
        ]);
        $this->createIndex(
            'charge_attempt_external_trx_id_unique',
            '{{%charge_attempt}}',
            'external_trx_id',
            true,
        );
        $this->createIndex('charge_attempt_subscriber_id_idx', '{{%charge_attempt}}', 'subscriber_id');
        $this->addForeignKey(
            'fk_charge_attempt_subscriber',
            '{{%charge_attempt}}',
            'subscriber_id',
            '{{%subscriber}}',
            'id',
            'RESTRICT',
            'RESTRICT',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_charge_attempt_subscriber', '{{%charge_attempt}}');
        $this->dropTable('{{%charge_attempt}}');
        $this->dropTable('{{%subscriber}}');
    }
}
