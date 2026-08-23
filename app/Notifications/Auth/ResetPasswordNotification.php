<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $expiresIn = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $data = [
            'preheader' => 'Use o link seguro para redefinir sua senha.',
            'eyebrow' => 'SEGURANÇA DA CONTA',
            'title' => 'Redefina sua senha',
            'greeting' => 'Olá, '.$notifiable->name.'!',
            'intro' => 'Recebemos uma solicitação para redefinir a senha da sua conta no Hub Inovaforce.',
            'actionUrl' => $url,
            'actionLabel' => 'Criar nova senha',
            'details' => ['Este link expira em '.$expiresIn.' minutos e só pode ser usado para esta conta.'],
            'outro' => 'Se você não solicitou a redefinição, ignore esta mensagem. Sua senha continuará a mesma.',
            'securityNote' => 'A Inovaforce nunca solicitará sua senha por e-mail.',
        ];

        return (new MailMessage)
            ->subject('Redefinição de senha | Hub Inovaforce')
            ->view(['html' => 'emails.action', 'text' => 'emails.action-text'], $data);
    }
}
