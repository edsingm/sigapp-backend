<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cidades', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('city');
            $table->string('state');
            $table->string('state_code', 2);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('capital')->default(false);
            $table->string('area_code', 10)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->integer('population')->nullable();
            $table->integer('employed')->nullable();
            $table->decimal('per_capta_income', 12, 2)->nullable();
            $table->decimal('property_maximum_value', 12, 2)->nullable();
            $table->decimal('buyer_demand', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['city']);
            $table->index(['state_code']);
            $table->index(['city', 'state_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cidades');
    }
};