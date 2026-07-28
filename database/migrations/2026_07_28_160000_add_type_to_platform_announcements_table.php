<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_announcements', function (Blueprint $table) {
            $table->string('type', 32)
                ->default('info')
                ->after('body')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('platform_announcements', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
