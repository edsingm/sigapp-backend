<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'features')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'features')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->json('features')->nullable()->after('max_terrenos');
        });
    }
};
