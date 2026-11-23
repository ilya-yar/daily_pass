<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\OptIn\OptInFailedException;
use App\Application\OptIn\OptInSubscriber;
use App\Application\Status\GetSubscriberStatus;
use App\Application\Status\SubscriberNotFoundException;
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
                    'view' => ['GET'],
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

    public function actionView(string $subscriberId): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;

        try {
            $id = SubscriberId::fromString($subscriberId);
        } catch (\InvalidArgumentException) {
            $response->statusCode = 400;

            return $this->asJson(['error' => 'Invalid subscriber id']);
        }

        try {
            $subscriber = $this->status()->execute($id);
        } catch (SubscriberNotFoundException) {
            $response->statusCode = 404;

            return $this->asJson(['error' => 'Subscriber not found']);
        }

        $response->statusCode = 200;

        return $this->asJson([
            'subscriberId' => $subscriber->id->value(),
            'status' => $subscriber->status->value,
        ]);
    }

    private function optIn(): OptInSubscriber
    {
        return \Yii::$container->get(OptInSubscriber::class);
    }

    private function status(): GetSubscriberStatus
    {
        return \Yii::$container->get(GetSubscriberStatus::class);
    }
}
