<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPreviewIsReadOnly
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->attributes->has('clientPreviewTeam') && ! $request->isMethodSafe(),
            403,
            'O modo de visualização do cliente é somente leitura.',
        );

        return $next($request);
    }
}
