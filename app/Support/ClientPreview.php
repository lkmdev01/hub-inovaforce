<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Http\Request;

class ClientPreview
{
    public const SESSION_TEAM_ID = 'client_preview.team_id';

    public const SESSION_EXPIRES_AT = 'client_preview.expires_at';

    public const DURATION_MINUTES = 30;

    public function start(Request $request, Team $team): void
    {
        $request->session()->put([
            self::SESSION_TEAM_ID => $team->getKey(),
            self::SESSION_EXPIRES_AT => now()->addMinutes(self::DURATION_MINUTES)->timestamp,
        ]);
    }

    public function stop(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_TEAM_ID,
            self::SESSION_EXPIRES_AT,
        ]);
    }

    public function team(Request $request): ?Team
    {
        $user = $request->user();

        if (
            ! $user?->is_admin ||
            (config('security.admin_requires_two_factor') && ! $user->two_factor_confirmed_at)
        ) {
            $this->stop($request);

            return null;
        }

        $teamId = $request->session()->get(self::SESSION_TEAM_ID);
        $expiresAt = $request->session()->get(self::SESSION_EXPIRES_AT);

        if (! is_numeric($teamId) || ! is_numeric($expiresAt) || now()->timestamp >= (int) $expiresAt) {
            $this->stop($request);

            return null;
        }

        $team = Team::query()->find((int) $teamId);

        if (! $team) {
            $this->stop($request);
        }

        return $team;
    }
}
