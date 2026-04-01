<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Mail\WithdrawNotificationMail;
use Hyperf\Testing\TestCase;

class EmailTemplateTest extends TestCase
{
    public function testEmailTemplateContainsWithdrawData(): void
    {
        $mail = new WithdrawNotificationMail(
            'withdraw-123',
            'account-456',
            '2026-04-01 10:30:00',
            '150.25',
            'email',
            'mauricio.srfh@gmail.com'
        );
        $html = $mail->toHtml();

        self::assertStringContainsString('Saque PIX concluido', $html);
        self::assertStringContainsString('withdraw_id: withdraw-123', $html);
        self::assertStringContainsString('account_id: account-456', $html);
        self::assertStringContainsString('processed_at: 2026-04-01 10:30:00', $html);
        self::assertStringContainsString('amount: 150.25', $html);
        self::assertStringContainsString('pix_key: mauricio.srfh@gmail.com', $html);
    }
}
