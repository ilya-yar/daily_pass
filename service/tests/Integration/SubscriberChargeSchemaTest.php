<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\SubscriberStatus;
use App\Infrastructure\Persistence\NewSubscriberWithCharge;
use App\Infrastructure\Persistence\SubscriberChargeWriter;
use DateTimeImmutable;
use yii\db\Connection;
use yii\db\Exception;

final class SubscriberChargeSchemaTest extends \Codeception\Test\Unit
{
    private const MIGRATION = 'm261123_120000_create_subscriber_and_charge_attempt';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->connection();
        $this->ensureMigrated();
    }

    protected function tearDown(): void
    {
        $this->connection->createCommand(
            'DELETE FROM charge_attempt WHERE subscriber_id IN (
                SELECT id FROM subscriber WHERE subscriber_id LIKE :prefix
            )',
            [':prefix' => 'tel:schema-%'],
        )->execute();
        $this->connection->createCommand(
            'DELETE FROM subscriber WHERE subscriber_id LIKE :prefix',
            [':prefix' => 'tel:schema-%'],
        )->execute();
        parent::tearDown();
    }

    public function testMigrationCreatesTheSubscriberAndChargeAttemptTables(): void
    {
        $this->assertNotNull($this->connection->getTableSchema('subscriber', true));
        $this->assertNotNull($this->connection->getTableSchema('charge_attempt', true));
        $this->assertSame(
            'charge_attempt_external_trx_id_unique',
            $this->connection->createCommand(
                'SELECT indexname FROM pg_indexes WHERE tablename = :table AND indexname = :index',
                [':table' => 'charge_attempt', ':index' => 'charge_attempt_external_trx_id_unique'],
            )->queryScalar(),
        );
    }

    public function testFailedAttemptStoresAnEmptyReference(): void
    {
        $this->writer()->insert($this->record('tel:schema-empty-ref', 'trx-empty-ref', null));

        $referenceId = $this->connection->createCommand(
            'SELECT reference_id FROM charge_attempt WHERE external_trx_id = :trx',
            [':trx' => 'trx-empty-ref'],
        )->queryScalar();

        $this->assertNull($referenceId);
    }

    public function testDuplicateExternalTrxIdRollsBackTheSecondSubscriber(): void
    {
        $writer = $this->writer();
        $writer->insert($this->record('tel:schema-first', 'trx-once', null));

        try {
            $writer->insert($this->record('tel:schema-second', 'trx-once', 'ref-2'));
            $this->fail('A second attempt with the same externalTrxId must be rejected.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('charge_attempt_external_trx_id_unique', $exception->getMessage());
        }

        $subscribers = $this->connection->createCommand(
            'SELECT subscriber_id FROM subscriber WHERE subscriber_id LIKE :prefix ORDER BY subscriber_id',
            [':prefix' => 'tel:schema-%'],
        )->queryColumn();
        $attempts = (int) $this->connection->createCommand(
            'SELECT COUNT(*) FROM charge_attempt WHERE external_trx_id = :trx',
            [':trx' => 'trx-once'],
        )->queryScalar();

        $this->assertSame(['tel:schema-first'], $subscribers);
        $this->assertSame(1, $attempts);
    }

    public function testUnknownSubscriberStatusIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->connection->createCommand(
            'INSERT INTO subscriber (subscriber_id, status, created_at, status_changed_at)
             VALUES (:subscriber_id, :status, :created_at, :created_at)',
            [
                ':subscriber_id' => 'tel:schema-bad-status',
                ':status' => 'UNKNOWN',
                ':created_at' => '2026-11-23 00:00:00',
            ],
        )->execute();
    }

    private function writer(): SubscriberChargeWriter
    {
        return new SubscriberChargeWriter($this->connection);
    }

    private function record(string $subscriberId, string $externalTrxId, ?string $referenceId): NewSubscriberWithCharge
    {
        $moment = new DateTimeImmutable('2026-11-23 00:00:00 UTC');

        return new NewSubscriberWithCharge(
            $subscriberId,
            SubscriberStatus::Active,
            $moment,
            'E1326',
            $externalTrxId,
            $referenceId,
            $moment,
        );
    }

    private function connection(): Connection
    {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'test');
        require_once dirname(__DIR__, 2) . '/vendor/yiisoft/yii2/Yii.php';

        $application = \Yii::$app ?? new \yii\web\Application(require dirname(__DIR__, 2) . '/config/web.php');
        $connection = $application->get('db');
        if (!$connection instanceof Connection) {
            throw new \RuntimeException('Database component is not configured.');
        }

        return $connection;
    }

    private function ensureMigrated(): void
    {
        if ($this->connection->getTableSchema('subscriber', true) !== null) {
            return;
        }

        $this->connection->createCommand(
            'CREATE TABLE IF NOT EXISTS migration (
                version varchar(180) NOT NULL PRIMARY KEY,
                apply_time integer
            )',
        )->execute();

        require_once dirname(__DIR__, 2) . '/migrations/' . self::MIGRATION . '.php';
        $migration = new \m261123_120000_create_subscriber_and_charge_attempt();
        $migration->db = $this->connection;
        $migration->up();
        $this->connection->createCommand()->insert('migration', [
            'version' => self::MIGRATION,
            'apply_time' => time(),
        ])->execute();
    }
}
