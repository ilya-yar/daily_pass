<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Domain\MtMessage;
use Tests\ApiTester;
use yii\db\Connection;

final class DailyChargeCest
{
    public function _before(ApiTester $tester): void
    {
        $this->setMockChargeStatus($tester, 'S1000');
    }

    public function chargesAnActiveSubscriberOnceAndSendsReceipt(ApiTester $tester): void
    {
        $msisdn = 'tel:3200' . random_int(100000, 999999);
        $this->optIn($tester, $msisdn);

        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);

        $attempts = $this->successfulAttempts($msisdn);
        $tester->assertCount(1, $attempts);
        $referenceId = $attempts[0]['reference_id'];
        $tester->assertNotSame('', $referenceId);
        $tester->assertSame(1, $this->receiptCount($tester, $msisdn, $referenceId));

        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $tester->assertCount(1, $this->successfulAttempts($msisdn));
        $tester->assertSame(1, $this->receiptCount($tester, $msisdn, $referenceId));
    }

    public function doesNotChargeAnInactiveSubscriber(ApiTester $tester): void
    {
        $msisdn = 'tel:3201' . random_int(100000, 999999);
        $this->insertInactive($msisdn);

        $before = $this->successfulAttemptCount($msisdn);
        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $tester->assertSame($before, $this->successfulAttemptCount($msisdn));
        $tester->assertSame(0, $this->receiptCount($tester, $msisdn, 'unused'));
    }

    private function optIn(ApiTester $tester, string $msisdn): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
        $tester->seeResponseCodeIs(200);
    }

    private function setMockChargeStatus(ApiTester $tester, string $code): void
    {
        $this->mockPost($tester, '/mock/charge-status', ['statusCode' => $code]);
    }

    /**
     * @param array<string, string> $body
     */
    private function mockPost(ApiTester $tester, string $path, array $body): void
    {
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $handle = curl_init('http://platform-mock:8080' . $path);
        if ($handle === false) {
            $tester->fail('Could not reach the platform mock.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        $tester->assertSame(200, $status);
        $tester->assertIsString($raw);
    }

    private function receiptCount(ApiTester $tester, string $destination, string $referenceId): int
    {
        $handle = curl_init('http://platform-mock:8080/sms');
        if ($handle === false) {
            $tester->fail('Could not read mock SMS log.');
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($handle);
        curl_close($handle);
        if (!is_string($raw)) {
            $tester->fail('Mock SMS log was not JSON.');
        }
        $decoded = json_decode($raw, true);
        $tester->assertIsArray($decoded);
        $messages = $decoded['messages'] ?? null;
        $tester->assertIsArray($messages);

        $count = 0;
        $expected = MtMessage::receipt($referenceId);
        foreach ($messages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $destinations = $row['destinationAddresses'] ?? null;
            if (($row['message'] ?? null) === $expected && $destinations === [$destination]) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array{external_trx_id: string, reference_id: string}>
     */
    private function successfulAttempts(string $msisdn): array
    {
        $rows = $this->connection()->createCommand(
            'SELECT a.external_trx_id, a.reference_id
             FROM charge_attempt a
             JOIN subscriber s ON s.id = a.subscriber_id
             WHERE s.subscriber_id = :subscriber_id AND a.status = :status',
            [':subscriber_id' => $msisdn, ':status' => 'S1000'],
        )->queryAll();
        $attempts = [];
        foreach ($rows as $row) {
            $externalTrxId = $row['external_trx_id'] ?? null;
            $referenceId = $row['reference_id'] ?? null;
            if (!is_string($externalTrxId) || !is_string($referenceId)) {
                throw new \RuntimeException('Charge attempt row is incomplete.');
            }
            $attempts[] = [
                'external_trx_id' => $externalTrxId,
                'reference_id' => $referenceId,
            ];
        }

        return $attempts;
    }

    private function successfulAttemptCount(string $msisdn): int
    {
        return count($this->successfulAttempts($msisdn));
    }

    private function insertInactive(string $msisdn): void
    {
        $this->connection()->createCommand(
            'INSERT INTO subscriber (subscriber_id, status, created_at, status_changed_at)
             VALUES (:subscriber_id, :status, :created_at, :created_at)',
            [
                ':subscriber_id' => $msisdn,
                ':status' => 'INACTIVE',
                ':created_at' => '2026-11-24 00:00:00',
            ],
        )->execute();
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
}
