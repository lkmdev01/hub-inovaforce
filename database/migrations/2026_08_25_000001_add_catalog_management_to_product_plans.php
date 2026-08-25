<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_plans', function (Blueprint $table) {
            $table->string('status')->default('active')->after('name')->index();
            $table->string('billing_type')->default('CREDIT_CARD')->after('billing_cycle');
            $table->string('pricing_model')->default('flat')->after('price');
            $table->unsignedInteger('minimum_seats')->default(1)->after('pricing_model');
            $table->unsignedInteger('maximum_seats')->nullable()->after('minimum_seats');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('pending_seats')->nullable()->after('seats');
        });

        DB::table('products')->where('status', 'inactive')->update(['status' => 'archived']);
    }

    public function down(): void
    {
        DB::table('products')->where('status', 'archived')->update(['status' => 'inactive']);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('pending_seats');
        });

        Schema::table('product_plans', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'billing_type', 'pricing_model', 'minimum_seats', 'maximum_seats']);
        });
    }
};
