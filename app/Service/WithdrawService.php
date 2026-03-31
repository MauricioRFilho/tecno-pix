<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Service\Exception\AccountNotFoundException;
use App\Service\Exception\InsufficientBalanceException;
use App\Service\Exception\InvalidScheduleException;
use App\Support\Uuid;
use Hyperf\DbConnection\Db;

class WithdrawService
{
    /**
     * @param array{account_id:string, method:string, amount:string, pix:array{type:string,key:string}, schedule:?string} $payload
     * @return array{id:string, account_id:string, method:string, amount:string, scheduled:bool, scheduled_for:?string}
     */
    public function create(array $payload): array
    {
        $account = Account::query()->find($payload['account_id']);
        if (! $account instanceof Account) {
            throw new AccountNotFoundException();
        }

        if (bccomp((string) $account->balance, $payload['amount'], 2) < 0) {
            throw new InsufficientBalanceException();
        }

        $scheduledFor = null;
        $isScheduled = false;
        if ($payload['schedule'] !== null) {
            $scheduledAt = strtotime($payload['schedule']);
            if ($scheduledAt === false || $scheduledAt <= time()) {
                throw new InvalidScheduleException();
            }

            $isScheduled = true;
            $scheduledFor = date('Y-m-d H:i:s', $scheduledAt);
        }

        /** @var array{id:string, account_id:string, method:string, amount:string, scheduled:bool, scheduled_for:?string} $created */
        $created = Db::transaction(function () use ($payload, $isScheduled, $scheduledFor): array {
            $withdrawId = Uuid::v4();

            AccountWithdraw::query()->create([
                'id' => $withdrawId,
                'account_id' => $payload['account_id'],
                'method' => $payload['method'],
                'amount' => $payload['amount'],
                'scheduled' => $isScheduled,
                'scheduled_for' => $scheduledFor,
                'done' => false,
                'error' => false,
                'error_reason' => null,
                'processed_at' => null,
            ]);

            AccountWithdrawPix::query()->create([
                'account_withdraw_id' => $withdrawId,
                'type' => $payload['pix']['type'],
                'key' => $payload['pix']['key'],
            ]);

            return [
                'id' => $withdrawId,
                'account_id' => $payload['account_id'],
                'method' => $payload['method'],
                'amount' => $payload['amount'],
                'scheduled' => $isScheduled,
                'scheduled_for' => $scheduledFor,
            ];
        });

        return $created;
    }
}
