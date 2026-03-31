<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Job\ProcessWithdrawJob;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

use function Hyperf\Support\make;

class Part06AsyncWithdrawProcessingTest extends TestCase
{
    public function testPart06ImmediateWithdrawIsProcessedAndDebitsBalance(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta teste Parte 6',
            'balance' => '1000.00',
        ]);

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

        $response->assertStatus(202);

        $withdraw = AccountWithdraw::query()->where('account_id', $account->id)->first();
        self::assertNotNull($withdraw);
        self::assertTrue((bool) $withdraw->done);
        self::assertFalse((bool) $withdraw->error);
        self::assertNotNull($withdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('900.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart06ScheduledWithdrawIsNotProcessedImmediately(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta agendada Parte 6',
            'balance' => '1000.00',
        ]);

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'cliente@example.com',
                ],
                'schedule' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]
        );

        $response
            ->assertStatus(202)
            ->assertJsonPath('scheduled', true);

        $withdraw = AccountWithdraw::query()->where('account_id', $account->id)->first();
        self::assertNotNull($withdraw);
        self::assertFalse((bool) $withdraw->done);
        self::assertFalse((bool) $withdraw->error);
        self::assertNull($withdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('1000.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart06ProcessingWithInsufficientBalanceMarksError(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta erro processamento Parte 6',
            'balance' => '50.00',
        ]);

        $withdraw = AccountWithdraw::query()->create([
            'id' => Uuid::v4(),
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '100.00',
            'scheduled' => false,
            'done' => false,
            'error' => false,
        ]);

        $job = make(ProcessWithdrawJob::class);
        $job->handle($withdraw->id);

        $freshWithdraw = AccountWithdraw::query()->findOrFail($withdraw->id);
        self::assertFalse((bool) $freshWithdraw->done);
        self::assertTrue((bool) $freshWithdraw->error);
        self::assertSame('Insufficient balance at processing.', $freshWithdraw->error_reason);
        self::assertNotNull($freshWithdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('50.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart06JobIsIdempotentOnRepeatedExecution(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta idempotente Parte 6',
            'balance' => '1000.00',
        ]);

        $withdraw = AccountWithdraw::query()->create([
            'id' => Uuid::v4(),
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '100.00',
            'scheduled' => false,
            'done' => false,
            'error' => false,
        ]);

        $job = make(ProcessWithdrawJob::class);
        $job->handle($withdraw->id);
        $job->handle($withdraw->id);

        $freshWithdraw = AccountWithdraw::query()->findOrFail($withdraw->id);
        self::assertTrue((bool) $freshWithdraw->done);
        self::assertFalse((bool) $freshWithdraw->error);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('900.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }
}
