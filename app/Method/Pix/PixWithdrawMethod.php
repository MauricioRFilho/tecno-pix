<?php

declare(strict_types=1);

namespace App\Method\Pix;

use App\Contract\WithdrawMethodInterface;
use App\Model\AccountWithdrawPix;
use App\Service\Exception\InvalidPixKeyException;

class PixWithdrawMethod implements WithdrawMethodInterface
{
    public function __construct(private readonly PixEmailValidator $pixEmailValidator)
    {
    }

    public function name(): string
    {
        return 'pix';
    }

    /**
     * @param array{method:string,pix?:array{type:string,key:string}} $payload
     */
    public function validate(array $payload): void
    {
        $pix = $payload['pix'] ?? null;
        if (! is_array($pix)) {
            return;
        }

        if (($pix['type'] ?? '') === 'email' && ! $this->pixEmailValidator->isValid((string) ($pix['key'] ?? ''))) {
            throw new InvalidPixKeyException();
        }
    }

    /**
     * @param array{method:string,pix?:array{type:string,key:string}} $payload
     */
    public function persistDetails(string $withdrawId, array $payload): void
    {
        $pix = $payload['pix'] ?? null;
        if (! is_array($pix)) {
            return;
        }

        AccountWithdrawPix::query()->create([
            'account_withdraw_id' => $withdrawId,
            'type' => (string) $pix['type'],
            'key' => (string) $pix['key'],
        ]);
    }
}
