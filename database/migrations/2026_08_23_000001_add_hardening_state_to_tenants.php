<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('pii_encryption_status')->nullable()->index();
            $table->unsignedBigInteger('pii_encryption_processed')->default(0);
            $table->text('pii_encryption_last_error')->nullable();
            $table->timestamp('pii_encryption_started_at')->nullable();

            $table->string('wipe_status')->nullable()->index();
            $table->string('wipe_step')->nullable();
            $table->unsignedInteger('wipe_attempts')->default(0);
            $table->text('wipe_last_error')->nullable();
            $table->timestamp('wipe_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['pii_encryption_status']);
            $table->dropIndex(['wipe_status']);
            $table->dropColumn([
                'pii_encryption_status',
                'pii_encryption_processed',
                'pii_encryption_last_error',
                'pii_encryption_started_at',
                'wipe_status',
                'wipe_step',
                'wipe_attempts',
                'wipe_last_error',
                'wipe_started_at',
            ]);
        });
    }
};
