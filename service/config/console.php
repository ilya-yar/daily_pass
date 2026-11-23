<?php

declare(strict_types=1);

return [
    'id' => 'daily-pass-service-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'App\\Presentation\\Console',
    'components' => [
        'db' => require __DIR__ . '/db.php',
        'log' => [
            'traceLevel' => 0,
            'targets' => [
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/console.log',
                    'logVars' => [],
                ],
            ],
        ],
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => yii\console\controllers\MigrateController::class,
            'migrationPath' => '@app/migrations',
            'interactive' => false,
        ],
    ],
];
