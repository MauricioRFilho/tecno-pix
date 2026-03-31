<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\WithdrawMethodInterface;
use App\Method\Pix\PixWithdrawMethod;
use App\Service\Exception\UnsupportedWithdrawMethodException;

class WithdrawMethodFactory
{
    public function __construct(private readonly PixWithdrawMethod $pixWithdrawMethod)
    {
    }

    public function make(string $method): WithdrawMethodInterface
    {
        return match ($method) {
            'pix' => $this->pixWithdrawMethod,
            default => throw new UnsupportedWithdrawMethodException(),
        };
    }
}
