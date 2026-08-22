<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('abacatepay_customer_id')->nullable()->unique();
            $table->string('name');
            $table->string('email');
            $table->string('tax_id')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('zip_code')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('billing_cycle');
            $table->decimal('price', 10, 2);
            $table->string('abacatepay_product_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['product_id', 'name', 'billing_cycle']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('product_plan_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('pending_product_plan_id')->nullable()->after('product_plan_id')->constrained('product_plans')->nullOnDelete();
            $table->string('abacatepay_checkout_id')->nullable()->unique();
            $table->string('abacatepay_subscription_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id');
            $table->string('event');
            $table->json('payload');
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_product_plan_id');
            $table->dropConstrainedForeignId('product_plan_id');
            $table->dropColumn(['abacatepay_checkout_id', 'abacatepay_subscription_id', 'checkout_url']);
        });

        Schema::dropIfExists('product_plans');
        Schema::dropIfExists('billing_customers');
    }
};
