<?php

namespace Database\Seeders;

use App\Models\BillingCustomer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Lucas Martins',
            'email' => 'demo@inovaforce.com.br',
            'is_admin' => true,
        ]);
        $user->currentTeam->forceFill([
            'name' => 'Empresa Demonstração',
            'slug' => 'empresa-demonstracao',
        ])->saveQuietly();

        $products = collect([
            ['name' => 'Flow CRM', 'slug' => 'flow-crm', 'description' => 'Centralize oportunidades, clientes e o desempenho do seu time comercial.', 'accent' => 'violet', 'features' => ['Funil de vendas visual', 'Automações comerciais', 'Relatórios em tempo real']],
            ['name' => 'Desk One', 'slug' => 'desk-one', 'description' => 'Atendimento organizado para entregar suporte rápido e próximo aos seus clientes.', 'accent' => 'sky', 'features' => ['Tickets e SLA', 'Base de conhecimento', 'Múltiplos canais']],
            ['name' => 'Pulse Analytics', 'slug' => 'pulse-analytics', 'description' => 'Indicadores claros para transformar os dados da operação em decisões melhores.', 'accent' => 'fuchsia', 'features' => ['Dashboards personalizados', 'Metas e indicadores', 'Exportação de relatórios']],
            ['name' => 'Sign Cloud', 'slug' => 'sign-cloud', 'description' => 'Envie, acompanhe e assine documentos com segurança e validade jurídica.', 'accent' => 'emerald', 'features' => ['Assinatura eletrônica', 'Trilhas de auditoria', 'Modelos reutilizáveis']],
        ])->map(fn (array $product) => Product::query()->create($product));

        $plans = $products->flatMap(function (Product $product) {
            return collect([
                ['name' => 'Essencial', 'billing_cycle' => 'monthly', 'price' => 89],
                ['name' => 'Profissional', 'billing_cycle' => 'monthly', 'price' => 179],
                ['name' => 'Enterprise', 'billing_cycle' => 'monthly', 'price' => 399],
                ['name' => 'Essencial', 'billing_cycle' => 'yearly', 'price' => 890],
                ['name' => 'Profissional', 'billing_cycle' => 'yearly', 'price' => 1790],
                ['name' => 'Enterprise', 'billing_cycle' => 'yearly', 'price' => 3990],
            ])->map(fn (array $plan) => ProductPlan::query()->create([...$plan, 'product_id' => $product->id]));
        });

        BillingCustomer::query()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Empresa Demonstração Ltda.',
            'email' => $user->email,
            'tax_id' => '12.345.678/0001-90',
            'cellphone' => '(11) 99999-9999',
            'zip_code' => '01310-100',
        ]);

        $crm = Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $products->firstWhere('slug', 'flow-crm')->id,
            'product_plan_id' => $plans->first(fn (ProductPlan $plan) => $plan->product_id === $products->firstWhere('slug', 'flow-crm')->id && $plan->name === 'Profissional' && $plan->billing_cycle === 'monthly')->id,
            'plan_name' => 'Profissional',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 179,
            'seats' => 8,
            'renews_at' => now()->addDays(12),
        ]);

        $desk = Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $products->firstWhere('slug', 'desk-one')->id,
            'product_plan_id' => $plans->first(fn (ProductPlan $plan) => $plan->product_id === $products->firstWhere('slug', 'desk-one')->id && $plan->name === 'Essencial' && $plan->billing_cycle === 'monthly')->id,
            'plan_name' => 'Essencial',
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
            'amount' => 89,
            'seats' => 3,
            'renews_at' => now()->addDays(7),
        ]);

        Invoice::query()->create([
            'team_id' => $user->current_team_id,
            'subscription_id' => $crm->id,
            'number' => 'INV-202607-0012',
            'status' => 'paid',
            'currency' => 'BRL',
            'subtotal' => 179,
            'total' => 179,
            'issued_at' => now()->subMonth()->startOfMonth(),
            'due_at' => now()->subMonth()->addDays(7)->startOfMonth(),
            'paid_at' => now()->subMonth()->addDays(3),
        ]);

        Invoice::query()->create([
            'team_id' => $user->current_team_id,
            'subscription_id' => $desk->id,
            'number' => 'INV-202608-0018',
            'status' => 'open',
            'currency' => 'BRL',
            'subtotal' => 89,
            'total' => 89,
            'issued_at' => today(),
            'due_at' => today()->addDays(7),
        ]);
    }
}
