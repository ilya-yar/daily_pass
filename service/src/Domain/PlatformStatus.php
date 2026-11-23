<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * §E classification only. Retry counts stay in the charging policy.
 */
final readonly class PlatformStatus
{
    private function __construct(
        private string $code,
        private ChargeDisposition $disposition,
        private ?ChargeConsequence $consequence,
    ) {
    }

    public static function fromCode(string $code): self
    {
        return match ($code) {
            'S1000' => new self($code, ChargeDisposition::TerminalOk, null),
            'E1337' => new self($code, ChargeDisposition::NonRetryable, null),
            'E1308' => new self($code, ChargeDisposition::NonRetryable, ChargeConsequence::Suspend),
            'E1326' => new self($code, ChargeDisposition::Retryable, ChargeConsequence::GraceRetry),
            'E1318' => new self($code, ChargeDisposition::Retryable, ChargeConsequence::ThrottleBackoff),
            default => new self($code, ChargeDisposition::NonRetryable, null),
        };
    }

    public function code(): string
    {
        return $this->code;
    }

    public function disposition(): ChargeDisposition
    {
        return $this->disposition;
    }

    public function consequence(): ?ChargeConsequence
    {
        return $this->consequence;
    }

    public function isRetryable(): bool
    {
        return $this->disposition === ChargeDisposition::Retryable;
    }
}
