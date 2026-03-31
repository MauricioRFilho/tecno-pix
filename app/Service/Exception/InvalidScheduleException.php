<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

class InvalidScheduleException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Schedule must be in the future.');
    }
}
