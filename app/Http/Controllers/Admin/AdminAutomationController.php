<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationAlert;
use App\Models\CommunicationLog;
use App\Models\FinancialEvent;
use App\Models\FiscalDocument;
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
        $metrics = [
            'open_alerts' => AutomationAlert::query()->where('status', 'open')->count(),
            'failed_communications' => CommunicationLog::query()->where('status', 'failed')->count(),
            'whatsapp_waiting' => CommunicationLog::query()->where('channel', 'whatsapp')->where('status', 'waiting_configuration')->count(),
            'fiscal_errors' => FiscalDocument::query()->where('status', 'error')->count(),
        ];

        return view('admin.automations.index', compact('alerts', 'communications', 'documents', 'events', 'metrics'));
    }

    public function resolve(AutomationAlert $alert): RedirectResponse
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('success', 'Alerta marcado como resolvido.');
    }
}
