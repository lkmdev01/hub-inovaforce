<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('community_sso_enabled')->default(false)->after('provisioning_webhook_secret');
            $table->text('community_sso_secret')->nullable()->after('community_sso_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['community_sso_enabled', 'community_sso_secret']);
        });
    }
};
