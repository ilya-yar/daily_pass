<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\ApiTester;

final class SecretsHygieneCest
{
    public function optInDoesNotLeakPlatformPasswordInLogsOrSms(ApiTester $tester): void
    {
        $password = getenv('PLATFORM_PASSWORD');
        $tester->assertIsString($password);
        $tester->assertNotSame('', $password);

        $msisdn = 'tel:3602' . random_int(100000, 999999);
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/subscribers', ['msisdn' => $msisdn]);
        $tester->seeResponseCodeIs(200);
        $tester->dontSeeResponseContains($password);

        $sms = $this->mockGet($tester, '/sms');
        $tester->assertStringNotContainsString($password, $sms);
        $tester->assertStringNotContainsString('"password"', $sms);

        $this->assertRuntimeLogsOmitSecret($tester, $password);
    }

    private function assertRuntimeLogsOmitSecret(ApiTester $tester, string $password): void
    {
        $logDir = dirname(__DIR__, 2) . '/runtime/logs';
        if (!is_dir($logDir)) {
            return;
        }
        $files = glob($logDir . '/*');
        if ($files === false) {
            $tester->fail('Could not read runtime logs.');
        }
        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            $tester->assertIsString($contents);
            $tester->assertStringNotContainsString($password, $contents);
        }
    }

    private function mockGet(ApiTester $tester, string $path): string
    {
        $handle = curl_init('http://platform-mock:8080' . $path);
        if ($handle === false) {
            $tester->fail('Could not reach the platform mock.');
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($handle);
        curl_close($handle);
        if (!is_string($raw)) {
            $tester->fail('Mock GET did not return a body.');
        }

        return $raw;
    }
}
