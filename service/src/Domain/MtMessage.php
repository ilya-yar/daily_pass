<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Q7 default texts. Receipt and goodbye are added with those flows.
 */
final class MtMessage
{
    public const WELCOME = 'Daily Pass is active';

    public const GOODBYE = 'Daily Pass is stopped';

    public static function receipt(string $referenceId): string
    {
        if ($referenceId === '') {
            throw new \InvalidArgumentException('Receipt reference id is required.');
        }

        return 'Charged. Reference: ' . $referenceId;
    }
}
