<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

class Part02DataModelTest extends TestCase
{
    public function testPart02CanPersistWithdrawAndPixRelations(): void
    {
        $accountId = Uuid::v4();
        $withdrawId = Uuid::v4();

        Account::query()->create([
            'id' => $accountId,
            'name' => 'Conta Teste Parte 2',
            'balance' => '500.00',
        ]);

        AccountWithdraw::query()->create([
            'id' => $withdrawId,
            'account_id' => $accountId,
            'method' => 'pix',
            'amount' => '120.50',
            'scheduled' => false,
            'done' => false,
            'error' => false,
        ]);

        AccountWithdrawPix::query()->create([
            'account_withdraw_id' => $withdrawId,
            'type' => 'email',
            'key' => 'cliente@example.com',
        ]);

        $withdraw = AccountWithdraw::query()
            ->with(['account', 'pix'])
            ->findOrFail($withdrawId);

        self::assertSame($accountId, $withdraw->account?->id);
        self::assertSame('pix', $withdraw->method);
        self::assertSame('120.50', $withdraw->amount);
        self::assertSame('email', $withdraw->pix?->type);
        self::assertSame('cliente@example.com', $withdraw->pix?->key);

        Account::query()->where('id', $accountId)->delete();
    }
}
