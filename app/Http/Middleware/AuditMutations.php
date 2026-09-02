<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Team;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && ! $request->isMethodSafe() && $response->getStatusCode() < 400) {
            $subject = collect($request->route()?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);
            if (! $subject instanceof Model) {
                $subject = null;
            }

            $team = collect($request->route()?->parameters() ?? [])->first(fn ($value) => $value instanceof Team)
                ?? $request->user()->currentTeam;
            if (! $team instanceof Team) {
                $team = null;
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'team_id' => $team?->id,
                'action' => (string) ($request->route()?->getName() ?? $request->path()),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'metadata' => ['method' => $request->method(), 'changed_fields' => collect($request->except(['_token', '_method', 'password', 'password_confirmation']))->keys()->values()->all()],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }
}
