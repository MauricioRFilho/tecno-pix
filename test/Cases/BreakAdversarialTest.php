<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

class BreakAdversarialTest extends TestCase
{
    public function testBreakNumericOverflowAmountIsRejectedWithout500(): void
    {
        $account = $this->createAccount('1000.00', 'Conta ataque overflow');

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => '1e309',
                'pix' => [
                    'type' => 'email',
                    'key' => 'overflow@example.com',
                ],
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.amount.0', 'The amount format is invalid.');

        Account::query()->where('id', $account->id)->delete();
    }

    public function testBreakFuzzedInvalidPayloadDoesNotCrashServer(): void
    {
        $response = $this->post(
            '/account/not-a-uuid/balance/withdraw',
            [
                'method' => ['pix'],
                'amount' => ['999999999999999999999'],
                'pix' => 'DROP TABLE account_withdraw',
                'schedule' => 'not-a-date',
            ]
        );

        self::assertContains($response->getStatusCode(), [422]);
    }

    public function testBreakBurstRequestsNoInternalErrorUnderLoad(): void
    {
        putenv('WITHDRAW_RATE_LIMIT_IN_TEST=false');

        $account = $this->createAccount('500000.00', 'Conta ataque burst');
        $serverErrors = 0;

        for ($i = 0; $i < 40; ++$i) {
            $response = $this->post(
                sprintf('/account/%s/balance/withdraw', $account->id),
                [
                    'method' => 'pix',
                    'amount' => 1,
                    'pix' => [
                        'type' => 'email',
                        'key' => sprintf('burst-%d-%s@example.com', $i, Uuid::v4()),
                    ],
                ]
            );

            if ($response->getStatusCode() >= 500) {
                ++$serverErrors;
            }
        }

        self::assertSame(0, $serverErrors, 'Burst test encontrou erro 5xx inesperado.');

        Account::query()->where('id', $account->id)->delete();
    }

    private function createAccount(string $balance, string $name): Account
    {
        /** @var Account $account */
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => $name,
            'balance' => $balance,
        ]);

        return $account;
    }
}
