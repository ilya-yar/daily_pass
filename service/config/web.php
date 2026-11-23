<?php

declare(strict_types=1);

$required = static function (string $name): string {
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException(sprintf('Environment variable %s is required.', $name));
    }

    return $value;
};

return [
    'id' => 'daily-pass-service',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'App\\Presentation\\Http',
    'components' => [
        'request' => [
            'cookieValidationKey' => $required('COOKIE_VALIDATION_KEY'),
            'enableCsrfValidation' => true,
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET health' => 'health/index',
            ],
        ],
        'log' => [
            'traceLevel' => 0,
            'targets' => [
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/app.log',
                    'logVars' => [],
                ],
            ],
        ],
    ],
];
