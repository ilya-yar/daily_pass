<?php

declare(strict_types=1);

namespace Mock\Presentation\Http;

use Mock\Infrastructure\MtLog;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class SmsController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'send' => ['POST'],
                    'index' => ['GET'],
                ],
            ],
        ];
    }

    public function actionSend(): Response
    {
        $payload = $this->payload();
        if ($payload === null) {
            return $this->reject('Invalid JSON');
        }

        $message = $this->field($payload, 'message');
        $sourceAddress = $this->field($payload, 'sourceAddress');
        $applicationId = $this->field($payload, 'applicationId');
        $password = $this->field($payload, 'password');
        $version = $this->field($payload, 'version');
        $encoding = $this->field($payload, 'encoding');
        $destinations = $this->destinations($payload);
        if (
            $message === null
            || $sourceAddress === null
            || $applicationId === null
            || $password === null
            || $version !== '1.0'
            || $encoding !== '0'
            || $destinations === null
        ) {
            return $this->reject('Invalid request');
        }

        $this->log()->append($message, $destinations, $sourceAddress, $encoding, $version);
        $this->response->statusCode = 200;

        return $this->asJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
        ]);
    }

    public function actionIndex(): Response
    {
        $this->response->statusCode = 200;

        return $this->asJson(['messages' => $this->log()->all()]);
    }

    /**
     * @return array<mixed>|null
     */
    private function payload(): ?array
    {
        try {
            $decoded = json_decode($this->request->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $payload
     */
    private function field(array $payload, string $name): ?string
    {
        $value = $payload[$name] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     * @return list<string>|null
     */
    private function destinations(array $payload): ?array
    {
        $raw = $payload['destinationAddresses'] ?? null;
        if (!is_array($raw) || $raw === []) {
            return null;
        }

        $addresses = [];
        foreach ($raw as $address) {
            if (!is_string($address) || $address === '') {
                return null;
            }
            $addresses[] = $address;
        }

        return $addresses;
    }

    private function log(): MtLog
    {
        $path = \Yii::getAlias('@runtime/mt-log.jsonl');
        if (!is_string($path) || $path === '') {
            throw new \RuntimeException('MT log path is not configured.');
        }

        return new MtLog($path);
    }

    private function reject(string $detail): Response
    {
        $this->response->statusCode = 400;

        return $this->asJson([
            'statusCode' => 'E1000',
            'statusDetail' => $detail,
        ]);
    }
}
