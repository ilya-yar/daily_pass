<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Domain\MtMessage;
use Tests\ApiTester;
use yii\db\Connection;

final class OptInCest
{
    public function newSubscriberBecomesActiveAndWelcomeIsVisible(ApiTester $tester): void
    {
        $msisdn = 'tel:3000' . random_int(100000, 999999);
        $this->postOptIn($tester, $msisdn);

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'subscriberId' => $msisdn,
            'status' => 'ACTIVE',
        ]);
        $tester->assertSame(1, $this->welcomeCount($tester, $msisdn));
        $tester->assertSame('ACTIVE', $this->storedStatus($msisdn));
    }

    public function repeatForActiveDoesNotSendASecondWelcome(ApiTester $tester): void
    {
        $msisdn = 'tel:3001' . random_int(100000, 999999);
        $this->postOptIn($tester, $msisdn);
        $tester->seeResponseCodeIs(200);
        $this->postOptIn($tester, $msisdn);

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'subscriberId' => $msisdn,
            'status' => 'ACTIVE',
        ]);
        $tester->assertSame(1, $this->welcomeCount($tester, $msisdn));
        $tester->assertSame(1, $this->subscriberCount($msisdn));
    }

    public function junkMsisdnIsRejectedWithoutASubscriberRow(ApiTester $tester): void
    {
        $msisdn = 'not-a-subscriber';
        $this->postOptIn($tester, $msisdn);

        $tester->seeResponseCodeIs(400);
        $tester->seeResponseContainsJson(['error' => 'Invalid subscriber id']);
        $tester->assertSame(0, $this->subscriberCount($msisdn));
        $tester->assertSame(0, $this->welcomeCount($tester, $msisdn));
    }

    private function postOptIn(ApiTester $tester, string $msisdn): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
    }

    private function welcomeCount(ApiTester $tester, string $destination): int
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
            if (($row['message'] ?? null) === MtMessage::WELCOME && $destinations === [$destination]) {
                $count++;
            }
        }

        return $count;
    }

    private function storedStatus(string $msisdn): string
    {
        $status = $this->connection()->createCommand(
            'SELECT status FROM subscriber WHERE subscriber_id = :subscriber_id',
            [':subscriber_id' => $msisdn],
        )->queryScalar();
        if (!is_string($status)) {
            throw new \RuntimeException('Subscriber row was not stored.');
        }

        return $status;
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
