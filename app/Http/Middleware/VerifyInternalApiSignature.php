<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = (string) $request->header('X-Inova-Client');
        $timestamp = (string) $request->header('X-Inova-Timestamp');
        $signature = (string) $request->header('X-Inova-Signature');
        $expectedClient = (string) config('services.internal_api.client_id');
        $secret = (string) config('services.internal_api.secret');
        $maxAge = (int) config('services.internal_api.max_age', 300);

        abort_if($expectedClient === '' || $secret === '', 503, 'Integração interna não configurada.');
        abort_unless(hash_equals($expectedClient, $client), 401, 'Cliente interno inválido.');
        abort_unless(ctype_digit($timestamp) && abs((int) now()->timestamp - (int) $timestamp) <= $maxAge, 401, 'Assinatura interna expirada.');

        $body = in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)
            ? ''
            : $request->getContent();
        $canonical = implode("\n", [
            $timestamp,
            strtoupper($request->method()),
            '/'.$request->path(),
            hash('sha256', $body),
        ]);
        $expected = 'sha256='.hash_hmac('sha256', $canonical, $secret);

        abort_unless(hash_equals($expected, $signature), 401, 'Assinatura interna inválida.');

        return $next($request);
    }
}
