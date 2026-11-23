<?php

declare(strict_types=1);

namespace Tests\Mock;

use Tests\MockTester;

final class SmsSendCest
{
    public function storesTheMessageAndHidesThePassword(MockTester $tester): void
    {
        $marker = 'welcome-' . bin2hex(random_bytes(8));
        $destination = 'tel:1000000001';
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/sms/send', $this->body($marker, $destination, 'local-mock-password'));

        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson([
            'statusCode' => 'S1000',
            'statusDetail' => 'SUCCESS',
        ]);

        $tester->sendGet('/sms');
        $tester->seeResponseCodeIs(200);
        $rows = $this->messages($tester);
        $tester->assertContains($this->stored($marker, $destination), $rows);
        $tester->dontSeeResponseContains('local-mock-password');
        $tester->dontSeeResponseContains('APP_000123');
    }

    public function keepsBothSendsWhenTheTextRepeats(MockTester $tester): void
    {
        $marker = 'repeat-' . bin2hex(random_bytes(8));
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/sms/send', $this->body($marker, 'tel:1000000001', 'local-mock-password'));
        $tester->sendPost('/sms/send', $this->body($marker, 'tel:1000000001', 'local-mock-password'));

        $tester->sendGet('/sms');
        $count = 0;
        foreach ($this->messages($tester) as $row) {
            if (($row['message'] ?? null) === $marker) {
                $count++;
            }
        }
        $tester->assertSame(2, $count);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $message, string $destination, string $password): array
    {
        return [
            'applicationId' => 'APP_000123',
            'password' => $password,
            'message' => $message,
            'destinationAddresses' => [$destination],
            'version' => '1.0',
            'sourceAddress' => '1234',
            'encoding' => '0',
        ];
    }

    /**
     * @return array{message: string, destinationAddresses: list<string>, sourceAddress: string, encoding: string, version: string}
     */
    private function stored(string $message, string $destination): array
    {
        return [
            'message' => $message,
            'destinationAddresses' => [$destination],
            'sourceAddress' => '1234',
            'encoding' => '0',
            'version' => '1.0',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(MockTester $tester): array
    {
        $decoded = json_decode($tester->grabResponse(), true);
        $tester->assertIsArray($decoded);
        $tester->assertArrayHasKey('messages', $decoded);
        $tester->assertIsArray($decoded['messages']);

        $rows = [];
        foreach ($decoded['messages'] as $row) {
            $tester->assertIsArray($row);
            $rows[] = $row;
        }

        return $rows;
    }
}
