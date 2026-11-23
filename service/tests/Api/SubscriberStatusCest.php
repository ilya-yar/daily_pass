<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\ApiTester;

final class SubscriberStatusCest
{
    public function afterOptInShowsActive(ApiTester $tester): void
    {
        $msisdn = 'tel:3100' . random_int(100000, 999999);
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
        $tester->seeResponseCodeIs(200);

        $tester->sendGet('/subscribers/' . rawurlencode($msisdn));
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'subscriberId' => $msisdn,
            'status' => 'ACTIVE',
        ]);
    }

    public function unknownIdDoesNotReturnAnotherSubscriber(ApiTester $tester): void
    {
        $known = 'tel:3101' . random_int(100000, 999999);
        $unknown = 'tel:3102' . random_int(100000, 999999);
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $known]);
        $tester->seeResponseCodeIs(200);

        $tester->sendGet('/subscribers/' . rawurlencode($unknown));
        $tester->seeResponseCodeIs(404);
        $tester->seeResponseContainsJson(['error' => 'Subscriber not found']);
        $tester->dontSeeResponseContainsJson(['subscriberId' => $known]);
        $tester->dontSeeResponseContainsJson(['status' => 'ACTIVE']);
    }

    public function junkIdIsRejectedWithoutLookingUpAPeer(ApiTester $tester): void
    {
        $tester->sendGet('/subscribers/' . rawurlencode('not-a-subscriber'));
        $tester->seeResponseCodeIs(400);
        $tester->seeResponseContainsJson(['error' => 'Invalid subscriber id']);
        $tester->dontSeeResponseContainsJson(['status' => 'ACTIVE']);
    }
}
