<?php

declare(strict_types=1);

namespace Mock\Presentation\Http;

use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class HealthController extends Controller
{
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
        $this->response->statusCode = 200;

        return $this->asJson(['status' => 'ok']);
    }
}
