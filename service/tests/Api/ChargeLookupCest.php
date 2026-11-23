<?php

declare(strict_types=1);

namespace Tests\Api;

use DateTimeImmutable;
use DateTimeZone;
use Tests\ApiTester;
use yii\db\Connection;

final class ChargeLookupCest
{
    public function afterSuccessfulChargeReturnsTheSameReference(ApiTester $tester): void
    {
        $msisdn = 'tel:3500' . random_int(100000, 999999);
        $this->setMockChargeStatus($tester, 'S1000');
        $this->optIn($tester, $msisdn);
        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);
        $referenceId = $this->successfulReference($msisdn);
        $tester->assertNotSame('', $referenceId);

        $date = $this->utcDay();
        $tester->sendGet('/charges?msisdn=' . rawurlencode($msisdn) . '&date=' . $date);
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'msisdn' => $msisdn,
            'date' => $date,
            'charged' => true,
            'referenceId' => $referenceId,
        ]);
        $tester->dontSeeResponseContains('password');
        $tester->dontSeeResponseContains('local-mock-password');
    }

    public function beforeChargeAndAfterInsufficientBalanceAreNotCharged(ApiTester $tester): void
    {
        $msisdn = 'tel:3501' . random_int(100000, 999999);
        $this->optIn($tester, $msisdn);

        $date = $this->utcDay();
        $tester->sendGet('/charges?msisdn=' . rawurlencode($msisdn) . '&date=' . $date);
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'msisdn' => $msisdn,
            'date' => $date,
            'charged' => false,
        ]);
        $tester->dontSeeResponseContainsJson(['charged' => true]);
        $tester->dontSeeResponseContains('password');

        $this->setMockChargeStatus($tester, 'E1326');
        $tester->sendPost('/jobs/daily-charge');
        $tester->seeResponseCodeIs(200);

        $tester->sendGet('/charges?msisdn=' . rawurlencode($msisdn) . '&date=' . $date);
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['charged' => false]);
        $tester->dontSeeResponseContainsJson(['charged' => true]);
        $tester->dontSeeResponseContains('referenceId');
        $tester->dontSeeResponseContains('password');
    }

    public function unknownSubscriberIsNotCharged(ApiTester $tester): void
    {
        $msisdn = 'tel:3502' . random_int(100000, 999999);
        $tester->sendGet('/charges?msisdn=' . rawurlencode($msisdn) . '&date=' . $this->utcDay());
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['msisdn' => $msisdn, 'charged' => false]);
    }

    private function utcDay(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
    }

    private function optIn(ApiTester $tester, string $msisdn): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
        $tester->seeResponseCodeIs(200);
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

    private function successfulReference(string $msisdn): string
    {
        $referenceId = $this->connection()->createCommand(
            'SELECT a.reference_id
             FROM charge_attempt a
             JOIN subscriber s ON s.id = a.subscriber_id
             WHERE s.subscriber_id = :subscriber_id AND a.status = :status',
            [':subscriber_id' => $msisdn, ':status' => 'S1000'],
        )->queryScalar();
        if (!is_string($referenceId) || $referenceId === '') {
            throw new \RuntimeException('Successful charge was not stored.');
        }

        return $referenceId;
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
