<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\HealthStatus;

final class HealthStatusTest extends \Codeception\Test\Unit
{
    public function testNamesTheTwoOutcomesTheHealthEndpointReturns(): void
    {
        $this->assertSame('ok', HealthStatus::OK);
        $this->assertSame('error', HealthStatus::ERROR);
        $this->assertNotSame(HealthStatus::OK, HealthStatus::ERROR);
    }
}
