<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        if (config('security.admin_requires_two_factor') && ! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('security.edit')->with('warning', 'Ative a autenticação em duas etapas para acessar a administração.');
        }

        return $next($request);
    }
}
