<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Domain\MtMessage;
use Tests\ApiTester;
use yii\db\Connection;

final class MoStopCest
{
    public function stopMarksInactiveSendsGoodbyeAndTheNextJobDoesNotCharge(ApiTester $tester): void
    {
        $msisdn = 'tel:3400' . random_int(100000, 999999);
        $this->optIn($tester, $msisdn);
        $this->postMo($tester, $msisdn, 'STOP');

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'Success',
        ]);
        $tester->sendGet('/subscribers/' . rawurlencode($msisdn));
        $tester->seeResponseContainsJson([
            'subscriberId' => $msisdn,
            'status' => 'INACTIVE',
        ]);
        $tester->assertSame(1, $this->goodbyeCount($tester, $msisdn));

        $this->setMockChargeStatus($tester, 'S1000');
        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $tester->assertSame(0, $this->successfulAttemptCount($msisdn));
        $tester->assertSame(1, $this->goodbyeCount($tester, $msisdn));
    }

    public function repeatStopDoesNotSendASecondGoodbye(ApiTester $tester): void
    {
        $msisdn = 'tel:3401' . random_int(100000, 999999);
        $this->optIn($tester, $msisdn);
        $this->postMo($tester, $msisdn, 'STOP');
        $this->postMo($tester, $msisdn, 'STOP');

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['statusCode' => 'S1000']);
        $tester->assertSame(1, $this->goodbyeCount($tester, $msisdn));
        $tester->assertSame(1, $this->subscriberCount($msisdn));
        $tester->sendGet('/subscribers/' . rawurlencode($msisdn));
        $tester->seeResponseContainsJson(['status' => 'INACTIVE']);
    }

    public function unknownSourceAddressAnswersSuccessWithoutASubscriber(ApiTester $tester): void
    {
        $msisdn = 'tel:3402' . random_int(100000, 999999);
        $this->postMo($tester, $msisdn, 'STOP');

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['statusCode' => 'S1000']);
        $tester->assertSame(0, $this->subscriberCount($msisdn));
        $tester->assertSame(0, $this->goodbyeCount($tester, $msisdn));
    }

    private function optIn(ApiTester $tester, string $msisdn): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
        $tester->seeResponseCodeIs(200);
    }

    private function postMo(ApiTester $tester, string $sourceAddress, string $message): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/webhooks/mo', [
            'applicationId' => 'APP_000123',
            'sourceAddress' => $sourceAddress,
            'message' => $message,
            'encoding' => '0',
            'version' => '1.0',
        ]);
    }

    private function setMockChargeStatus(ApiTester $tester, string $code): void
    {
        $payload = json_encode(['statusCode' => $code], JSON_THROW_ON_ERROR);
        $handle = curl_init('http://platform-mock:8080/mock/charge-status');
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

    private function goodbyeCount(ApiTester $tester, string $destination): int
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
        foreach ($messages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $destinations = $row['destinationAddresses'] ?? null;
            if (($row['message'] ?? null) === MtMessage::GOODBYE && $destinations === [$destination]) {
                $count++;
            }
        }

        return $count;
    }

    private function successfulAttemptCount(string $msisdn): int
    {
        $count = $this->connection()->createCommand(
            'SELECT COUNT(*) FROM charge_attempt a
             JOIN subscriber s ON s.id = a.subscriber_id
             WHERE s.subscriber_id = :subscriber_id AND a.status = :status',
            [':subscriber_id' => $msisdn, ':status' => 'S1000'],
        )->queryScalar();

        return (int) $count;
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
