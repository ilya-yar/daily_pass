<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\OptIn\OptInFailedException;
use App\Application\OptIn\OptInSubscriber;
use App\Domain\SubscriberId;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Demo HTTP has no user auth (A-NOAUTH). CSRF is off because the contract is JSON, not a browser form.
 */
final class SubscribersController extends Controller
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
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;

        $form = new OptInForm();
        $params = $this->request->getBodyParams();
        if (!is_array($params) || !$form->load($params, '') || !$form->validate()) {
            $response->statusCode = 400;

            return $this->asJson(['error' => 'Invalid subscriber id']);
        }

        try {
            $result = $this->optIn()->execute(SubscriberId::fromString($form->subscriberId()));
        } catch (OptInFailedException $exception) {
            \Yii::error($exception->getMessage(), __METHOD__);
            $response->statusCode = 502;

            return $this->asJson(['error' => 'Opt-in could not be completed']);
        }

        $response->statusCode = 200;

        return $this->asJson([
            'subscriberId' => $result->subscriberId,
            'status' => $result->status->value,
        ]);
    }

    private function optIn(): OptInSubscriber
    {
        return \Yii::$container->get(OptInSubscriber::class);
    }
}
