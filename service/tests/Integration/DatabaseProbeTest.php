<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Health\DatabaseProbe;
use yii\db\Connection;
use yii\web\Application;

final class DatabaseProbeTest extends \Codeception\Test\Unit
{
    public function testReachesTheDatabaseFromTheContainerEnvironment(): void
    {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'test');
        require_once dirname(__DIR__, 2) . '/vendor/yiisoft/yii2/Yii.php';

        $config = require dirname(__DIR__, 2) . '/config/web.php';
        $application = new Application($config);
        $connection = $application->get('db');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertTrue((new DatabaseProbe($connection))->isReachable());
    }
}
