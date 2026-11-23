<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Domain\MtMessage;
use Tests\ApiTester;
use yii\db\Connection;

/**
 * F1–F5 against the mock in one run: opt-in, webhook, charge-once, status, STOP.
 */
final class EndToEndFlowsCest
{
    public function _before(ApiTester $tester): void
    {
        $this->mockPost($tester, '/mock/charge-status', ['statusCode' => 'S1000']);
    }

    public function walksOptInWebhookChargeStatusAndStopAgainstTheMock(ApiTester $tester): void
    {
        $charged = 'tel:3600' . random_int(100000, 999999);
        $stopped = 'tel:3601' . random_int(100000, 999999);
        $stamp = '2026-11-24T15:00:00.000+00:00';

        $this->optIn($tester, $charged);
        $tester->sendGet('/subscribers/' . rawurlencode($charged));
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['subscriberId' => $charged, 'status' => 'ACTIVE']);
        $tester->assertSame(1, $this->smsCount($tester, $charged, MtMessage::WELCOME));

        $this->pushRegistered($tester, $charged, $stamp);
        $this->pushRegistered($tester, $charged, $stamp);
        $tester->assertSame(1, $this->subscriberCount($charged));
        $tester->assertSame(0, $this->successfulAttemptCount($charged));

        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $attempts = $this->successfulAttempts($charged);
        $tester->assertCount(1, $attempts);
        $referenceId = $attempts[0]['reference_id'];
        $tester->assertSame(1, $this->smsCount($tester, $charged, MtMessage::receipt($referenceId)));

        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $tester->assertCount(1, $this->successfulAttempts($charged));

        $this->optIn($tester, $stopped);
        $this->postMoStop($tester, $stopped);
        $tester->sendGet('/subscribers/' . rawurlencode($stopped));
        $tester->seeResponseContainsJson(['status' => 'INACTIVE']);
        $tester->assertSame(1, $this->smsCount($tester, $stopped, MtMessage::GOODBYE));

        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $tester->assertSame(0, $this->successfulAttemptCount($stopped));
        $tester->assertCount(1, $this->successfulAttempts($charged));
    }

    private function optIn(ApiTester $tester, string $msisdn): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
        $tester->seeResponseCodeIs(200);
    }

    private function postMoStop(ApiTester $tester, string $msisdn): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/webhooks/mo', [
            'applicationId' => 'APP_000123',
            'sourceAddress' => $msisdn,
            'message' => 'STOP',
            'encoding' => '0',
            'version' => '1.0',
        ]);
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['statusCode' => 'S1000']);
    }

    private function pushRegistered(ApiTester $tester, string $msisdn, string $stamp): void
    {
        $this->mockPost($tester, '/mock/notifications', [
            'subscriberId' => $msisdn,
            'status' => 'REGISTERED',
            'frequency' => 'DAILY',
            'timeStamp' => $stamp,
        ]);
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
        $decoded = json_decode($raw, true);
        $tester->assertIsArray($decoded);
        $tester->assertSame('S1000', $decoded['statusCode'] ?? null);
    }

    private function smsCount(ApiTester $tester, string $destination, string $message): int
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
        $tester->assertStringNotContainsString('password', json_encode($decoded, JSON_THROW_ON_ERROR));
        $messages = $decoded['messages'] ?? null;
        $tester->assertIsArray($messages);

        $count = 0;
        foreach ($messages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $destinations = $row['destinationAddresses'] ?? null;
            if (($row['message'] ?? null) === $message && $destinations === [$destination]) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array{reference_id: string}>
     */
    private function successfulAttempts(string $msisdn): array
    {
        $rows = $this->connection()->createCommand(
            'SELECT a.reference_id
             FROM charge_attempt a
             JOIN subscriber s ON s.id = a.subscriber_id
             WHERE s.subscriber_id = :subscriber_id AND a.status = :status',
            [':subscriber_id' => $msisdn, ':status' => 'S1000'],
        )->queryAll();
        $attempts = [];
        foreach ($rows as $row) {
            $referenceId = $row['reference_id'] ?? null;
            if (!is_string($referenceId)) {
                throw new \RuntimeException('Charge attempt row is incomplete.');
            }
            $attempts[] = ['reference_id' => $referenceId];
        }

        return $attempts;
    }

    private function successfulAttemptCount(string $msisdn): int
    {
        return count($this->successfulAttempts($msisdn));
    }

    private function subscriberCount(string $msisdn): int
    {
        $count = $this->connection()->createCommand(
            'SELECT COUNT(*) FROM subscriber WHERE subscriber_id = :subscriber_id',
            [':subscriber_id' => $msisdn],
        )->queryScalar();

        return (int) $count;
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
