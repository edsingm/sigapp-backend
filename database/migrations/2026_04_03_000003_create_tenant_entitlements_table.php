<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index()->comment('stancl/tenancy uses string IDs');
            $table->foreignIdFor(\App\Models\Central\Entitlement::class)->constrained()->cascadeOnDelete();
            $table->json('value')->comment('Valor do entitlement extra (bool ou int)');
            $table->integer('price')->default(0)->comment('Custo adicional mensal em centavos');
            $table->timestamps();

            $table->unique(['tenant_id', 'entitlement_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entitlements');
    }
};
