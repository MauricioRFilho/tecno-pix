<?php

declare(strict_types=1);

namespace App\Listener;

use App\Event\WithdrawCompletedEvent;
use App\Mail\WithdrawNotificationMail;
use App\Method\Pix\PixEmailValidator;
use App\Model\AccountWithdraw;
use FriendsOfHyperf\Mail\Contract\Factory as MailFactory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

use function Hyperf\Support\env;

#[Listener]
class WithdrawCompletedListener implements ListenerInterface
{
    public function __construct(
        private readonly MailFactory $mailFactory,
        private readonly PixEmailValidator $pixEmailValidator,
        private readonly StdoutLoggerInterface $logger
    ) {
    }

    public function listen(): array
    {
        return [
            WithdrawCompletedEvent::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof WithdrawCompletedEvent) {
            return;
        }

        $withdraw = AccountWithdraw::query()
            ->with(['account', 'pix'])
            ->find($event->withdrawId);

        if (! $withdraw instanceof AccountWithdraw || ! $withdraw->pix) {
            return;
        }

        $recipient = (string) env('MAIL_FROM_ADDRESS', 'no-reply@tecnopix.local');
        if ($withdraw->pix->type === 'email' && $this->pixEmailValidator->isValid((string) $withdraw->pix->key)) {
            $recipient = (string) $withdraw->pix->key;
        }

        try {
            $mail = new WithdrawNotificationMail(
                (string) $withdraw->id,
                (string) $withdraw->account_id,
                $withdraw->processed_at?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
                (string) $withdraw->amount,
                (string) $withdraw->pix->type,
                (string) $withdraw->pix->key
            );

            $this->mailFactory
                ->mailer()
                ->html($mail->toHtml(), static function ($message) use ($recipient, $withdraw): void {
                    $message
                        ->to($recipient)
                        ->subject(sprintf('Saque PIX concluido %s', (string) $withdraw->id));
                });

            $this->logger->info('withdraw.email_sent', [
                'withdraw_id' => (string) $withdraw->id,
                'recipient' => $recipient,
                'status' => 'success',
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->error('withdraw.email_failed', [
                'withdraw_id' => (string) $withdraw->id,
                'recipient' => $recipient,
                'reason' => $throwable->getMessage(),
                'status' => 'failed',
            ]);
        }
    }
}
