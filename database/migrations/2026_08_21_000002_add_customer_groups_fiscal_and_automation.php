<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color')->default('violet');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('billing_customers', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('fiscal_enabled')->default(false)->after('features');
            $table->string('municipal_service_id')->nullable()->after('fiscal_enabled');
            $table->string('municipal_service_code')->nullable()->after('municipal_service_id');
            $table->string('municipal_service_name')->nullable()->after('municipal_service_code');
            $table->text('fiscal_service_description')->nullable()->after('municipal_service_name');
            $table->text('fiscal_observations')->nullable()->after('fiscal_service_description');
            $table->decimal('fiscal_deductions', 10, 2)->default(0)->after('fiscal_observations');
            $table->string('fiscal_effective_period')->default('ON_PAYMENT_CONFIRMATION')->after('fiscal_deductions');
            $table->json('fiscal_taxes')->nullable()->after('fiscal_effective_period');
            $table->text('provisioning_webhook_url')->nullable()->after('fiscal_taxes');
            $table->text('provisioning_webhook_secret')->nullable()->after('provisioning_webhook_url');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('access_status')->default('pending')->after('status')->index();
            $table->string('access_reason')->nullable()->after('access_status');
            $table->timestamp('access_updated_at')->nullable()->after('access_reason');
            $table->timestamp('fiscal_configured_at')->nullable()->after('access_updated_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->text('receipt_url')->nullable()->after('payment_url');
            $table->text('bank_slip_url')->nullable()->after('receipt_url');
            $table->text('failure_reason')->nullable()->after('bank_slip_url');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
        });

        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_provider')->default('asaas');
            $table->string('external_invoice_id');
            $table->string('external_payment_id')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('number')->nullable();
            $table->string('validation_code')->nullable();
            $table->text('pdf_url')->nullable();
            $table->text('xml_url')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->date('effective_date')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['billing_provider', 'external_invoice_id']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('financial_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('asaas');
            $table->string('external_event_id');
            $table->string('type');
            $table->string('status')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['provider', 'external_event_id']);
            $table->index(['team_id', 'occurred_at']);
        });

        Schema::create('automation_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('severity')->default('warning');
            $table->string('status')->default('open');
            $table->string('title');
            $table->text('message');
            $table->string('deduplication_key')->unique();
            $table->text('action_url')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('recipient');
            $table->string('template');
            $table->string('status')->default('queued');
            $table->string('deduplication_key')->unique();
            $table->json('context')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        DB::table('subscriptions')->whereIn('status', ['active', 'trialing'])->update(['access_status' => 'active', 'access_updated_at' => now()]);
        DB::table('subscriptions')->where('status', 'past_due')->update(['access_status' => 'suspended', 'access_updated_at' => now()]);
        DB::table('subscriptions')->where('status', 'canceled')->update(['access_status' => 'revoked', 'access_updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('automation_alerts');
        Schema::dropIfExists('financial_events');
        Schema::dropIfExists('fiscal_documents');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['receipt_url', 'bank_slip_url', 'failure_reason', 'refunded_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['access_status', 'access_reason', 'access_updated_at', 'fiscal_configured_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_enabled', 'municipal_service_id', 'municipal_service_code', 'municipal_service_name',
                'fiscal_service_description', 'fiscal_observations', 'fiscal_deductions',
                'fiscal_effective_period', 'fiscal_taxes',
                'provisioning_webhook_url', 'provisioning_webhook_secret',
            ]);
        });

        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
        });

        Schema::dropIfExists('customer_groups');
    }
};
