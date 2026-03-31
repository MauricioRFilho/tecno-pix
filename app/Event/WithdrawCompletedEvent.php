<?php

declare(strict_types=1);

namespace App\Event;

class WithdrawCompletedEvent
{
    public function __construct(public readonly string $withdrawId)
    {
    }
}
