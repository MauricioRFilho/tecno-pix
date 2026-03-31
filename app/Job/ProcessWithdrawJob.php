<?php

declare(strict_types=1);

namespace App\Job;

use App\Event\WithdrawCompletedEvent;
use App\Model\Account;
use App\Model\AccountWithdraw;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class ProcessWithdrawJob
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get('withdraw');
    }

    public function handle(string $withdrawId): void
    {
        Db::transaction(function () use ($withdrawId): void {
            $withdraw = AccountWithdraw::query()
                ->where('id', $withdrawId)
                ->lockForUpdate()
                ->first();

            if (! $withdraw instanceof AccountWithdraw) {
                $this->logger->warning('withdraw.failed', [
                    'withdraw_id' => $withdrawId,
                    'reason' => 'Withdraw not found at processing.',
                    'status' => 'failed',
                ]);
                return;
            }

            if ((bool) $withdraw->done || (bool) $withdraw->error) {
                return;
            }

            if ((bool) $withdraw->scheduled && $withdraw->scheduled_for !== null && strtotime((string) $withdraw->scheduled_for) > time()) {
                return;
            }

            $account = Account::query()
                ->where('id', $withdraw->account_id)
                ->lockForUpdate()
                ->first();

            if (! $account instanceof Account) {
                $withdraw->error = true;
                $withdraw->error_reason = 'Account not found at processing.';
                $withdraw->processed_at = date('Y-m-d H:i:s');
                $withdraw->save();
                $this->logger->warning('withdraw.failed', [
                    'withdraw_id' => (string) $withdraw->id,
                    'account_id' => (string) $withdraw->account_id,
                    'amount' => (string) $withdraw->amount,
                    'reason' => 'Account not found at processing.',
                    'status' => 'failed',
                ]);
                return;
            }

            if (bccomp((string) $account->balance, (string) $withdraw->amount, 2) < 0) {
                $withdraw->error = true;
                $withdraw->error_reason = 'Insufficient balance at processing.';
                $withdraw->processed_at = date('Y-m-d H:i:s');
                $withdraw->save();
                $this->logger->warning('withdraw.failed', [
                    'withdraw_id' => (string) $withdraw->id,
                    'account_id' => (string) $withdraw->account_id,
                    'amount' => (string) $withdraw->amount,
                    'reason' => 'Insufficient balance at processing.',
                    'status' => 'failed',
                ]);
                return;
            }

            $account->balance = bcsub((string) $account->balance, (string) $withdraw->amount, 2);
            $account->save();

            $withdraw->done = true;
            $withdraw->error = false;
            $withdraw->error_reason = null;
            $withdraw->processed_at = date('Y-m-d H:i:s');
            $withdraw->save();

            $this->logger->info('withdraw.processed', [
                'withdraw_id' => (string) $withdraw->id,
                'account_id' => (string) $withdraw->account_id,
                'amount' => (string) $withdraw->amount,
                'method' => strtoupper((string) $withdraw->method),
                'scheduled' => (bool) $withdraw->scheduled,
                'status' => 'success',
            ]);

            $this->eventDispatcher->dispatch(new WithdrawCompletedEvent((string) $withdraw->id));
        });
    }
}
