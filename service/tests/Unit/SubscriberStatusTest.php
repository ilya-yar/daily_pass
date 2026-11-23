<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\SubscriberStatus;

final class SubscriberStatusTest extends \Codeception\Test\Unit
{
    public function testKeepsTheThreeStatusesUsedByTheSchemaCheck(): void
    {
        $values = array_map(
            static fn (SubscriberStatus $status): string => $status->value,
            SubscriberStatus::cases(),
        );

        $this->assertSame(['ACTIVE', 'SUSPENDED', 'INACTIVE'], $values);
    }
}
