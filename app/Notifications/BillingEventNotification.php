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
        $data = [
            'preheader' => $this->message,
            'eyebrow' => 'ATUALIZAÇÃO FINANCEIRA',
            'title' => $this->title,
            'greeting' => 'Olá, '.($notifiable->name ?? 'cliente').'!',
            'intro' => $this->message,
            'actionUrl' => $this->actionUrl,
            'actionLabel' => $this->actionLabel ?? 'Acessar o Hub',
            'details' => [],
            'outro' => 'Você pode acompanhar cobranças, faturas e assinaturas diretamente no Hub Inovaforce.',
            'securityNote' => 'Esta é uma mensagem automática sobre sua conta.',
        ];

        return (new MailMessage)
            ->subject($this->title)
            ->view(['html' => 'emails.action', 'text' => 'emails.action-text'], $data);
    }
}
