<?php

declare(strict_types=1);

namespace App\Command;

use App\Job\ProcessWithdrawJob;
use App\Model\AccountWithdraw;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

#[Command(name: 'withdraw:process-scheduled')]
class ProcessScheduledWithdrawsCommand extends HyperfCommand
{
    protected string $description = 'Process pending scheduled withdraws that are ready to run.';

    private LoggerInterface $logger;

    public function __construct(
        private readonly ProcessWithdrawJob $processWithdrawJob,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get('withdraw');
        parent::__construct();
    }

    public function handle(): int
    {
        $now = date('Y-m-d H:i:s');
        $processed = 0;
        $failed = 0;
        $pending = 0;

        $this->logger->info('cron.scheduled.start', [
            'at' => $now,
        ]);

        AccountWithdraw::query()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('error', false)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $now)
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->chunkById(100, function ($withdraws) use (&$processed, &$failed, &$pending): void {
                foreach ($withdraws as $withdraw) {
                    $this->processWithdrawJob->handle((string) $withdraw->id);

                    $fresh = AccountWithdraw::query()->find((string) $withdraw->id);
                    if (! $fresh instanceof AccountWithdraw) {
                        continue;
                    }

                    if ((bool) $fresh->done) {
                        ++$processed;
                        continue;
                    }

                    if ((bool) $fresh->error) {
                        ++$failed;
                        continue;
                    }

                    ++$pending;
                }
            }, 'id');

        $this->logger->info('cron.scheduled.summary', [
            'processed' => $processed,
            'failed' => $failed,
            'pending' => $pending,
            'at' => date('Y-m-d H:i:s'),
        ]);

        return self::SUCCESS;
    }
}
