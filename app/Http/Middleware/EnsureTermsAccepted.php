<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->accepted_terms_at) {
            return redirect()->route('legal.accept')->with('warning', 'Aceite os termos para continuar no portal.');
        }

        return $next($request);
    }
}
