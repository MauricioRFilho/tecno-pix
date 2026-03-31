<?php

declare(strict_types=1);

namespace App\Method\Pix;

class PixEmailValidator
{
    public function isValid(string $key): bool
    {
        return filter_var($key, FILTER_VALIDATE_EMAIL) !== false;
    }
}
