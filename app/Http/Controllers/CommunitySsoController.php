<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CommunitySsoController extends Controller
{
    private const REQUEST_TTL_SECONDS = 120;

    public function launch(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'team' => ['required', 'string', 'max:255'],
            'timestamp' => ['required', 'integer'],
            'nonce' => ['required', 'string', 'min:16', 'max:100', 'alpha_dash:ascii'],
        ]);

        abort_unless(
            $product->status === 'active' && $product->community_sso_enabled && filled($product->community_sso_secret),
            404,
            'Integração com a comunidade indisponível para este produto.',
        );
        abort_if(abs(now()->timestamp - (int) $data['timestamp']) > self::REQUEST_TTL_SECONDS, 401, 'A solicitação expirou. Gere um novo acesso.');

        $expectedSignature = hash_hmac('sha256', $this->canonicalPayload($data), $product->community_sso_secret);
        $receivedSignature = Str::after((string) $request->header('X-Inovaforce-Signature'), 'sha256=');
        abort_unless(strlen($receivedSignature) === 64 && hash_equals($expectedSignature, $receivedSignature), 401, 'Assinatura inválida.');

        $nonceKey = 'community-sso:nonce:'.$product->id.':'.hash('sha256', $data['nonce']);
        abort_unless(Cache::add($nonceKey, true, now()->addMinutes(5)), 409, 'Esta solicitação já foi utilizada.');

        $team = Team::query()->where('slug', $data['team'])->first();
        $user = $team?->members()->whereRaw('LOWER(users.email) = ?', [Str::lower($data['email'])])->first();
        abort_unless($team && $user, 403, 'O usuário não pertence a este cliente.');
        abort_if($user->is_admin, 403, 'Contas administrativas não podem entrar por esta integração.');
        abort_unless($user->hasVerifiedEmail(), 403, 'O e-mail do usuário ainda não foi verificado no Hub.');

        $hasAccess = $team->subscriptions()
            ->where('product_id', $product->id)
            ->whereIn('status', ['active', 'trialing'])
            ->where('access_status', 'active')
            ->exists();
        abort_unless($hasAccess, 403, 'Este cliente não possui acesso ativo ao produto.');

        $token = Str::random(64);
        $expiresAt = now()->addSeconds(self::REQUEST_TTL_SECONDS);
        Cache::put($this->tokenKey($token), [
            'product_id' => $product->id,
            'team_id' => $team->id,
            'user_id' => $user->id,
        ], $expiresAt);

        return response()->json([
            'launch_url' => route('community.sso.consume', ['token' => $token]),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);

        try {
            $payload = Cache::lock($this->tokenKey($token).':lock', 5)
                ->block(2, fn () => Cache::pull($this->tokenKey($token)));
        } catch (LockTimeoutException) {
            abort(409, 'Este acesso já está sendo utilizado.');
        }
        abort_unless(is_array($payload), 410, 'Este acesso expirou ou já foi utilizado.');

        $product = Product::query()->find($payload['product_id'] ?? null);
        $team = Team::query()->find($payload['team_id'] ?? null);
        $user = User::query()->find($payload['user_id'] ?? null);
        $hasAccess = $product && $team && $user
            && $product->community_sso_enabled
            && ! $user->is_admin
            && $user->belongsToTeam($team)
            && $team->subscriptions()
                ->where('product_id', $product->id)
                ->whereIn('status', ['active', 'trialing'])
                ->where('access_status', 'active')
                ->exists();
        abort_unless($hasAccess, 403, 'O acesso à comunidade não está mais disponível.');

        Auth::login($user);
        $request->session()->regenerate();
        $user->switchTeam($team);

        return redirect(route('dashboard', ['current_team' => $team]).'#comunidade')
            ->with('success', 'Acesso realizado com segurança pelo '.$product->name.'.');
    }

    /** @param array{email: string, team: string, timestamp: int|string, nonce: string} $data */
    private function canonicalPayload(array $data): string
    {
        return implode("\n", [Str::lower(trim($data['email'])), $data['team'], (string) $data['timestamp'], $data['nonce']]);
    }

    private function tokenKey(string $token): string
    {
        return 'community-sso:token:'.hash('sha256', $token);
    }
}
