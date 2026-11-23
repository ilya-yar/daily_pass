<?php

declare(strict_types=1);

namespace App\Application\OptIn;

use App\Application\Platform\PlatformClient;
use App\Application\Platform\PlatformRequestException;
use App\Domain\Clock;
use App\Domain\MtMessage;
use App\Domain\SubscriberId;
use App\Domain\SubscriberRepository;
use App\Domain\SubscriberStatus;

/**
 * Q9: an ACTIVE subscriber is not sent to Platform and does not get a second welcome.
 * Platform is called before any local write. A timeout leaves the subscriber unchanged.
 */
final class OptInSubscriber
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

    public function execute(SubscriberId $subscriberId): OptInResult
    {
        $existing = $this->subscribers->find($subscriberId);
        if ($existing !== null && $existing->isActive()) {
            return new OptInResult($subscriberId->value(), SubscriberStatus::Active, true);
        }

        $this->registerOnPlatform($subscriberId);
        $this->subscribers->activate($subscriberId, $this->clock->now());
        $this->sendWelcome($subscriberId);

        return new OptInResult($subscriberId->value(), SubscriberStatus::Active, false);
    }

    private function registerOnPlatform(SubscriberId $subscriberId): void
    {
        try {
            $result = $this->platform->optIn($subscriberId->value());
        } catch (PlatformRequestException $exception) {
            throw new OptInFailedException('Platform did not confirm opt-in.', previous: $exception);
        }
        if ($result->statusCode !== 'S1000') {
            throw new OptInFailedException('Platform did not confirm opt-in.');
        }
    }

    private function sendWelcome(SubscriberId $subscriberId): void
    {
        try {
            $result = $this->platform->sendSms(
                MtMessage::WELCOME,
                $subscriberId->value(),
                $this->smsSourceAddress,
            );
        } catch (PlatformRequestException $exception) {
            throw new OptInFailedException('Welcome SMS was not sent.', previous: $exception);
        }
        if ($result->statusCode !== 'S1000') {
            throw new OptInFailedException('Welcome SMS was not sent.');
        }
    }
}
