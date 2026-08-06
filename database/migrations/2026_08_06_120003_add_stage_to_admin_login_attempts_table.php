<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_login_attempts', function (Blueprint $table): void {
            $table->string('stage', 20)->nullable()->after('successful')->index();
        });
    }

    public function down(): void
    {
        Schema::table('admin_login_attempts', function (Blueprint $table): void {
            $table->dropColumn('stage');
        });
    }
};
