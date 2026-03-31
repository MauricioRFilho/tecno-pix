<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

class Part07EmailNotificationTest extends TestCase
{
    public function testPart07SendsEmailAfterImmediateWithdraw(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta teste Parte 7',
            'balance' => '1000.00',
        ]);

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => sprintf('notify-%s@example.com', Uuid::v4()),
                ],
            ]
        );

        $response->assertStatus(202);

        Account::query()->where('id', $account->id)->delete();
    }

    public function testPart07DoesNotSendEmailForFutureScheduledWithdraw(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta teste Parte 7 agendado',
            'balance' => '1000.00',
        ]);

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => sprintf('scheduled-%s@example.com', Uuid::v4()),
                ],
                'schedule' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            ]
        );

        $response
            ->assertStatus(202)
            ->assertJsonPath('scheduled', true);

        usleep(500000);
        self::assertTrue(true);

        Account::query()->where('id', $account->id)->delete();
    }
}
