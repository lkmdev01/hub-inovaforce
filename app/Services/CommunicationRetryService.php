<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Notifications\BillingEventNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class CommunicationRetryService
{
    public function __construct(
        private readonly WhatsAppGateway $whatsApp,
        private readonly SoftwareAccessGateway $softwareAccess,
    ) {}

    public function retry(CommunicationLog $log): void
    {
        $context = $log->contextData();
        $log->update([
            'status' => 'processing',
            'attempts' => $log->attempts + 1,
            'last_attempted_at' => now(),
            'error_message' => null,
        ]);

        try {
            if ($log->channel === 'email') {
                Notification::route('mail', $log->recipient)->notify(new BillingEventNotification(
                    (string) ($context['title'] ?? 'Atualização da sua conta'),
                    (string) ($context['message'] ?? 'Existe uma atualização disponível no Hub.'),
                    isset($context['url']) ? (string) $context['url'] : null,
                    'Consultar no Hub',
                ));
            } elseif ($log->channel === 'whatsapp') {
                $this->whatsApp->send($log->recipient, $log->template, $context);
            } elseif ($log->channel === 'software_webhook') {
                $this->softwareAccess->retry($log);
            } else {
                throw new RuntimeException('Canal de comunicação não suportado.');
            }

            $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
