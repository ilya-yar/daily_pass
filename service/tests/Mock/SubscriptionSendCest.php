<?php

declare(strict_types=1);

namespace Tests\Mock;

use Tests\MockTester;

final class SubscriptionSendCest
{
    public function registersOnOptIn(MockTester $tester): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscription/send', $this->body('1'));

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'version' => '1.0',
            'requestId' => '1374746416574',
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
            'subscriptionStatus' => 'REGISTERED',
        ]);
    }

    public function unregistersOnOptOut(MockTester $tester): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscription/send', $this->body('0'));

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
            'subscriptionStatus' => 'UNREGISTERED',
        ]);
    }

    public function rejectsAnUnknownActionWithoutRegistering(MockTester $tester): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscription/send', $this->body('2'));

        $tester->seeResponseCodeIs(400);
        $tester->dontSeeResponseContainsJson(['statusCode' => 'S1000']);
        $tester->dontSeeResponseContainsJson(['subscriptionStatus' => 'REGISTERED']);
    }

    public function rejectsBrokenJsonWithoutSuccess(MockTester $tester): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscription/send', '{');

        $tester->seeResponseCodeIs(400);
        $tester->dontSeeResponseContains('"statusCode":"S1000"');
    }

    /**
     * @return array<string, string>
     */
    private function body(string $action): array
    {
        return [
            'applicationId' => 'APP_000123',
            'password' => 'local-mock-password',
            'version' => '1.0',
            'action' => $action,
            'subscriberId' => 'tel:1000000001',
        ];
    }
}
