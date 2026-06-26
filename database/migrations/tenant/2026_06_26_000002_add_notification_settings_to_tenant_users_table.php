<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('quiet_hours_start', 5)->nullable()->after('locale');
            $table->string('quiet_hours_end', 5)->nullable()->after('quiet_hours_start');
            $table->string('email_digest_frequency', 10)->default('instant')->after('quiet_hours_end');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['quiet_hours_start', 'quiet_hours_end', 'email_digest_frequency']);
        });
    }
};
