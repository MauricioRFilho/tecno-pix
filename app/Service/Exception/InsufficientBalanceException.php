<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Insufficient balance.');
    }
}
