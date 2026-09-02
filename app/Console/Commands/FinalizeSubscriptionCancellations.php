<?php

namespace App\Console\Commands;

use App\Models\AutomationAlert;
use App\Models\Subscription;
use App\Services\SoftwareAccessGateway;
use Illuminate\Console\Command;
use Throwable;

class FinalizeSubscriptionCancellations extends Command
{
    protected $signature = 'subscriptions:finalize-cancellations';

    protected $description = 'Encerra acessos de assinaturas canceladas ao fim do período contratado';

    public function handle(SoftwareAccessGateway $softwareAccess): int
    {
        $count = 0;

        Subscription::query()
            ->with(['product', 'team'])
            ->where('cancel_at_period_end', true)
            ->where('renews_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use ($softwareAccess, &$count): void {
                foreach ($subscriptions as $subscription) {
                    $subscription->update([
                        'status' => 'canceled', 'canceled_at' => now(), 'renews_at' => null,
                        'access_status' => 'revoked', 'access_reason' => 'customer_canceled_period_end',
                        'access_updated_at' => now(), 'cancel_at_period_end' => false,
                    ]);

                    try {
                        $softwareAccess->sync($subscription, 'SUBSCRIPTION_CANCELED_PERIOD_END');
                    } catch (Throwable $exception) {
                        AutomationAlert::query()->create([
                            'team_id' => $subscription->team_id,
                            'subscription_id' => $subscription->id,
                            'severity' => 'high',
                            'title' => 'Falha ao bloquear acesso após cancelamento',
                            'message' => $exception->getMessage(),
                        ]);
                    }

                    $count++;
                }
            });

        $this->info("{$count} assinatura(s) encerrada(s).");

        return self::SUCCESS;
    }
}
