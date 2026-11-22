<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use yii\db\Connection;

final class DatabaseProbe
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isReachable(): bool
    {
        $value = $this->connection->createCommand('SELECT 1')->queryScalar();

        return (int) $value === 1;
    }
}
