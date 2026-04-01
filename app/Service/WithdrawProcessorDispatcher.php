<?php

declare(strict_types=1);

namespace App\Service;

use App\Job\ProcessWithdrawJob;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use Throwable;

use function Hyperf\Support\env;

class WithdrawProcessorDispatcher
{
    private static ?Channel $semaphore = null;

    private static int $semaphoreSize = 0;

    private LoggerInterface $logger;

    public function __construct(
        private readonly ProcessWithdrawJob $processWithdrawJob,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get('withdraw');
    }

    public function dispatch(string $withdrawId): void
    {
        if (env('APP_ENV', 'dev') === 'testing' || ! class_exists(\Swoole\Coroutine::class)) {
            $this->processWithdrawJob->handle($withdrawId);
            return;
        }

        \Swoole\Coroutine::create(function () use ($withdrawId): void {
            $acquired = false;
            $semaphore = $this->getSemaphore();

            try {
                if ($semaphore !== null) {
                    $acquired = $semaphore->push(1);
                }

                $this->processWithdrawJob->handle($withdrawId);
            } catch (Throwable $throwable) {
                $this->logger->error('withdraw.process_dispatch_failed', [
                    'withdraw_id' => $withdrawId,
                    'reason' => $throwable->getMessage(),
                    'status' => 'failed',
                ]);
            } finally {
                if ($acquired && $semaphore !== null) {
                    $semaphore->pop();
                }
            }
        });
    }

    private function getSemaphore(): ?Channel
    {
        $maxConcurrency = max(1, (int) env('WITHDRAW_PROCESS_MAX_CONCURRENCY', 8));
        if (! class_exists(Channel::class)) {
            return null;
        }

        if (self::$semaphore === null || self::$semaphoreSize !== $maxConcurrency) {
            self::$semaphore = new Channel($maxConcurrency);
            self::$semaphoreSize = $maxConcurrency;
        }

        return self::$semaphore;
    }
}
