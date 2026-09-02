<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAsaasWebhookAutomation;
use App\Models\AuditLog;
use App\Models\AutomationAlert;
use App\Models\CommunicationLog;
use App\Models\FinancialEvent;
use App\Models\FiscalDocument;
use App\Models\SystemRun;
use App\Models\WebhookEvent;
use App\Services\CommunicationRetryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminAutomationController extends Controller
{
    public function index(): View
    {
        $alerts = AutomationAlert::query()->with(['team', 'subscription.product'])->latest()->take(30)->get();
        $communications = CommunicationLog::query()->with('team')->latest()->take(30)->get();
        $events = FinancialEvent::query()->with(['team', 'subscription.product'])->latest('occurred_at')->take(40)->get();
        $documents = FiscalDocument::query()->with(['team', 'subscription.product'])->latest()->take(30)->get();
        $webhooks = WebhookEvent::query()->whereIn('automation_status', ['failed', 'processing', 'pending'])->latest()->take(30)->get();
        $systemRuns = SystemRun::query()->orderBy('name')->get();
        $auditLogs = AuditLog::query()->with(['user', 'team'])->latest()->take(30)->get();
        $metrics = [
            'open_alerts' => AutomationAlert::query()->where('status', 'open')->count(),
            'failed_communications' => CommunicationLog::query()->where('status', 'failed')->count(),
            'whatsapp_waiting' => CommunicationLog::query()->where('channel', 'whatsapp')->where('status', 'waiting_configuration')->count(),
            'fiscal_errors' => FiscalDocument::query()->where('status', 'error')->count(),
            'webhook_failures' => WebhookEvent::query()->where('automation_status', 'failed')->count(),
        ];

        return view('admin.automations.index', compact('alerts', 'communications', 'documents', 'events', 'webhooks', 'systemRuns', 'auditLogs', 'metrics'));
    }

    public function resolve(AutomationAlert $alert): RedirectResponse
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('success', 'Alerta marcado como resolvido.');
    }

    public function retryCommunication(CommunicationLog $communication, CommunicationRetryService $retry): RedirectResponse
    {
        try {
            $retry->retry($communication);
        } catch (\Throwable $exception) {
            return back()->with('error', 'O reenvio falhou: '.$exception->getMessage());
        }

        return back()->with('success', 'Comunicação reenviada com sucesso.');
    }

    public function reprocessWebhook(WebhookEvent $webhook): RedirectResponse
    {
        $webhook->update([
            'automation_status' => 'pending',
            'automation_error' => null,
            'automation_completed_at' => null,
        ]);
        ProcessAsaasWebhookAutomation::dispatch($webhook->id);

        return back()->with('success', 'Evento enviado novamente para processamento.');
    }
}
