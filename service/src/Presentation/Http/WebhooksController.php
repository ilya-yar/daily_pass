<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\OptOut\OptOutFailedException;
use App\Application\OptOut\OptOutSubscriber;
use App\Application\Webhook\HandleSubscriptionNotification;
use App\Domain\SubscriberId;
use App\Domain\SubscriptionEventKey;
use App\Domain\SubscriptionNotificationStatus;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Demo HTTP has no user auth (A-NOAUTH). CSRF is off because Platform posts JSON.
 */
final class WebhooksController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'subscription' => ['POST'],
                    'mo' => ['POST'],
                ],
            ],
        ];
    }

    public function actionSubscription(): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;

        $form = new SubscriptionWebhookForm();
        $params = $this->request->getBodyParams();
        if (!is_array($params) || !$form->load($params, '') || !$form->validate()) {
            $response->statusCode = 400;

            return $this->asJson(['error' => 'Invalid webhook']);
        }

        $this->subscriptionHandler()->execute(new SubscriptionEventKey(
            SubscriberId::fromString($this->stringField($form->subscriberId)),
            SubscriptionNotificationStatus::from($this->stringField($form->status)),
            $this->stringField($form->timeStamp),
            $this->stringField($form->frequency),
        ));

        $response->statusCode = 200;

        return $this->asJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'Success',
        ]);
    }

    private function stringField(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \RuntimeException('Validated webhook field must be a string.');
        }

        return $value;
    }

    public function actionMo(): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;

        $form = new MoWebhookForm();
        $params = $this->request->getBodyParams();
        if (!is_array($params) || !$form->load($params, '') || !$form->validate()) {
            $response->statusCode = 400;

            return $this->asJson(['error' => 'Invalid MO']);
        }

        $this->handleStop($this->stringField($form->sourceAddress), $this->stringField($form->message));

        $response->statusCode = 200;

        return $this->asJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'Success',
        ]);
    }

    private function handleStop(string $sourceAddress, string $message): void
    {
        if ($message !== 'STOP') {
            return;
        }
        try {
            $subscriberId = SubscriberId::fromString($sourceAddress);
        } catch (\InvalidArgumentException) {
            return;
        }
        try {
            $this->optOut()->execute($subscriberId);
        } catch (OptOutFailedException $exception) {
            \Yii::error($exception->getMessage(), __METHOD__);
        }
    }

    private function subscriptionHandler(): HandleSubscriptionNotification
    {
        return \Yii::$container->get(HandleSubscriptionNotification::class);
    }

    private function optOut(): OptOutSubscriber
    {
        return \Yii::$container->get(OptOutSubscriber::class);
    }
}
