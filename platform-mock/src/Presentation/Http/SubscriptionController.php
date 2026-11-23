<?php

declare(strict_types=1);

namespace Mock\Presentation\Http;

use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * E1000 is a mock rejection, not a §E charge code.
 * The success body follows the §A example.
 */
final class SubscriptionController extends Controller
{
    public $enableCsrfValidation = false;

    private const REQUEST_ID = '1374746416574';

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'send' => ['POST'],
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

        $action = $this->field($payload, 'action');
        $subscriberId = $this->field($payload, 'subscriberId');
        $applicationId = $this->field($payload, 'applicationId');
        $password = $this->field($payload, 'password');
        $version = $this->field($payload, 'version');
        if ($subscriberId === null || $applicationId === null || $password === null || $version !== '1.0') {
            return $this->reject('Invalid request');
        }

        $subscriptionStatus = match ($action) {
            '1' => 'REGISTERED',
            '0' => 'UNREGISTERED',
            default => null,
        };
        if ($subscriptionStatus === null) {
            return $this->reject('Unsupported action');
        }

        $this->response->statusCode = 200;

        return $this->asJson([
            'version' => '1.0',
            'requestId' => self::REQUEST_ID,
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
            'subscriptionStatus' => $subscriptionStatus,
        ]);
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

    private function reject(string $detail): Response
    {
        $this->response->statusCode = 400;

        return $this->asJson([
            'statusCode' => 'E1000',
            'statusDetail' => $detail,
        ]);
    }
}
