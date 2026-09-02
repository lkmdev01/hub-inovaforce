<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Support\ClientPreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientPreviewController extends Controller
{
    public function start(Request $request, Team $team, ClientPreview $preview): RedirectResponse
    {
        abort_unless($team->billingCustomer()->exists(), 404);

        $preview->start($request, $team);

        return redirect()->route('dashboard', ['current_team' => $team])
            ->with('success', 'Visualização do portal do cliente iniciada.');
    }

    public function stop(Request $request, ClientPreview $preview): RedirectResponse
    {
        $team = $preview->team($request);
        $preview->stop($request);

        if ($team) {
            return redirect()->route('admin.customers.show', $team)
                ->with('success', 'Visualização do cliente encerrada.');
        }

        return redirect()->route('admin.dashboard');
    }
}
