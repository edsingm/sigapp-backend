<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cidades', function (Blueprint $table): void {
            $table->integer('own_property')->nullable()->after('buyer_demand');
            $table->integer('rented_property')->nullable()->after('own_property');
        });
    }

    public function down(): void
    {
        Schema::table('cidades', function (Blueprint $table): void {
            $table->dropColumn(['own_property', 'rented_property']);
        });
    }
};