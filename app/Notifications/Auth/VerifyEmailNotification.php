<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $data = [
            'preheader' => 'Confirme seu e-mail para acessar o Hub Inovaforce.',
            'eyebrow' => 'ACESSO SEGURO',
            'title' => 'Confirme seu e-mail',
            'greeting' => 'Olá, '.$notifiable->name.'!',
            'intro' => 'Seu cadastro no Hub Inovaforce está quase pronto. Confirme seu endereço de e-mail para liberar o acesso ao portal.',
            'actionUrl' => $url,
            'actionLabel' => 'Confirmar meu e-mail',
            'details' => ['Este link é válido por '.config('auth.verification.expire', 60).' minutos.'],
            'outro' => 'Se você não criou esta conta, ignore esta mensagem com segurança.',
            'securityNote' => 'A Inovaforce nunca solicitará sua senha por e-mail.',
        ];

        return (new MailMessage)
            ->subject('Confirme seu e-mail | Hub Inovaforce')
            ->view(['html' => 'emails.action', 'text' => 'emails.action-text'], $data);
    }
}
