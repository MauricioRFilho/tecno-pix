<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

class InvalidPixKeyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid PIX key for type email.');
    }
}
