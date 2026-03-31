<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Command\ProcessScheduledWithdrawsCommand;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

use function Hyperf\Support\make;

class Part10FinalValidationTest extends TestCase
{
    public function testPart10ImmediateWithdrawEndToEndSuccess(): void
    {
        $account = $this->createAccount('1000.00', 'Conta Parte 10 imediato');
        $pixKey = sprintf('part10-immediate-%s@example.com', Uuid::v4());

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 150.00,
                'pix' => [
                    'type' => 'email',
                    'key' => $pixKey,
                ],
            ]
        );

        $response
            ->assertStatus(202)
            ->assertJsonPath('scheduled', false);

        $withdraw = AccountWithdraw::query()
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->first();

        self::assertNotNull($withdraw);
        self::assertTrue((bool) $withdraw->done);
        self::assertFalse((bool) $withdraw->error);
        self::assertNotNull($withdraw->processed_at);

        $pix = AccountWithdrawPix::query()->find((string) $withdraw->id);
        self::assertNotNull($pix);
        self::assertSame($pixKey, $pix->key);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('850.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart10ScheduledWithdrawSuccessAfterCron(): void
    {
        $account = $this->createAccount('1000.00', 'Conta Parte 10 agendado sucesso');

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 200.00,
                'pix' => [
                    'type' => 'email',
                    'key' => sprintf('part10-scheduled-%s@example.com', Uuid::v4()),
                ],
                'schedule' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]
        );

        $response
            ->assertStatus(202)
            ->assertJsonPath('scheduled', true);

        $withdraw = AccountWithdraw::query()
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->first();

        self::assertNotNull($withdraw);
        self::assertFalse((bool) $withdraw->done);
        self::assertFalse((bool) $withdraw->error);

        $withdraw->scheduled_for = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $withdraw->save();

        make(ProcessScheduledWithdrawsCommand::class)->handle();

        $freshWithdraw = AccountWithdraw::query()->findOrFail((string) $withdraw->id);
        self::assertTrue((bool) $freshWithdraw->done);
        self::assertFalse((bool) $freshWithdraw->error);
        self::assertNotNull($freshWithdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('800.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart10Returns404ForUnknownAccount(): void
    {
        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', Uuid::v4()),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'part10-not-found@example.com',
                ],
            ]
        );

        $response
            ->assertStatus(404)
            ->assertJsonPath('message', 'Account not found.');
    }

    public function testPart10Returns422ForInsufficientBalanceAtCreation(): void
    {
        $account = $this->createAccount('50.00', 'Conta Parte 10 sem saldo');

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'part10-insufficient-create@example.com',
                ],
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient balance.');

        self::assertSame(0, AccountWithdraw::query()->where('account_id', $account->id)->count());
        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart10MarksScheduledWithdrawErrorWhenInsufficientAtProcessing(): void
    {
        $account = $this->createAccount('10.00', 'Conta Parte 10 erro agendado');

        $withdraw = AccountWithdraw::query()->create([
            'id' => Uuid::v4(),
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '100.00',
            'scheduled' => true,
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'done' => false,
            'error' => false,
            'processed_at' => null,
        ]);

        make(ProcessScheduledWithdrawsCommand::class)->handle();

        $freshWithdraw = AccountWithdraw::query()->findOrFail((string) $withdraw->id);
        self::assertFalse((bool) $freshWithdraw->done);
        self::assertTrue((bool) $freshWithdraw->error);
        self::assertSame('Insufficient balance at processing.', $freshWithdraw->error_reason);
        self::assertNotNull($freshWithdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('10.00', $freshAccount->balance);

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
