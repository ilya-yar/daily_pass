<?php

declare(strict_types=1);

namespace App\Domain;

enum ChargeDisposition: string
{
    case TerminalOk = 'terminal-ok';
    case NonRetryable = 'non-retry-able';
    case Retryable = 'retry-able';
}
