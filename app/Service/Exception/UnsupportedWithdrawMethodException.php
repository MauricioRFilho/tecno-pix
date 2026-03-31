<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

class UnsupportedWithdrawMethodException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Unsupported withdraw method.');
    }
}
