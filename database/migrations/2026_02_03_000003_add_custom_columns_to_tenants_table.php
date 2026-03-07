<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('pending'); // pending, active, suspended, cancelled
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('encryption_key')->nullable();
            $table->boolean('database_created')->default(false);
            $table->timestamp('setup_completed_at')->nullable();
            $table->boolean('trial_extended')->default(false);

            // Dados temporários do admin (limpos após setup)
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('admin_password')->nullable(); // Será apagado após seed

            $table->index('status');
            $table->index('stripe_customer_id');
            $table->index('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'name',
                'slug',
                'status',
                'stripe_customer_id',
                'stripe_subscription_id',
                'plan_id',
                'trial_ends_at',
                'encryption_key',
                'database_created',
                'setup_completed_at',
                'trial_extended',
                'admin_name',
                'admin_email',
                'admin_password',
            ]);
        });
    }
};
