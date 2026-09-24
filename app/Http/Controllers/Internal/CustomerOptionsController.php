<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\BillingCustomer;
use Illuminate\Http\JsonResponse;

class CustomerOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $customers = BillingCustomer::query()
            ->with('team:id,name,slug')
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (BillingCustomer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'team' => $customer->team->name,
                'team_slug' => $customer->team->slug,
                'provider' => $customer->billing_provider,
                'synced' => $customer->synced_at !== null,
            ]);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'customers' => $customers,
        ]);
    }
}
