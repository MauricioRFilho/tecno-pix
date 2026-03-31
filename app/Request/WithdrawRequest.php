<?php

declare(strict_types=1);

namespace App\Request;

class WithdrawRequest
{
    /**
     * @return array{account_id:string, method:string, amount:string, pix:array{type:string,key:string}, schedule:?string}
     */
    public function validate(string $accountId, array $payload): array
    {
        $errors = [];

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $accountId) || ! $this->isUuid($accountId)) {
            $errors['accountId'][] = 'The accountId must be a valid UUID.';
        }

        $method = $payload['method'] ?? null;
        if (! is_string($method) || $method === '') {
            $errors['method'][] = 'The method field is required.';
        } elseif ($method !== 'pix') {
            $errors['method'][] = 'The selected method is invalid.';
        }

        $amount = $payload['amount'] ?? null;
        if (! is_numeric($amount)) {
            $errors['amount'][] = 'The amount must be a number.';
        } elseif ((float) $amount <= 0) {
            $errors['amount'][] = 'The amount must be greater than 0.';
        }

        $pix = $payload['pix'] ?? null;
        if (! is_array($pix)) {
            $errors['pix'][] = 'The pix field is required.';
        } else {
            $pixType = $pix['type'] ?? null;
            $allowedTypes = ['cpf', 'cnpj', 'email', 'phone', 'random'];
            if (! is_string($pixType) || $pixType === '') {
                $errors['pix.type'][] = 'The pix.type field is required.';
            } elseif (! in_array($pixType, $allowedTypes, true)) {
                $errors['pix.type'][] = 'The selected pix.type is invalid.';
            }

            $pixKey = $pix['key'] ?? null;
            if (! is_string($pixKey) || trim($pixKey) === '') {
                $errors['pix.key'][] = 'The pix.key field is required.';
            } elseif (mb_strlen($pixKey) > 255) {
                $errors['pix.key'][] = 'The pix.key field must not be greater than 255 characters.';
            }
        }

        $schedule = $payload['schedule'] ?? null;
        if ($schedule !== null && $schedule !== '') {
            if (! is_string($schedule) || strtotime($schedule) === false) {
                $errors['schedule'][] = 'The schedule is not a valid date.';
            }
        }

        if ($errors !== []) {
            throw new WithdrawValidationException($errors);
        }

        return [
            'account_id' => $accountId,
            'method' => 'pix',
            'amount' => number_format((float) $amount, 2, '.', ''),
            'pix' => [
                'type' => (string) $pix['type'],
                'key' => trim((string) $pix['key']),
            ],
            'schedule' => $schedule === null || $schedule === '' ? null : (string) $schedule,
        ];
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
