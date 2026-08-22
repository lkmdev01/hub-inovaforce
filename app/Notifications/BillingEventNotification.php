<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $actionUrl = null,
        private readonly ?string $actionLabel = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Olá!')
            ->line($this->message);

        if ($this->actionUrl) {
            $mail->action($this->actionLabel ?? 'Acessar o Hub', $this->actionUrl);
        }

        return $mail->line('Esta mensagem foi enviada automaticamente pelo Hub Inovaforce.');
    }
}
