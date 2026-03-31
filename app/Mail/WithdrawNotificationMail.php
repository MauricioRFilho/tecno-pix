<?php

declare(strict_types=1);

namespace App\Mail;

use FriendsOfHyperf\Mail\Mailable;

class WithdrawNotificationMail extends Mailable
{
    public function __construct(
        private readonly string $withdrawId,
        private readonly string $accountId,
        private readonly string $amount,
        private readonly string $pixType,
        private readonly string $pixKey
    ) {
    }

    public function build(): static
    {
        $html = sprintf(
            '<h1>Saque PIX concluido</h1><p>withdraw_id: %s</p><p>account_id: %s</p><p>amount: %s</p><p>pix_type: %s</p><p>pix_key: %s</p>',
            htmlspecialchars($this->withdrawId, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->accountId, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->amount, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->pixType, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->pixKey, ENT_QUOTES, 'UTF-8')
        );

        return $this
            ->subject(sprintf('Saque PIX concluido %s', $this->withdrawId))
            ->html($html);
    }
}
