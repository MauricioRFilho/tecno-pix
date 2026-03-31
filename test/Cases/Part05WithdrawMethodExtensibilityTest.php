<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Account;
use App\Service\Exception\UnsupportedWithdrawMethodException;
use App\Service\WithdrawMethodFactory;
use App\Support\Uuid;
use Hyperf\Testing\TestCase;

use function Hyperf\Support\make;

class Part05WithdrawMethodExtensibilityTest extends TestCase
{
    public function testPart05FactoryResolvesPixMethod(): void
    {
        $factory = make(WithdrawMethodFactory::class);
        $method = $factory->make('pix');

        self::assertSame('pix', $method->name());
    }

    public function testPart05FactoryThrowsForUnsupportedMethod(): void
    {
        $factory = make(WithdrawMethodFactory::class);

        $this->expectException(UnsupportedWithdrawMethodException::class);
        $factory->make('bank_transfer');
    }

    public function testPart05RejectsInvalidPixEmailKey(): void
    {
        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta teste Parte 5',
            'balance' => '1000.00',
        ]);

        $response = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 100,
                'pix' => [
                    'type' => 'email',
                    'key' => 'invalid-email-key',
                ],
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid PIX key for type email.');

        Account::query()->where('id', $account->id)->delete();
    }
}
