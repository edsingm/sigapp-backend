<?php

declare(strict_types=1);

use App\Enums\Common\AiCreditTransactionType;
use App\Enums\Common\TenantAddonPurchaseStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_addon_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignIdFor(BillingAddon::class)->constrained()->restrictOnDelete();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_price_id')->index();
            $table->text('checkout_url')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('amount_total')->nullable();
            $table->string('currency', 3);
            $table->string('status', 20)->default(TenantAddonPurchaseStatus::PENDING->value);
            $table->json('grant_snapshot');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on((new Tenant)->getTable())
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'billing_addon_id', 'status'], 'addon_purchases_tenant_addon_status_idx');
        });

        Schema::create('ai_credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignIdFor(TenantAddonPurchase::class)
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->string('type', 20)->default(AiCreditTransactionType::CREDIT->value);
            $table->decimal('amount_usd', 14, 6);
            $table->string('reference')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on((new Tenant)->getTable())
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_transactions');
        Schema::dropIfExists('tenant_addon_purchases');
    }
};
