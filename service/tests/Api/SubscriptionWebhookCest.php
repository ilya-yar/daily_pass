<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\ApiTester;
use yii\db\Connection;

final class SubscriptionWebhookCest
{
    public function duplicateRegisteredLeavesOneActiveSubscriberAndNoCharge(ApiTester $tester): void
    {
        $msisdn = 'tel:3300' . random_int(100000, 999999);
        $stamp = '2026-11-24T12:00:00.000+00:00';
        $this->pushTwice($tester, $msisdn, 'REGISTERED', $stamp);

        $tester->sendGet('/subscribers/' . rawurlencode($msisdn));
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'subscriberId' => $msisdn,
            'status' => 'ACTIVE',
        ]);
        $tester->assertSame(0, $this->chargeCount($msisdn));
        $tester->assertSame(1, $this->subscriberCount($msisdn));
    }

    public function duplicateUnregisteredLeavesOneInactiveSubscriberAndNoCharge(ApiTester $tester): void
    {
        $msisdn = 'tel:3301' . random_int(100000, 999999);
        $stamp = '2026-11-24T12:01:00.000+00:00';
        $this->pushTwice($tester, $msisdn, 'UNREGISTERED', $stamp);

        $tester->sendGet('/subscribers/' . rawurlencode($msisdn));
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'subscriberId' => $msisdn,
            'status' => 'INACTIVE',
        ]);
        $tester->assertSame(0, $this->chargeCount($msisdn));
        $tester->assertSame(1, $this->subscriberCount($msisdn));
    }

    private function pushTwice(ApiTester $tester, string $msisdn, string $status, string $stamp): void
    {
        $body = [
            'subscriberId' => $msisdn,
            'status' => $status,
            'frequency' => 'DAILY',
            'timeStamp' => $stamp,
        ];
        $this->mockPush($tester, $body);
        $this->mockPush($tester, $body);
    }

    /**
     * @param array<string, string> $body
     */
    private function mockPush(ApiTester $tester, array $body): void
    {
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $handle = curl_init('http://platform-mock:8080/mock/notifications');
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
        $httpStatus = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        $tester->assertSame(200, $httpStatus);
        $tester->assertIsString($raw);
        $decoded = json_decode($raw, true);
        $tester->assertIsArray($decoded);
        $tester->assertSame('S1000', $decoded['statusCode'] ?? null);
    }

    private function chargeCount(string $msisdn): int
    {
        $count = $this->connection()->createCommand(
            'SELECT COUNT(*) FROM charge_attempt a
             JOIN subscriber s ON s.id = a.subscriber_id
             WHERE s.subscriber_id = :subscriber_id',
            [':subscriber_id' => $msisdn],
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
