<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TeamInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;
        $data = [
            'preheader' => $inviter->name.' convidou você para acessar '.$team->name.'.',
            'eyebrow' => 'CONVITE DE EQUIPE',
            'title' => 'Você recebeu um convite',
            'greeting' => 'Olá!',
            'intro' => $inviter->name.' convidou você para participar da equipe '.$team->name.' no Hub Inovaforce.',
            'actionUrl' => route('login', ['invitation' => $this->invitation->code]),
            'actionLabel' => 'Entrar e responder',
            'details' => ['Acesse o painel para aceitar ou recusar este convite.'],
            'outro' => 'Se você não esperava este convite, pode ignorar esta mensagem.',
            'securityNote' => 'Não encaminhe este e-mail: o convite foi enviado exclusivamente para você.',
        ];

        return (new MailMessage)
            ->subject('Convite para acessar '.$team->name.' | Hub Inovaforce')
            ->view(['html' => 'emails.action', 'text' => 'emails.action-text'], $data);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
