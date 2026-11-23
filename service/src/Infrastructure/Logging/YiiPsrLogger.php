<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Maps PSR-3 to Yii file logs. Context must not include secrets.
 */
final class YiiPsrLogger extends AbstractLogger
{
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $text = (string) $message;
        if ($context !== []) {
            $text .= ' ' . json_encode(array_keys($context), JSON_THROW_ON_ERROR);
        }
        $category = 'application';
        match ((string) $level) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR => \Yii::error($text, $category),
            LogLevel::WARNING => \Yii::warning($text, $category),
            default => \Yii::info($text, $category),
        };
    }
}
