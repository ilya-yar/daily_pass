<?php

declare(strict_types=1);

namespace Mock\Infrastructure;

/**
 * Append-only MT log for the demo. A repeat of the same text is another row.
 * Credentials never enter this file.
 */
final class MtLog
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @param list<string> $destinationAddresses
     */
    public function append(
        string $message,
        array $destinationAddresses,
        string $sourceAddress,
        string $encoding,
        string $version,
    ): void {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('MT log directory could not be created.');
        }

        $line = json_encode([
            'message' => $message,
            'destinationAddresses' => $destinationAddresses,
            'sourceAddress' => $sourceAddress,
            'encoding' => $encoding,
            'version' => $version,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $written = file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('MT log could not be written.');
        }
    }

    /**
     * @return list<array{message: string, destinationAddresses: list<string>, sourceAddress: string, encoding: string, version: string}>
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

        $messages = [];
        foreach (explode("\n", $raw) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('MT log is not JSON.');
            }
            $messages[] = $this->record($decoded);
        }

        return $messages;
    }

    /**
     * @param array<mixed> $decoded
     * @return array{message: string, destinationAddresses: list<string>, sourceAddress: string, encoding: string, version: string}
     */
    private function record(array $decoded): array
    {
        $addresses = $decoded['destinationAddresses'] ?? null;
        if (
            !is_string($decoded['message'] ?? null)
            || !is_array($addresses)
            || !is_string($decoded['sourceAddress'] ?? null)
            || !is_string($decoded['encoding'] ?? null)
            || !is_string($decoded['version'] ?? null)
        ) {
            throw new \RuntimeException('MT log row is incomplete.');
        }

        $destinationAddresses = [];
        foreach ($addresses as $address) {
            if (!is_string($address)) {
                throw new \RuntimeException('MT log row has a non-string destination.');
            }
            $destinationAddresses[] = $address;
        }

        return [
            'message' => $decoded['message'],
            'destinationAddresses' => $destinationAddresses,
            'sourceAddress' => $decoded['sourceAddress'],
            'encoding' => $decoded['encoding'],
            'version' => $decoded['version'],
        ];
    }
}
