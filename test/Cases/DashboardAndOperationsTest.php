<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

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
}
