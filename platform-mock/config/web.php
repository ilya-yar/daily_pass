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
    'id' => 'daily-pass-platform-mock',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'Mock\\Presentation\\Http',
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
                'POST subscription/send' => 'subscription/send',
                'POST sms/send' => 'sms/send',
                'GET sms' => 'sms/index',
                'POST caas/direct/debit' => 'debit/create',
                'POST mock/charge-status' => 'charge-status/update',
                'POST mock/notifications' => 'notification/push',
                'POST mock/inbox' => 'notification/capture',
                'GET mock/inbox' => 'notification/index',
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
