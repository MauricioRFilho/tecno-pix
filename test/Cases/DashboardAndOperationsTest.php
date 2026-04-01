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

class DashboardAndOperationsTest extends TestCase
{
    public function testDashboardScreenIsAvailable(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Tecno Pix Console');
    }

    public function testCanCreateAccountFromVisualFlowEndpoint(): void
    {
        $response = $this->post('/accounts', [
            'name' => 'Conta criada via dashboard',
            'balance' => 321.45,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('name', 'Conta criada via dashboard')
            ->assertJsonPath('balance', '321.45');

        $accountId = (string) $response->json('id');
        self::assertNotSame('', $accountId);

        Account::query()->where('id', $accountId)->delete();
    }

    public function testCanListOperationsForDashboard(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta operacoes dashboard',
            'balance' => '1000.00',
        ]);

        $withdrawId = Uuid::v4();
        AccountWithdraw::query()->create([
            'id' => $withdrawId,
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '10.00',
            'scheduled' => false,
            'done' => true,
            'error' => false,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
        AccountWithdrawPix::query()->create([
            'account_withdraw_id' => $withdrawId,
            'type' => 'email',
            'key' => 'dashboard@example.com',
        ]);

        $response = $this->get('/operations?limit=10');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $withdrawId,
            'account_id' => $account->id,
            'method' => 'pix',
            'amount' => '10.00',
        ]);
        $response->assertJsonFragment([
            'type' => 'email',
            'key' => 'dashboard@example.com',
        ]);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testScheduledWithdrawCanFailWhenBalanceChangesBeforeProcessing(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta cenário saldo insuficiente',
            'balance' => '100.00',
        ]);

        $scheduledResponse = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 80,
                'pix' => [
                    'type' => 'email',
                    'key' => 'dashboard@example.com',
                ],
                'schedule' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]
        );

        $scheduledResponse
            ->assertStatus(202)
            ->assertJsonPath('scheduled', true);

        $scheduledId = (string) $scheduledResponse->json('id');
        self::assertNotSame('', $scheduledId);

        $immediateResponse = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 30,
                'pix' => [
                    'type' => 'email',
                    'key' => 'dashboard@example.com',
                ],
            ]
        );

        $immediateResponse->assertStatus(202);

        $scheduledWithdraw = AccountWithdraw::query()->find($scheduledId);
        self::assertInstanceOf(AccountWithdraw::class, $scheduledWithdraw);

        $scheduledWithdraw->scheduled_for = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $scheduledWithdraw->save();

        make(ProcessScheduledWithdrawsCommand::class)->handle();

        $freshScheduledWithdraw = AccountWithdraw::query()->find($scheduledId);
        self::assertInstanceOf(AccountWithdraw::class, $freshScheduledWithdraw);
        self::assertTrue((bool) $freshScheduledWithdraw->error);
        self::assertFalse((bool) $freshScheduledWithdraw->done);
        self::assertSame('Insufficient balance at processing.', $freshScheduledWithdraw->error_reason);

        Account::query()->where('id', $account->id)->delete();
    }
}
