<?php

declare(strict_types=1);

namespace App\Service;

use App\Job\ProcessWithdrawJob;

use function Hyperf\Support\env;

class WithdrawProcessorDispatcher
{
    public function __construct(private readonly ProcessWithdrawJob $processWithdrawJob)
    {
    }

    public function dispatch(string $withdrawId): void
    {
        if (env('APP_ENV', 'dev') === 'testing' || ! class_exists(\Swoole\Coroutine::class)) {
            $this->processWithdrawJob->handle($withdrawId);
            return;
        }

        \Swoole\Coroutine::create(function () use ($withdrawId): void {
            $this->processWithdrawJob->handle($withdrawId);
        });
    }
}
