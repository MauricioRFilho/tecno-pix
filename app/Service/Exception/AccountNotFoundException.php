<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

class AccountNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Account not found.');
    }
}
