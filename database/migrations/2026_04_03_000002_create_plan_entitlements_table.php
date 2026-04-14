<?php

use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Plan::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Entitlement::class)->constrained()->cascadeOnDelete();
            $table->json('value')->comment('Valor do entitlement para este plano (bool ou int)');
            $table->timestamps();

            $table->unique(['plan_id', 'entitlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entitlements');
    }
};
