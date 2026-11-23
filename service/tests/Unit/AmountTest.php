<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Amount;

final class AmountTest extends \Codeception\Test\Unit
{
    public function testStoresTwoDecimalPlacesAsMinorUnits(): void
    {
        $amount = Amount::fromDecimal('5.00');

        $this->assertSame(500, $amount->minorUnits());
        $this->assertSame('5.00', $amount->toDecimal());
        $this->assertSame(1, Amount::fromDecimal('0.01')->minorUnits());
        $this->assertSame('10.50', Amount::fromDecimal('10.50')->toDecimal());
    }

    public function testRejectsAmountsThatAreNotExactlyTwoDecimalPlaces(): void
    {
        foreach (['', '5', '5.0', '5.000', '-5.00', '5,00', '05.00'] as $value) {
            try {
                Amount::fromDecimal($value);
                $this->fail('Invalid amount must be rejected: ' . $value);
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('Amount must have exactly two decimal places.', $exception->getMessage());
            }
        }
    }
}
