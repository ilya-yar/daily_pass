<?php

declare(strict_types=1);

namespace App\Application\Platform;

final readonly class PlatformResult
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public string $statusCode,
        public string $statusDetail,
        public array $fields,
    ) {
    }

    public function field(string $name): ?string
    {
        return $this->fields[$name] ?? null;
    }
}
