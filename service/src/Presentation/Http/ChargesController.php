<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Audit\GetDailyCharge;
use App\Domain\SubscriberId;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Demo HTTP has no user auth (A-NOAUTH). CSRF is off because the contract is JSON, not a browser form.
 */
final class ChargesController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                ],
            ],
        ];
    }

    public function actionIndex(): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;

        $form = new ChargeQueryForm();
        if (!$form->load($this->request->get(), '') || !$form->validate()) {
            $response->statusCode = 400;

            return $this->asJson(['error' => 'Invalid charge query']);
        }

        $lookup = $this->query()->execute(
            SubscriberId::fromString($form->subscriberId()),
            $form->billingDay(),
        );

        $response->statusCode = 200;
        $body = [
            'msisdn' => $lookup->msisdn,
            'date' => $lookup->date,
            'charged' => $lookup->charged,
        ];
        if ($lookup->referenceId !== null) {
            $body['referenceId'] = $lookup->referenceId;
        }

        return $this->asJson($body);
    }

    private function query(): GetDailyCharge
    {
        return \Yii::$container->get(GetDailyCharge::class);
    }
}
