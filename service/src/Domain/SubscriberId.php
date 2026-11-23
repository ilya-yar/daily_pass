<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Q8: the whole tel: value is the subscriber id.
 * Digits are not a phone number and are not normalised.
 */
final readonly class SubscriberId
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^tel:[0-9]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('Subscriber id must be tel: followed by digits.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
