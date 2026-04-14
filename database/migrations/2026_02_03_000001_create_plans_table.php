<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->integer('price')->default(0); // Em centavos
            $table->integer('trial_days')->default(7);
            $table->integer('max_users')->default(5); // -1 para ilimitado
            $table->integer('max_storage_gb')->default(5);
            $table->integer('max_terrenos')->default(100); // -1 para ilimitado
            $table->json('entitlements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
