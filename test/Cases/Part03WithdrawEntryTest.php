<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

class Part03WithdrawEntryTest extends TestCase
{
    public function testPart03AcceptsValidWithdrawPayload(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta teste Parte 3',
            'balance' => '500.00',
        ]);
        $accountId = $account->id;

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $accountId),
            [
                'method' => 'pix',
                'amount' => 150.75,
                'pix' => [
                    'type' => 'email',
                    'key' => 'cliente@example.com',
                ],
            ]
        );

        $response
            ->assertStatus(202)
            ->assertJsonFragment([
                'message' => 'Withdraw request accepted.',
                'account_id' => $accountId,
                'method' => 'pix',
                'amount' => '150.75',
                'scheduled' => false,
            ]);

        Account::query()->where('id', $accountId)->delete();
    }

    public function testPart03Returns422WhenPayloadIsInvalid(): void
    {
        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', Uuid::v4()),
            [
                'method' => 'ted',
                'amount' => 0,
                'pix' => [
                    'type' => 'document',
                    'key' => '',
                ],
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure([
                'errors' => [
                    'method',
                    'amount',
                    'pix.type',
                    'pix.key',
                ],
            ]);
    }

    public function testPart03Returns422WhenAccountIdIsNotUuid(): void
    {
        $response = $this->post(
            '/account/invalid-account-id/balance/withdraw',
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'cliente@example.com',
                ],
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'accountId',
                ],
            ]);
    }

    public function testPart03ValidatesScheduleFormat(): void
    {
        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', Uuid::v4()),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'cliente@example.com',
                ],
                'schedule' => 'not-a-date',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'schedule',
                ],
            ]);
    }
}
