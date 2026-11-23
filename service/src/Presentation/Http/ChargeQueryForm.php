<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use yii\base\Model;

final class ChargeQueryForm extends Model
{
    public mixed $msisdn = null;

    public mixed $date = null;

    public function rules(): array
    {
        return [
            [['msisdn', 'date'], 'required'],
            [['msisdn', 'date'], 'string'],
            ['msisdn', 'match', 'pattern' => '/^tel:[0-9]+$/'],
            ['date', 'match', 'pattern' => '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/'],
            ['date', 'validateCalendarDate'],
        ];
    }

    public function validateCalendarDate(string $attribute): void
    {
        if (!is_string($this->date)) {
            return;
        }
        $parts = explode('-', $this->date);
        if (count($parts) !== 3) {
            $this->addError($attribute, 'Date must be a UTC calendar day.');

            return;
        }
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            $this->addError($attribute, 'Date must be a UTC calendar day.');
        }
    }

    public function subscriberId(): string
    {
        if (!is_string($this->msisdn)) {
            throw new \RuntimeException('Validated msisdn must be a string.');
        }

        return $this->msisdn;
    }

    public function billingDay(): string
    {
        if (!is_string($this->date)) {
            throw new \RuntimeException('Validated date must be a string.');
        }

        return $this->date;
    }
}
