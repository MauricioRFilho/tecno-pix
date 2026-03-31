<?php

declare(strict_types=1);

namespace App\Contract;

interface WithdrawMethodInterface
{
    public function name(): string;

    /**
     * @param array{method:string,pix?:array{type:string,key:string}} $payload
     */
    public function validate(array $payload): void;

    /**
     * @param array{method:string,pix?:array{type:string,key:string}} $payload
     */
    public function persistDetails(string $withdrawId, array $payload): void;
}
