<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\RedisFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

use function Hyperf\Support\env;

class WithdrawRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HttpResponse $response,
        private readonly RedisFactory $redisFactory,
        private readonly LoggerFactory $loggerFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->shouldApply($request)) {
            return $handler->handle($request);
        }

        $maxRequests = (int) env('WITHDRAW_RATE_LIMIT_MAX', 10);
        $windowSeconds = (int) env('WITHDRAW_RATE_LIMIT_WINDOW_SECONDS', 60);
        if ($maxRequests <= 0 || $windowSeconds <= 0) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        preg_match('#^/account/([^/]+)/balance/withdraw$#', $path, $matches);
        $accountId = $matches[1] ?? 'unknown';
        $ip = (string) ($request->getServerParams()['remote_addr'] ?? 'unknown');
        $key = sprintf('rate_limit:withdraw:%s:%s', $ip, $accountId);

        try {
            $redis = $this->redisFactory->get('default');
            $current = (int) $redis->incr($key);
            if ($current === 1) {
                $redis->expire($key, $windowSeconds);
            }

            if ($current > $maxRequests) {
                return $this->response
                    ->json([
                        'message' => 'Too many requests. Try again later.',
                        'code' => 'rate_limit_exceeded',
                    ])
                    ->withHeader('Retry-After', (string) $windowSeconds)
                    ->withStatus(429);
            }
        } catch (Throwable $throwable) {
            $this->loggerFactory
                ->get('default')
                ->error('rate_limit.failed', [
                    'path' => $path,
                    'reason' => $throwable->getMessage(),
                ]);
        }

        return $handler->handle($request);
    }

    private function shouldApply(ServerRequestInterface $request): bool
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return false;
        }

        if (env('APP_ENV', 'dev') === 'testing' && ! filter_var(env('WITHDRAW_RATE_LIMIT_IN_TEST', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return preg_match('#^/account/[^/]+/balance/withdraw$#', $request->getUri()->getPath()) === 1;
    }
}
