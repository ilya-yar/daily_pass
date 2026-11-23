<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Domain\HealthStatus;
use Tests\ApiTester;

final class HealthCest
{
    public function returnsOkWhenTheDatabaseIsUp(ApiTester $tester): void
    {
        $tester->sendGet('/health');
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['status' => HealthStatus::OK]);
    }
}
