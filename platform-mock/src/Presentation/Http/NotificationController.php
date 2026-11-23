<?php

declare(strict_types=1);

namespace Mock\Presentation\Http;

use Mock\Infrastructure\InboxLog;
use Mock\Infrastructure\NotificationClient;
use Mock\Infrastructure\NotificationDeliveryException;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class NotificationController extends Controller
{
    public $enableCsrfValidation = false;

    private const STATUSES = ['REGISTERED', 'UNREGISTERED', 'PENDING', 'CHARGE'];

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'push' => ['POST'],
                    'capture' => ['POST'],
                    'index' => ['GET'],
                ],
            ],
        ];
    }

    public function actionPush(): Response
    {
        $payload = $this->payload();
        if ($payload === null) {
            return $this->reject('Invalid JSON');
        }

        $status = $this->field($payload, 'status');
        $subscriberId = $this->field($payload, 'subscriberId');
        $frequency = $this->field($payload, 'frequency');
        $callbackUrl = $this->field($payload, 'callbackUrl') ?? $this->defaultCallbackUrl();
        $timeStamp = $this->field($payload, 'timeStamp')
            ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.vP');
        if (
            $status === null
            || !in_array($status, self::STATUSES, true)
            || $subscriberId === null
            || $frequency === null
            || $callbackUrl === null
        ) {
            return $this->reject('Invalid request');
        }

        $body = json_encode([
            'applicationId' => $this->applicationId(),
            'subscriberId' => $subscriberId,
            'frequency' => $frequency,
            'status' => $status,
            'version' => '1.0',
            'timeStamp' => $timeStamp,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            (new NotificationClient())->post($callbackUrl, $body, 2, 2);
        } catch (NotificationDeliveryException $exception) {
            $this->response->statusCode = 502;

            return $this->asJson([
                'statusCode' => 'E1000',
                'statusDetail' => $exception->getMessage(),
            ]);
        }

        $this->response->statusCode = 200;

        return $this->asJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
        ]);
    }

    public function actionCapture(): Response
    {
        $raw = $this->request->getRawBody();
        if ($raw === '') {
            return $this->reject('Invalid JSON');
        }
        $this->inbox()->append($raw);
        $this->response->statusCode = 200;

        return $this->asJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'Success',
        ]);
    }

    public function actionIndex(): Response
    {
        $this->response->statusCode = 200;

        return $this->asJson(['events' => $this->inbox()->all()]);
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

    private function defaultCallbackUrl(): ?string
    {
        $value = getenv('SERVICE_WEBHOOK_URL');
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function applicationId(): string
    {
        $value = getenv('MOCK_APPLICATION_ID');
        if (!is_string($value) || $value === '') {
            return 'APP_000123';
        }

        return $value;
    }

    private function inbox(): InboxLog
    {
        return new InboxLog(dirname(__DIR__, 3) . '/runtime/notification-inbox.jsonl');
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
