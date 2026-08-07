<?php

declare(strict_types=1);

use App\Enums\Common\BillingAddonType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_addons', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 30)->default(BillingAddonType::BUNDLE->value);
            $table->string('stripe_price_id')->nullable()->unique();
            $table->string('currency', 3)->default('brl');
            $table->string('billing_interval', 20)->default('month');
            $table->json('definition');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_addons');
    }
};
