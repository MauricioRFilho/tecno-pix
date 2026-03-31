<?php

declare(strict_types=1);

namespace App\Command;

use App\Job\ProcessWithdrawJob;
use App\Model\AccountWithdraw;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

#[Command(name: 'withdraw:process-scheduled')]
class ProcessScheduledWithdrawsCommand extends HyperfCommand
{
    protected string $description = 'Process pending scheduled withdraws that are ready to run.';

    public function __construct(private readonly ProcessWithdrawJob $processWithdrawJob)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = date('Y-m-d H:i:s');

        AccountWithdraw::query()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('error', false)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $now)
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->chunkById(100, function ($withdraws): void {
                foreach ($withdraws as $withdraw) {
                    $this->processWithdrawJob->handle((string) $withdraw->id);
                }
            }, 'id');

        return self::SUCCESS;
    }
}
