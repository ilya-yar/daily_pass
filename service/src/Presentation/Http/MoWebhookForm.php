<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use yii\base\Model;

final class MoWebhookForm extends Model
{
    public mixed $applicationId = null;

    public mixed $sourceAddress = null;

    public mixed $message = null;

    public mixed $encoding = null;

    public mixed $version = null;

    public function rules(): array
    {
        return [
            [['applicationId', 'sourceAddress', 'message', 'encoding', 'version'], 'required'],
            [['applicationId', 'sourceAddress', 'message', 'encoding', 'version'], 'string'],
        ];
    }
}
