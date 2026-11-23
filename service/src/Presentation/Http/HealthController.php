<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\HealthStatus;
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
            $application = \Yii::$app;
            if (!$application instanceof \yii\web\Application) {
                throw new \RuntimeException('HTTP application is not bootstrapped.');
            }
            $connection = $application->get('db');
            if (!$connection instanceof \yii\db\Connection) {
                throw new \RuntimeException('Database component is not configured.');
            }
            $reachable = (new DatabaseProbe($connection))->isReachable();
        } catch (DbException) {
            \Yii::error('Database health check failed.', __METHOD__);
            $reachable = false;
        }

        if (!$reachable) {
            $response->statusCode = 503;

            return $this->asJson(['status' => HealthStatus::ERROR]);
        }

        $response->statusCode = 200;

        return $this->asJson(['status' => HealthStatus::OK]);
    }
}
