<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->string('billing_provider')->nullable()->after('team_id')->index();
            $table->string('external_customer_id')->nullable()->after('billing_provider');
            $table->unique(['billing_provider', 'external_customer_id']);
        });

        Schema::table('product_plans', function (Blueprint $table) {
            $table->string('billing_provider')->nullable()->after('price')->index();
            $table->string('external_product_id')->nullable()->after('billing_provider');
            $table->unique(['billing_provider', 'external_product_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_provider')->nullable()->after('seats')->index();
            $table->string('external_checkout_id')->nullable()->after('billing_provider');
            $table->string('external_subscription_id')->nullable()->after('external_checkout_id');
            $table->string('external_payment_id')->nullable()->after('external_subscription_id');
            $table->unique(['billing_provider', 'external_checkout_id']);
            $table->unique(['billing_provider', 'external_subscription_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_provider')->nullable()->after('subscription_id')->index();
            $table->string('external_payment_id')->nullable()->after('billing_provider');
            $table->text('payment_url')->nullable()->after('external_payment_id');
            $table->unique(['billing_provider', 'external_payment_id']);
        });

        DB::table('billing_customers')
            ->whereNotNull('abacatepay_customer_id')
            ->update([
                'billing_provider' => 'abacatepay',
                'external_customer_id' => DB::raw('abacatepay_customer_id'),
            ]);

        DB::table('product_plans')
            ->whereNotNull('abacatepay_product_id')
            ->update([
                'billing_provider' => 'abacatepay',
                'external_product_id' => DB::raw('abacatepay_product_id'),
            ]);

        DB::table('subscriptions')
            ->where(function ($query) {
                $query->whereNotNull('abacatepay_checkout_id')
                    ->orWhereNotNull('abacatepay_subscription_id');
            })
            ->update([
                'billing_provider' => 'abacatepay',
                'external_checkout_id' => DB::raw('abacatepay_checkout_id'),
                'external_subscription_id' => DB::raw('abacatepay_subscription_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['billing_provider', 'external_payment_id']);
            $table->dropColumn(['billing_provider', 'external_payment_id', 'payment_url']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['billing_provider', 'external_checkout_id']);
            $table->dropUnique(['billing_provider', 'external_subscription_id']);
            $table->dropColumn(['billing_provider', 'external_checkout_id', 'external_subscription_id', 'external_payment_id']);
        });

        Schema::table('product_plans', function (Blueprint $table) {
            $table->dropUnique(['billing_provider', 'external_product_id']);
            $table->dropColumn(['billing_provider', 'external_product_id']);
        });

        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropUnique(['billing_provider', 'external_customer_id']);
            $table->dropColumn(['billing_provider', 'external_customer_id']);
        });
    }
};
