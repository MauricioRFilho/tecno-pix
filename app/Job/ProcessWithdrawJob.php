<?php

declare(strict_types=1);

namespace App\Job;

use App\Model\Account;
use App\Model\AccountWithdraw;
use Hyperf\DbConnection\Db;

class ProcessWithdrawJob
{
    public function handle(string $withdrawId): void
    {
        Db::transaction(function () use ($withdrawId): void {
            $withdraw = AccountWithdraw::query()
                ->where('id', $withdrawId)
                ->lockForUpdate()
                ->first();

            if (! $withdraw instanceof AccountWithdraw) {
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
                return;
            }

            if (bccomp((string) $account->balance, (string) $withdraw->amount, 2) < 0) {
                $withdraw->error = true;
                $withdraw->error_reason = 'Insufficient balance at processing.';
                $withdraw->processed_at = date('Y-m-d H:i:s');
                $withdraw->save();
                return;
            }

            $account->balance = bcsub((string) $account->balance, (string) $withdraw->amount, 2);
            $account->save();

            $withdraw->done = true;
            $withdraw->error = false;
            $withdraw->error_reason = null;
            $withdraw->processed_at = date('Y-m-d H:i:s');
            $withdraw->save();
        });
    }
}
