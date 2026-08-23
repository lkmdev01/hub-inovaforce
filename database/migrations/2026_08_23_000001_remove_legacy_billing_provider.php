<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('billing_customers')
            ->where('billing_provider', 'abacatepay')
            ->update([
                'billing_provider' => null,
                'external_customer_id' => null,
                'synced_at' => null,
            ]);

        DB::table('product_plans')
            ->where('billing_provider', 'abacatepay')
            ->update([
                'billing_provider' => null,
                'external_product_id' => null,
            ]);

        DB::table('subscriptions')
            ->where('billing_provider', 'abacatepay')
            ->update([
                'billing_provider' => null,
                'external_checkout_id' => null,
                'external_subscription_id' => null,
                'external_payment_id' => null,
                'checkout_url' => null,
            ]);

        DB::table('webhook_events')->where('provider', 'abacatepay')->delete();

        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropUnique('billing_customers_abacatepay_customer_id_unique');
        });
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropColumn('abacatepay_customer_id');
        });

        Schema::table('product_plans', function (Blueprint $table) {
            $table->dropUnique('product_plans_abacatepay_product_id_unique');
        });
        Schema::table('product_plans', function (Blueprint $table) {
            $table->dropColumn('abacatepay_product_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_abacatepay_checkout_id_unique');
            $table->dropUnique('subscriptions_abacatepay_subscription_id_unique');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['abacatepay_checkout_id', 'abacatepay_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->string('abacatepay_customer_id')->nullable()->unique();
        });

        Schema::table('product_plans', function (Blueprint $table) {
            $table->string('abacatepay_product_id')->nullable()->unique();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('abacatepay_checkout_id')->nullable()->unique();
            $table->string('abacatepay_subscription_id')->nullable()->unique();
        });
    }
};
