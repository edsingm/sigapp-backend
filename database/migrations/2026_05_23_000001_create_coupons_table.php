<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_coupon_id')->unique()->nullable();
            $table->string('code')->unique()->index();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('type'); // 'percent' | 'fixed'
            $table->integer('amount_off')->nullable(); // centavos (fixed)
            $table->integer('percent_off')->nullable(); // 0-100 (percent)
            $table->string('currency', 3)->nullable(); // 'brl'
            $table->integer('max_redemptions')->nullable();
            $table->integer('times_redeemed')->default(0);
            $table->dateTime('redeem_by')->nullable(); // validade do coupon
            $table->boolean('expires_after_first_redemption')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('applies_to_plans')->nullable(); // null = todos
            $table->json('applies_to_tenants')->nullable(); // null = todos
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
