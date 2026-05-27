<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->json('app_polygons')->nullable()->after('declividade_percentual_medio');
            $table->json('steep_polygons')->nullable()->after('app_polygons');
        });
    }

    public function down(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->dropColumn(['app_polygons', 'steep_polygons']);
        });
    }
};
