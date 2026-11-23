<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * One key per subscriber per UTC calendar day.
 * The attempt number is not an input, and no Platform secret is included.
 * The subscriber id is hashed so the value stays inside external_trx_id varchar(128).
 */
final readonly class ExternalTrxId
{
    private function __construct(private string $value)
    {
    }

    public static function forBillingDay(SubscriberId $subscriberId, DateTimeImmutable $instant): self
    {
        $day = $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $value = $day . ':' . hash('sha256', $subscriberId->value());

        return new self($value);
    }

    public static function forSubscriberNow(SubscriberId $subscriberId, Clock $clock): self
    {
        return self::forBillingDay($subscriberId, $clock->now());
    }

    public function value(): string
    {
        return $this->value;
    }
}
