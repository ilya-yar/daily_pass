<?php

declare(strict_types=1);

namespace App\Application\Platform;

interface PlatformClient
{
    public function optIn(string $subscriberId): PlatformResult;

    public function optOut(string $subscriberId): PlatformResult;

    public function sendSms(string $message, string $destinationAddress, string $sourceAddress): PlatformResult;

    /**
     * Sends the given externalTrxId unchanged. A retry must reuse the same value.
     */
    public function debit(
        string $externalTrxId,
        string $subscriberId,
        string $amount,
        string $currency,
    ): PlatformResult;
}
