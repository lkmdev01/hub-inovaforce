<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerAccessInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $companyName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $expiresIn = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $data = [
            'preheader' => 'Crie sua senha e acesse o portal da sua empresa.',
            'eyebrow' => 'SEU ACESSO AO HUB',
            'title' => 'Seu portal está pronto',
            'greeting' => 'Olá, '.$notifiable->name.'!',
            'intro' => 'A Inovaforce criou o acesso da empresa '.$this->companyName.' ao Hub. Defina sua senha pelo botão abaixo para consultar assinaturas, faturas e pagamentos.',
            'actionUrl' => $url,
            'actionLabel' => 'Criar minha senha',
            'details' => ['O link é individual e expira em '.$expiresIn.' minutos.'],
            'outro' => 'Depois de criar a senha, você poderá completar os dados financeiros quando precisar contratar um produto.',
            'securityNote' => 'Não encaminhe este e-mail. A Inovaforce nunca solicitará sua senha por mensagem.',
        ];

        return (new MailMessage)
            ->subject('Crie seu acesso ao Hub Inovaforce')
            ->view(['html' => 'emails.action', 'text' => 'emails.action-text'], $data);
    }
}
