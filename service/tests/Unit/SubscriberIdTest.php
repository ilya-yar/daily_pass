<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\SubscriberId;

final class SubscriberIdTest extends \Codeception\Test\Unit
{
    public function testAcceptsTheExampleAndKeepsItWhole(): void
    {
        $subscriberId = SubscriberId::fromString('tel:1000000001');

        $this->assertSame('tel:1000000001', $subscriberId->value());
    }

    public function testAcceptsAShortAliasWithoutStrippingDigits(): void
    {
        $subscriberId = SubscriberId::fromString('tel:0042');

        $this->assertSame('tel:0042', $subscriberId->value());
    }

    public function testRejectsEmptyPrefixAndNonDigitTails(): void
    {
        foreach (['', '1000000001', 'tel:', 'tel:1000000001a', 'tel:+1000000001', 'TEL:1000000001'] as $value) {
            try {
                SubscriberId::fromString($value);
                $this->fail('Invalid subscriber id must be rejected: ' . $value);
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('Subscriber id must be tel: followed by digits.', $exception->getMessage());
            }
        }
    }
}
