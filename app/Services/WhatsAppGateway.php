<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppGateway
{
    public function configured(): bool
    {
        return filled(config('services.whatsapp.webhook_url'));
    }

    /** @param array<string, mixed> $context */
    public function send(string $recipient, string $template, array $context): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('O provedor de WhatsApp ainda não foi configurado.');
        }

        $request = Http::acceptJson()->asJson()->timeout(15)->retry(2, 250);
        if (filled(config('services.whatsapp.token'))) {
            $request = $request->withToken((string) config('services.whatsapp.token'));
        }

        $response = $request->post((string) config('services.whatsapp.webhook_url'), [
            'to' => preg_replace('/\D+/', '', $recipient),
            'template' => $template,
            'message' => $context['message'] ?? null,
            'action_url' => $context['url'] ?? null,
            'context' => $context,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('O gateway de WhatsApp recusou a mensagem.');
        }
    }
}
