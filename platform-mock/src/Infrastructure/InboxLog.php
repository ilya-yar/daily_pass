<?php

declare(strict_types=1);

namespace Mock\Infrastructure;

/**
 * Test and demo sink for pushed §B bodies. Credentials are not stored.
 */
final class InboxLog
{
    public function __construct(private readonly string $path)
    {
    }

    public function append(string $body): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Inbox directory could not be created.');
        }
        $written = file_put_contents($this->path, $body . "\n", FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('Inbox could not be written.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $raw = file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $events = [];
        foreach (explode("\n", $raw) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Inbox row is not JSON.');
            }
            $events[] = $decoded;
        }

        return $events;
    }
}
