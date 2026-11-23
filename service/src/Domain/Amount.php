<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Decimal text with exactly two places, stored as minor units so money is never a float.
 */
final readonly class Amount
{
    private function __construct(private int $minorUnits)
    {
    }

    public static function fromDecimal(string $value): self
    {
        if (preg_match('/^(0|[1-9][0-9]*)\.([0-9]{2})$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('Amount must have exactly two decimal places.');
        }

        $minor = ltrim($matches[1] . $matches[2], '0');
        if ($minor === '') {
            $minor = '0';
        }
        $limit = (string) PHP_INT_MAX;
        $tooLong = strlen($minor) > strlen($limit);
        $tooLarge = strlen($minor) === strlen($limit) && $minor > $limit;
        if ($tooLong || $tooLarge) {
            throw new \InvalidArgumentException('Amount is too large.');
        }

        return new self((int) $minor);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function toDecimal(): string
    {
        $whole = intdiv($this->minorUnits, 100);
        $fraction = $this->minorUnits % 100;

        return $whole . '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
