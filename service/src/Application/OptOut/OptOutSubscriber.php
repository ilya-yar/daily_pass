<?php

declare(strict_types=1);

namespace App\Application\OptOut;

use App\Application\Platform\PlatformClient;
use App\Application\Platform\PlatformRequestException;
use App\Domain\Clock;
use App\Domain\MtMessage;
use App\Domain\SubscriberId;
use App\Domain\SubscriberRepository;

/**
 * Q12: unknown and already INACTIVE skip Platform and goodbye.
 * Platform is called before the local INACTIVE write. A timeout leaves the subscriber chargeable.
 */
final class OptOutSubscriber
{
    public function __construct(
        private readonly SubscriberRepository $subscribers,
        private readonly PlatformClient $platform,
        private readonly Clock $clock,
        private readonly string $smsSourceAddress,
    ) {
        if ($this->smsSourceAddress === '') {
            throw new \InvalidArgumentException('SMS source address is required.');
        }
    }

    public function execute(SubscriberId $subscriberId): void
    {
        $existing = $this->subscribers->find($subscriberId);
        if ($existing === null || $existing->isInactive()) {
            return;
        }

        $this->unregisterOnPlatform($subscriberId);
        $this->subscribers->deactivate($subscriberId, $this->clock->now());
        $this->sendGoodbye($subscriberId);
    }

    private function unregisterOnPlatform(SubscriberId $subscriberId): void
    {
        try {
            $result = $this->platform->optOut($subscriberId->value());
        } catch (PlatformRequestException $exception) {
            throw new OptOutFailedException('Platform did not confirm opt-out.', previous: $exception);
        }
        if ($result->statusCode !== 'S1000') {
            throw new OptOutFailedException('Platform did not confirm opt-out.');
        }
    }

    private function sendGoodbye(SubscriberId $subscriberId): void
    {
        try {
            $result = $this->platform->sendSms(
                MtMessage::GOODBYE,
                $subscriberId->value(),
                $this->smsSourceAddress,
            );
        } catch (PlatformRequestException $exception) {
            throw new OptOutFailedException('Goodbye SMS was not sent.', previous: $exception);
        }
        if ($result->statusCode !== 'S1000') {
            throw new OptOutFailedException('Goodbye SMS was not sent.');
        }
    }
}
