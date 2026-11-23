<?php

declare(strict_types=1);

use App\Application\OptIn\OptInSubscriber;
use App\Application\Platform\PlatformClient;
use App\Domain\Clock;
use App\Domain\SubscriberRepository;
use App\Infrastructure\Logging\YiiPsrLogger;
use App\Infrastructure\Persistence\PgsqlSubscriberRepository;
use App\Infrastructure\Platform\CurlHttpTransport;
use App\Infrastructure\Platform\HttpPlatformClient;
use App\Infrastructure\Platform\HttpTransport;
use App\Infrastructure\Time\SystemClock;
use Psr\Log\LoggerInterface;
use yii\db\Connection;
use yii\di\Container;
use yii\web\Application as WebApplication;
use yii\web\JsonParser;

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
    'container' => [
        'singletons' => [
            Clock::class => SystemClock::class,
            HttpTransport::class => CurlHttpTransport::class,
            LoggerInterface::class => YiiPsrLogger::class,
            SubscriberRepository::class => static function (): SubscriberRepository {
                $application = Yii::$app;
                if (!$application instanceof WebApplication && !$application instanceof yii\console\Application) {
                    throw new RuntimeException('Application is not bootstrapped.');
                }
                $connection = $application->get('db');
                if (!$connection instanceof Connection) {
                    throw new RuntimeException('Database component is not configured.');
                }

                return new PgsqlSubscriberRepository($connection);
            },
            PlatformClient::class => static function (Container $container): PlatformClient {
                return HttpPlatformClient::fromEnvironment(
                    $container->get(HttpTransport::class),
                    $container->get(LoggerInterface::class),
                );
            },
            OptInSubscriber::class => static function (Container $container): OptInSubscriber {
                $sourceAddress = getenv('SMS_SOURCE_ADDRESS');
                if (!is_string($sourceAddress) || $sourceAddress === '') {
                    throw new RuntimeException('Environment variable SMS_SOURCE_ADDRESS is required.');
                }

                return new OptInSubscriber(
                    $container->get(SubscriberRepository::class),
                    $container->get(PlatformClient::class),
                    $container->get(Clock::class),
                    $sourceAddress,
                );
            },
        ],
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $required('COOKIE_VALIDATION_KEY'),
            'enableCsrfValidation' => true,
            'parsers' => [
                'application/json' => JsonParser::class,
            ],
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET health' => 'health/index',
                'POST subscribers' => 'subscribers/create',
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
