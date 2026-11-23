<?php

declare(strict_types=1);

namespace Mock\Infrastructure;

final class ChargeStatus
{
    public const SUCCESS = 'S1000';

    public const DUPLICATE = 'E1337';

    public const PERMANENT = 'E1308';

    public const INSUFFICIENT = 'E1326';

    private const ALLOWED = [self::SUCCESS, self::DUPLICATE, self::PERMANENT, self::INSUFFICIENT];

    public static function configured(): string
    {
        $stored = self::readStored();
        if ($stored !== null) {
            return $stored;
        }

        return self::fromEnv();
    }

    public static function set(string $code): void
    {
        if (!self::isAllowed($code)) {
            throw new \InvalidArgumentException('Charge status must be S1000, E1337, E1308, or E1326.');
        }
        $path = self::path();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Charge status directory could not be created.');
        }
        $written = file_put_contents($path, $code);
        if ($written === false) {
            throw new \RuntimeException('Charge status could not be stored.');
        }
    }

    public static function isAllowed(string $code): bool
    {
        return in_array($code, self::ALLOWED, true);
    }

    private static function fromEnv(): string
    {
        $value = getenv('MOCK_CHARGE_STATUS');
        if ($value === false || $value === '') {
            return self::SUCCESS;
        }
        if (!self::isAllowed($value)) {
            throw new \RuntimeException('MOCK_CHARGE_STATUS must be S1000, E1337, E1308, or E1326.');
        }

        return $value;
    }

    private static function readStored(): ?string
    {
        $path = self::path();
        if (!is_file($path)) {
            return null;
        }
        $value = trim((string) file_get_contents($path));
        if ($value === '' || !self::isAllowed($value)) {
            return null;
        }

        return $value;
    }

    private static function path(): string
    {
        return dirname(__DIR__, 2) . '/runtime/charge-status';
    }

    public static function detail(string $code): string
    {
        return match ($code) {
            self::SUCCESS => 'Request was successfully processed',
            self::DUPLICATE => 'Duplicate request',
            self::PERMANENT => 'Permanent charging error',
            self::INSUFFICIENT => 'Insufficient balance',
            default => throw new \InvalidArgumentException('Unknown charge status.'),
        };
    }
}
