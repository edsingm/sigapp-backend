<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('stripe_dispute_id')->unique();
            $table->string('stripe_charge_id');
            $table->unsignedInteger('amount');
            $table->string('reason')->nullable();
            $table->string('status', 50)->default('needs_response');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('stripe_charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
