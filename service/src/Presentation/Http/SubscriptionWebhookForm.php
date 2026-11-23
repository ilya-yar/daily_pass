<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use yii\base\Model;

final class SubscriptionWebhookForm extends Model
{
    public mixed $applicationId = null;

    public mixed $subscriberId = null;

    public mixed $frequency = null;

    public mixed $status = null;

    public mixed $version = null;

    public mixed $timeStamp = null;

    public function rules(): array
    {
        return [
            [['applicationId', 'subscriberId', 'frequency', 'status', 'version', 'timeStamp'], 'required'],
            [['applicationId', 'subscriberId', 'frequency', 'status', 'version', 'timeStamp'], 'string'],
            ['subscriberId', 'match', 'pattern' => '/^tel:[0-9]+$/'],
            ['status', 'in', 'range' => ['REGISTERED', 'UNREGISTERED', 'PENDING', 'CHARGE']],
        ];
    }
}
