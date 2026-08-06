<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('admin_mfa_secret')->nullable();
            $table->timestamp('admin_mfa_confirmed_at')->nullable();
            $table->bigInteger('admin_mfa_last_used_timestep')->nullable();
            $table->unsignedInteger('admin_mfa_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'admin_mfa_secret',
                'admin_mfa_confirmed_at',
                'admin_mfa_last_used_timestep',
                'admin_mfa_version',
            ]);
        });
    }
};
