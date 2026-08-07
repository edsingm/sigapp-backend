<?php

declare(strict_types=1);

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_addon_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignIdFor(BillingAddon::class)->constrained()->restrictOnDelete();
            $table->string('stripe_subscription_id');
            $table->string('stripe_subscription_item_id')->unique();
            $table->string('stripe_price_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 30)->default(BillingAddonSubscriptionStatus::ACTIVE->value);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on((new Tenant)->getTable())
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'billing_addon_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['stripe_subscription_id', 'status']);
            $table->index('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addon_subscriptions');
    }
};
