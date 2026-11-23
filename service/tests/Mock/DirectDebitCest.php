<?php

declare(strict_types=1);

namespace Tests\Mock;

use Tests\MockTester;

final class DirectDebitCest
{
    public function returnsEachConfiguredCodeOnTheFirstNewKey(MockTester $tester): void
    {
        foreach (['S1000', 'E1326', 'E1308', 'E1337'] as $code) {
            $this->setChargeStatus($tester, $code);
            $key = 'trx-' . $code . '-' . bin2hex(random_bytes(8));
            $tester->sendPost('/caas/direct/debit', $this->body($key));
            $tester->seeResponseCodeIs(200);
            $tester->seeResponseContainsJson(['statusCode' => $code]);
            $tester->dontSeeResponseContains('local-mock-password');
            if ($code === 'S1000') {
                $tester->seeResponseContainsJson(['externalTrxId' => $key]);
                $decoded = json_decode($tester->grabResponse(), true);
                $tester->assertIsArray($decoded);
                $tester->assertNotSame('', $decoded['internalTrxId'] ?? null);
                $tester->assertNotSame('', $decoded['referenceId'] ?? null);
                $tester->assertNotSame('', $decoded['timeStamp'] ?? null);
            }
        }
    }

    public function answersDuplicateEvenAfterTheConfigBecomesSuccess(MockTester $tester): void
    {
        $this->setChargeStatus($tester, 'E1326');
        $key = 'trx-dup-' . bin2hex(random_bytes(8));
        $tester->sendPost('/caas/direct/debit', $this->body($key));
        $tester->seeResponseContainsJson(['statusCode' => 'E1326']);

        $this->setChargeStatus($tester, 'S1000');
        $tester->sendPost('/caas/direct/debit', $this->body($key));
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['statusCode' => 'E1337']);
        $tester->dontSeeResponseContainsJson(['referenceId' => '']);
        $decoded = json_decode($tester->grabResponse(), true);
        $tester->assertIsArray($decoded);
        $tester->assertArrayNotHasKey('referenceId', $decoded);
    }

    public function treatsAFreshKeyAsANewCharge(MockTester $tester): void
    {
        $this->setChargeStatus($tester, 'S1000');
        $first = 'trx-a-' . bin2hex(random_bytes(8));
        $second = 'trx-b-' . bin2hex(random_bytes(8));
        $tester->sendPost('/caas/direct/debit', $this->body($first));
        $tester->seeResponseContainsJson(['statusCode' => 'S1000', 'externalTrxId' => $first]);
        $tester->sendPost('/caas/direct/debit', $this->body($second));
        $tester->seeResponseContainsJson(['statusCode' => 'S1000', 'externalTrxId' => $second]);
    }

    private function setChargeStatus(MockTester $tester, string $code): void
    {
        $tester->haveHttpHeader('Content-Type', 'application/json');
        $tester->sendPost('/mock/charge-status', ['statusCode' => $code]);
        $tester->seeResponseCodeIs(200);
        $tester->seeResponseContainsJson(['chargeStatus' => $code]);
    }

    /**
     * @return array<string, string>
     */
    private function body(string $externalTrxId): array
    {
        return [
            'applicationId' => 'APP_000123',
            'password' => 'local-mock-password',
            'externalTrxId' => $externalTrxId,
            'subscriberId' => 'tel:1000000001',
            'paymentInstrumentName' => 'Mobile Account',
            'amount' => '5.00',
            'currency' => 'XTS',
        ];
    }
}
