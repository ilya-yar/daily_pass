<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\OptOut\OptOutFailedException;
use App\Application\OptOut\OptOutSubscriber;
use App\Application\Platform\PlatformRequestException;
use App\Application\Platform\PlatformResult;
use App\Domain\MtMessage;
use App\Domain\Subscriber;
use App\Domain\SubscriberId;
use App\Domain\SubscriberStatus;
use DateTimeImmutable;

final class OptOutSubscriberTest extends \Codeception\Test\Unit
{
    public function testActiveSubscriberUnregistersThenPersistsThenSendsGoodbye(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, new DateTimeImmutable('2026-11-24 00:00:00 UTC'));
        $platform = new RecordingPlatformClient();

        $this->useCase($subscribers, $platform)->execute($id);

        $this->assertSame(['tel:1000000001'], $platform->optOuts);
        $this->assertSame(
            [[MtMessage::GOODBYE, 'tel:1000000001', '1234']],
            $platform->sms,
        );
        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isInactive());
        $this->assertSame(1, $subscribers->deactivateCalls);
    }

    public function testAlreadyInactiveDoesNotCallPlatformOrSendGoodbye(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->add(new Subscriber($id, SubscriberStatus::Inactive));
        $platform = new RecordingPlatformClient();

        $this->useCase($subscribers, $platform)->execute($id);

        $this->assertSame([], $platform->optOuts);
        $this->assertSame([], $platform->sms);
        $this->assertSame(0, $subscribers->deactivateCalls);
    }

    public function testUnknownSubscriberDoesNotCallPlatformOrSendGoodbye(): void
    {
        $id = SubscriberId::fromString('tel:1999999999');
        $subscribers = new InMemorySubscriberRepository();
        $platform = new RecordingPlatformClient();

        $this->useCase($subscribers, $platform)->execute($id);

        $this->assertSame([], $platform->optOuts);
        $this->assertSame([], $platform->sms);
        $this->assertNull($subscribers->find($id));
    }

    public function testPlatformTimeoutLeavesSubscriberActiveAndSendsNoGoodbye(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, new DateTimeImmutable('2026-11-24 00:00:00 UTC'));
        $platform = new RecordingPlatformClient();
        $platform->optOutError = new PlatformRequestException('Platform request timed out.');

        try {
            $this->useCase($subscribers, $platform)->execute($id);
            $this->fail('A platform timeout must fail the opt-out.');
        } catch (OptOutFailedException $exception) {
            $this->assertSame('Platform did not confirm opt-out.', $exception->getMessage());
            $this->assertInstanceOf(PlatformRequestException::class, $exception->getPrevious());
        }

        $stored = $subscribers->find($id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isActive());
        $this->assertSame([], $platform->sms);
        $this->assertSame(0, $subscribers->deactivateCalls);
    }

    public function testNonSuccessPlatformStatusLeavesSubscriberActive(): void
    {
        $id = SubscriberId::fromString('tel:1000000001');
        $subscribers = new InMemorySubscriberRepository();
        $subscribers->activate($id, new DateTimeImmutable('2026-11-24 00:00:00 UTC'));
        $platform = new RecordingPlatformClient();
        $platform->optOutResult = new PlatformResult('E1000', 'Invalid request', []);

        $this->expectException(OptOutFailedException::class);
        try {
            $this->useCase($subscribers, $platform)->execute($id);
        } finally {
            $stored = $subscribers->find($id);
            $this->assertNotNull($stored);
            $this->assertTrue($stored->isActive());
            $this->assertSame([], $platform->sms);
            $this->assertSame(0, $subscribers->deactivateCalls);
        }
    }

    private function useCase(InMemorySubscriberRepository $subscribers, RecordingPlatformClient $platform): OptOutSubscriber
    {
        return new OptOutSubscriber(
            $subscribers,
            $platform,
            new FixedClock(new DateTimeImmutable('2026-11-24 12:00:00 UTC')),
            '1234',
        );
    }
}
