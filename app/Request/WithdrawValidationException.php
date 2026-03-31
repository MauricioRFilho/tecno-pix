<?php

declare(strict_types=1);

namespace App\Request;

use RuntimeException;

class WithdrawValidationException extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
