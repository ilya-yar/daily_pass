<?php

declare(strict_types=1);

namespace App\Application\Status;

use App\Domain\Subscriber;
use App\Domain\SubscriberId;
use App\Domain\SubscriberRepository;

final class GetSubscriberStatus
{
    public function __construct(private readonly SubscriberRepository $subscribers)
    {
    }

    public function execute(SubscriberId $subscriberId): Subscriber
    {
        $subscriber = $this->subscribers->find($subscriberId);
        if ($subscriber === null) {
            throw new SubscriberNotFoundException('Subscriber was not found.');
        }

        return $subscriber;
    }
}
