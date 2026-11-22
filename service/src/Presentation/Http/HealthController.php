<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Infrastructure\Health\DatabaseProbe;
use yii\db\Exception as DbException;
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
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;

        try {
            $reachable = (new DatabaseProbe(\Yii::$app->db))->isReachable();
        } catch (DbException) {
            \Yii::error('Database health check failed.', __METHOD__);
            $reachable = false;
        }

        if (!$reachable) {
            $response->statusCode = 503;

            return $this->asJson(['status' => 'error']);
        }

        $response->statusCode = 200;

        return $this->asJson(['status' => 'ok']);
    }
}
