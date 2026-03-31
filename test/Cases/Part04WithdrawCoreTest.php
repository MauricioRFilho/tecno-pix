<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

class Part04WithdrawCoreTest extends TestCase
{
    public function testPart04PersistsImmediateWithdrawAndPixData(): void
    {
        $account = $this->createAccountWithBalance('1000.00');
        $pixKey = sprintf('cliente+%s@example.com', Uuid::v4());

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 120.50,
                'pix' => [
                    'type' => 'email',
                    'key' => $pixKey,
                ],
            ]
        );

        $response
            ->assertStatus(202)
            ->assertJsonPath('message', 'Withdraw request accepted.')
            ->assertJsonPath('scheduled', false);

        $withdraw = AccountWithdraw::query()
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->first();

        self::assertNotNull($withdraw);
        self::assertSame('pix', $withdraw->method);
        self::assertSame('120.50', $withdraw->amount);
        self::assertFalse((bool) $withdraw->scheduled);
        self::assertNull($withdraw->scheduled_for);

        $pix = AccountWithdrawPix::query()->find($withdraw->id);
        self::assertNotNull($pix);
        self::assertSame('email', $pix->type);
        self::assertSame($pixKey, $pix->key);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart04Returns404WhenAccountDoesNotExist(): void
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
            ]
        );

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'Account not found.');
    }

    public function testPart04Returns422WhenBalanceIsInsufficient(): void
    {
        $account = $this->createAccountWithBalance('50.00');

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
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
            ->assertJsonPath('message', 'Insufficient balance.');

        self::assertSame(0, AccountWithdraw::query()->where('account_id', $account->id)->count());
        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart04Returns422WhenScheduleIsInThePast(): void
    {
        $account = $this->createAccountWithBalance('500.00');

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'cliente@example.com',
                ],
                'schedule' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Schedule must be in the future.');

        self::assertSame(0, AccountWithdraw::query()->where('account_id', $account->id)->count());
        Account::query()->where('id', $account->id)->delete();
    }

    private function createAccountWithBalance(string $balance): Account
    {
        /** @var Account $account */
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta de teste Parte 4',
            'balance' => $balance,
        ]);

        return $account;
    }
}
