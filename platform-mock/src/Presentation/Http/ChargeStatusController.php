<?php

declare(strict_types=1);

namespace Mock\Presentation\Http;

use Mock\Infrastructure\ChargeStatus;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Demo and test switch for MOCK_CHARGE_STATUS in this process.
 * Restart of the container restores the compose env.
 */
final class ChargeStatusController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'update' => ['POST'],
                ],
            ],
        ];
    }

    public function actionUpdate(): Response
    {
        try {
            $decoded = json_decode($this->request->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->reject('Invalid JSON');
        }
        if (!is_array($decoded)) {
            return $this->reject('Invalid JSON');
        }
        $code = $decoded['statusCode'] ?? null;
        if (!is_string($code) || !ChargeStatus::isAllowed($code)) {
            return $this->reject('Charge status must be S1000, E1337, E1308, or E1326.');
        }

        ChargeStatus::set($code);
        $this->response->statusCode = 200;

        return $this->asJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
            'chargeStatus' => $code,
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
