<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        if (! $request->isMethodSafe()) {
            $requestId = (string) $request->header('X-Inova-Request-Id');
            abort_unless($requestId !== '' && strlen($requestId) <= 100, 400, 'Identificador da operação ausente.');
            abort_unless(
                Cache::add('internal-api-request:'.hash('sha256', $client.':'.$requestId), true, now()->addMinutes(10)),
                409,
                'Essa operação já foi recebida.',
            );
        }

        return $next($request);
    }
}
