<?php

declare(strict_types=1);

namespace Tests\Mock;

use Tests\MockTester;

final class NotificationPushCest
{
    public function postsSectionBFieldsToTheReceiver(MockTester $tester): void
    {
        $marker = 'tel:' . random_int(1000000000, 1999999999);
        $stamp = '2026-11-23T12:00:00.000+00:00';
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/mock/notifications', [
            'callbackUrl' => 'http://127.0.0.1:8080/mock/inbox',
            'subscriberId' => $marker,
            'status' => 'REGISTERED',
            'frequency' => 'DAILY',
            'timeStamp' => $stamp,
        ]);
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['statusCode' => 'S1000']);

        $tester->sendGet('/mock/inbox');
        $tester->seeResponseCodeIs(200);
        $tester->assertContains(
            [
                'applicationId' => 'APP_000123',
                'subscriberId' => $marker,
                'frequency' => 'DAILY',
                'status' => 'REGISTERED',
                'version' => '1.0',
                'timeStamp' => $stamp,
            ],
            $this->events($tester),
        );
    }

    public function sendsAgainWhenTheSamePushIsRepeated(MockTester $tester): void
    {
        $marker = 'tel:' . random_int(1000000000, 1999999999);
        $body = [
            'callbackUrl' => 'http://127.0.0.1:8080/mock/inbox',
            'subscriberId' => $marker,
            'status' => 'PENDING',
            'frequency' => 'DAILY',
            'timeStamp' => '2026-11-23T12:00:00.000+00:00',
        ];
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/mock/notifications', $body);
        $tester->sendPost('/mock/notifications', $body);

        $tester->sendGet('/mock/inbox');
        $count = 0;
        foreach ($this->events($tester) as $event) {
            if (($event['subscriberId'] ?? null) === $marker) {
                $count++;
            }
        }
        $tester->assertSame(2, $count);
    }

    public function reportsDeliveryFailureWithoutDying(MockTester $tester): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/mock/notifications', [
            'callbackUrl' => 'http://127.0.0.1:1/nowhere',
            'subscriberId' => 'tel:1000000001',
            'status' => 'CHARGE',
            'frequency' => 'DAILY',
        ]);
        $tester->seeResponseCodeIs(502);
        $tester->seeResponseContainsJson(['statusCode' => 'E1000']);
        $tester->dontSeeResponseContains('password');

        $tester->sendGet('/health');
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['status' => 'ok']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(MockTester $tester): array
    {
        $decoded = json_decode($tester->grabResponse(), true);
        $tester->assertIsArray($decoded);
        $tester->assertIsArray($decoded['events'] ?? null);
        $events = [];
        foreach ($decoded['events'] as $event) {
            $tester->assertIsArray($event);
            $events[] = $event;
        }

        return $events;
    }
}
