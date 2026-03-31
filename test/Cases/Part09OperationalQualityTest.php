<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\Handler\AppExceptionHandler;
use App\Model\Account;
use App\Support\Uuid;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\Testing\TestCase;
use RuntimeException;

use function Hyperf\Support\make;

class Part09OperationalQualityTest extends TestCase
{
    public function testPart09RateLimitBlocksRepeatedWithdrawRequests(): void
    {
        putenv('WITHDRAW_RATE_LIMIT_IN_TEST=true');
        putenv('WITHDRAW_RATE_LIMIT_MAX=1');
        putenv('WITHDRAW_RATE_LIMIT_WINDOW_SECONDS=120');

        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => 'Conta rate limit Parte 9',
            'balance' => '1000.00',
        ]);

        $first = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 1,
                'pix' => [
                    'type' => 'email',
                    'key' => 'ratelimit1@example.com',
                ],
            ]
        );

        $second = $this->post(
            sprintf('/account/%s/balance/withdraw', $account->id),
            [
                'method' => 'pix',
                'amount' => 1,
                'pix' => [
                    'type' => 'email',
                    'key' => 'ratelimit2@example.com',
                ],
            ]
        );

        $first->assertStatus(202);
        $second
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limit_exceeded');

        Account::query()->where('id', $account->id)->delete();

        putenv('WITHDRAW_RATE_LIMIT_IN_TEST=false');
        putenv('WITHDRAW_RATE_LIMIT_MAX=10');
        putenv('WITHDRAW_RATE_LIMIT_WINDOW_SECONDS=60');
    }

    public function testPart09AppExceptionHandlerReturnsJsonPayload(): void
    {
        $handler = make(AppExceptionHandler::class);
        $response = $handler->handle(new RuntimeException('unexpected'), new Response());

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Internal Server Error.', $payload['message']);
        self::assertSame('internal_error', $payload['code']);
    }
}
