<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ChargeConsequence;
use App\Domain\ChargeDisposition;
use App\Domain\PlatformStatus;

final class PlatformStatusTest extends \Codeception\Test\Unit
{
    public function testClassifiesEveryCodeFromTheTable(): void
    {
        $success = PlatformStatus::fromCode('S1000');
        $duplicate = PlatformStatus::fromCode('E1337');
        $permanent = PlatformStatus::fromCode('E1308');
        $balance = PlatformStatus::fromCode('E1326');
        $limit = PlatformStatus::fromCode('E1318');

        $this->assertSame(ChargeDisposition::TerminalOk, $success->disposition());
        $this->assertFalse($success->isRetryable());
        $this->assertNull($success->consequence());

        $this->assertSame(ChargeDisposition::NonRetryable, $duplicate->disposition());
        $this->assertFalse($duplicate->isRetryable());
        $this->assertNull($duplicate->consequence());

        $this->assertSame(ChargeDisposition::NonRetryable, $permanent->disposition());
        $this->assertFalse($permanent->isRetryable());
        $this->assertSame(ChargeConsequence::Suspend, $permanent->consequence());

        $this->assertSame(ChargeDisposition::Retryable, $balance->disposition());
        $this->assertTrue($balance->isRetryable());
        $this->assertSame(ChargeConsequence::GraceRetry, $balance->consequence());

        $this->assertSame(ChargeDisposition::Retryable, $limit->disposition());
        $this->assertTrue($limit->isRetryable());
        $this->assertSame(ChargeConsequence::ThrottleBackoff, $limit->consequence());
    }

    public function testUnknownCodeIsNotRetryable(): void
    {
        $unknown = PlatformStatus::fromCode('E9999');

        $this->assertSame('E9999', $unknown->code());
        $this->assertSame(ChargeDisposition::NonRetryable, $unknown->disposition());
        $this->assertFalse($unknown->isRetryable());
        $this->assertNull($unknown->consequence());
    }
}
