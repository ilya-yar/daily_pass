<?php

declare(strict_types=1);

namespace Mock\Presentation\Http;

use Mock\Infrastructure\ChargeStatus;
use Mock\Infrastructure\SeenExternalTrxStore;
use yii\db\Connection;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class DebitController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['POST'],
                ],
            ],
        ];
    }

    public function actionCreate(): Response
    {
        $payload = $this->payload();
        if ($payload === null) {
            return $this->reject('Invalid JSON');
        }

        $externalTrxId = $this->field($payload, 'externalTrxId');
        $subscriberId = $this->field($payload, 'subscriberId');
        $applicationId = $this->field($payload, 'applicationId');
        $password = $this->field($payload, 'password');
        $instrument = $this->field($payload, 'paymentInstrumentName');
        $amount = $this->field($payload, 'amount');
        $currency = $this->field($payload, 'currency');
        if (
            $externalTrxId === null
            || $subscriberId === null
            || $applicationId === null
            || $password === null
            || $instrument !== 'Mobile Account'
            || $amount === null
            || $currency === null
        ) {
            return $this->reject('Invalid request');
        }

        $isNew = $this->store()->recordIfNew($externalTrxId);
        if (!$isNew) {
            return $this->status(ChargeStatus::DUPLICATE);
        }

        $code = ChargeStatus::configured();
        if ($code !== ChargeStatus::SUCCESS) {
            return $this->status($code);
        }

        $digest = hash('sha256', $externalTrxId);
        $this->response->statusCode = 200;

        return $this->asJson([
            'statusCode' => ChargeStatus::SUCCESS,
            'statusDetail' => ChargeStatus::detail(ChargeStatus::SUCCESS),
            'externalTrxId' => $externalTrxId,
            'internalTrxId' => substr($digest, 0, 15),
            'referenceId' => substr($digest, 0, 8),
            'timeStamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.vP'),
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

    private function store(): SeenExternalTrxStore
    {
        $connection = $this->getDb();

        return new SeenExternalTrxStore($connection);
    }

    private function getDb(): Connection
    {
        $application = \Yii::$app;
        $connection = $application->get('db');
        if (!$connection instanceof Connection) {
            throw new \RuntimeException('Database component is not configured.');
        }

        return $connection;
    }

    private function status(string $code): Response
    {
        $this->response->statusCode = 200;

        return $this->asJson([
            'statusCode' => $code,
            'statusDetail' => ChargeStatus::detail($code),
        ]);
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
