<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Charging\RunDailyCharge;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Demo HTTP has no user auth (A-NOAUTH). CSRF is off because the contract is JSON, not a browser form.
 */
final class JobsController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'run-daily-charge' => ['POST'],
                ],
            ],
        ];
    }

    public function actionRunDailyCharge(): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_JSON;
        $result = $this->job()->execute();
        $response->statusCode = 200;

        return $this->asJson([
            'charged' => $result->charged,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
        ]);
    }

    private function job(): RunDailyCharge
    {
        return \Yii::$container->get(RunDailyCharge::class);
    }
}
