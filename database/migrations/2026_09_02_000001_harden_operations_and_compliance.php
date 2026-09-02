<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('event')->constrained()->nullOnDelete();
            $table->string('automation_status')->default('pending')->after('processed_at')->index();
            $table->unsignedInteger('automation_attempts')->default(0)->after('automation_status');
            $table->timestamp('automation_attempted_at')->nullable()->after('automation_attempts');
            $table->timestamp('automation_completed_at')->nullable()->after('automation_attempted_at');
            $table->text('automation_error')->nullable()->after('automation_completed_at');
        });

        Schema::table('communication_logs', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempted_at')->nullable()->after('scheduled_at');
        });

        Schema::table('billing_customers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('zip_code');
            $table->string('address_number', 30)->nullable()->after('address');
            $table->string('complement')->nullable()->after('address_number');
            $table->string('province')->nullable()->after('complement');
            $table->string('municipal_inscription')->nullable()->after('province');
            $table->string('state_inscription')->nullable()->after('municipal_inscription');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('cancel_at_period_end')->default(false)->after('canceled_at')->index();
            $table->timestamp('cancel_scheduled_at')->nullable()->after('cancel_at_period_end');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('kind')->default('subscription')->after('subscription_id')->index();
            $table->string('description')->nullable()->after('number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('accepted_terms_at')->nullable()->after('email_verified_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });

        Schema::create('system_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('status')->default('ok');
            $table->timestamp('ran_at');
            $table->json('details')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_runs');
        Schema::dropIfExists('audit_logs');

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('accepted_terms_at'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['kind', 'description']));
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['cancel_at_period_end']);
            $table->dropColumn(['cancel_at_period_end', 'cancel_scheduled_at']);
        });
        Schema::table('billing_customers', fn (Blueprint $table) => $table->dropColumn([
            'address', 'address_number', 'complement', 'province', 'municipal_inscription', 'state_inscription',
        ]));
        Schema::table('communication_logs', fn (Blueprint $table) => $table->dropColumn(['attempts', 'last_attempted_at']));
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['automation_status']);
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn([
                'automation_status', 'automation_attempts', 'automation_attempted_at',
                'automation_completed_at', 'automation_error',
            ]);
        });
    }
};
