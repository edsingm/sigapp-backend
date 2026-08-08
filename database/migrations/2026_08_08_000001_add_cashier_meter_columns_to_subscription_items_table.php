<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cashier 16 persists the optional meter metadata when it syncs a
     * subscription item. The project owns the original subscription_items
     * migration, so these package migrations are not applied automatically.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('subscription_items', 'meter_id')) {
            Schema::table('subscription_items', function (Blueprint $table): void {
                $table->string('meter_id')->nullable()->after('stripe_price');
            });
        }

        if (! Schema::hasColumn('subscription_items', 'meter_event_name')) {
            Schema::table('subscription_items', function (Blueprint $table): void {
                $table->string('meter_event_name')->nullable()->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_items', 'meter_id')) {
            Schema::table('subscription_items', function (Blueprint $table): void {
                $table->dropColumn('meter_id');
            });
        }

        if (Schema::hasColumn('subscription_items', 'meter_event_name')) {
            Schema::table('subscription_items', function (Blueprint $table): void {
                $table->dropColumn('meter_event_name');
            });
        }
    }
};
