<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Service\Exception\AccountNotFoundException;
use App\Service\Exception\InsufficientBalanceException;
use App\Service\Exception\InvalidScheduleException;
use App\Support\Uuid;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class WithdrawService
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly WithdrawMethodFactory $withdrawMethodFactory,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get('withdraw');
    }

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

        $withdrawMethod = $this->withdrawMethodFactory->make($payload['method']);
        $withdrawMethod->validate($payload);

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
        $created = Db::transaction(function () use ($payload, $isScheduled, $scheduledFor, $withdrawMethod): array {
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

            $withdrawMethod->persistDetails($withdrawId, $payload);

            return [
                'id' => $withdrawId,
                'account_id' => $payload['account_id'],
                'method' => $payload['method'],
                'amount' => $payload['amount'],
                'scheduled' => $isScheduled,
                'scheduled_for' => $scheduledFor,
            ];
        });

        $this->logger->info('withdraw.created', [
            'withdraw_id' => $created['id'],
            'account_id' => $created['account_id'],
            'method' => strtoupper($created['method']),
            'amount' => $created['amount'],
            'scheduled' => $created['scheduled'],
            'scheduled_for' => $created['scheduled_for'],
            'status' => 'accepted',
        ]);

        return $created;
    }
}
