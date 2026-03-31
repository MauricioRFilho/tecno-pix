<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Command\ProcessScheduledWithdrawsCommand;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

use function Hyperf\Support\make;

class Part08ScheduledWithdrawCronTest extends TestCase
{
    public function testPart08ProcessesReadyScheduledWithdraw(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta cron Parte 8',
            'balance' => '1000.00',
        ]);

        $withdraw = AccountWithdraw::query()->create([
            'id' => Uuid::v4(),
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '125.00',
            'scheduled' => true,
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'done' => false,
            'error' => false,
            'processed_at' => null,
        ]);

        make(ProcessScheduledWithdrawsCommand::class)->handle();

        $freshWithdraw = AccountWithdraw::query()->findOrFail($withdraw->id);
        self::assertTrue((bool) $freshWithdraw->done);
        self::assertFalse((bool) $freshWithdraw->error);
        self::assertNotNull($freshWithdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('875.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart08KeepsFutureScheduledWithdrawPending(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta cron futuro Parte 8',
            'balance' => '1000.00',
        ]);

        $withdraw = AccountWithdraw::query()->create([
            'id' => Uuid::v4(),
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '100.00',
            'scheduled' => true,
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'done' => false,
            'error' => false,
            'processed_at' => null,
        ]);

        make(ProcessScheduledWithdrawsCommand::class)->handle();

        $freshWithdraw = AccountWithdraw::query()->findOrFail($withdraw->id);
        self::assertFalse((bool) $freshWithdraw->done);
        self::assertFalse((bool) $freshWithdraw->error);
        self::assertNull($freshWithdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('1000.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart08MarksErrorForScheduledWithdrawWithInsufficientBalance(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta cron saldo insuficiente Parte 8',
            'balance' => '50.00',
        ]);

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

        $freshWithdraw = AccountWithdraw::query()->findOrFail($withdraw->id);
        self::assertFalse((bool) $freshWithdraw->done);
        self::assertTrue((bool) $freshWithdraw->error);
        self::assertSame('Insufficient balance at processing.', $freshWithdraw->error_reason);
        self::assertNotNull($freshWithdraw->processed_at);

        $freshAccount = Account::query()->findOrFail($account->id);
        self::assertSame('50.00', $freshAccount->balance);

        Account::query()->where('id', $account->id)->delete();
    }
}
