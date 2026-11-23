<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use yii\base\Model;

final class OptInForm extends Model
{
    public mixed $msisdn = null;

    public function rules(): array
    {
        return [
            ['msisdn', 'required'],
            ['msisdn', 'string'],
            ['msisdn', 'match', 'pattern' => '/^tel:[0-9]+$/'],
        ];
    }

    public function subscriberId(): string
    {
        if (!is_string($this->msisdn)) {
            throw new \RuntimeException('Validated msisdn must be a string.');
        }

        return $this->msisdn;
    }
}
