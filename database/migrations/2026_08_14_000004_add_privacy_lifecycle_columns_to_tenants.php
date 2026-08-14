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
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('wipe_scheduled_at')->nullable();
            $table->timestamp('wiped_at')->nullable();
            $table->timestamp('ai_document_transfer_accepted_at')->nullable();
            $table->timestamp('pii_encrypted_at')->nullable();
            $table->timestamp('wipe_notice_d60_sent_at')->nullable();
            $table->timestamp('wipe_notice_d83_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'cancelled_at',
                'wipe_scheduled_at',
                'wiped_at',
                'ai_document_transfer_accepted_at',
                'pii_encrypted_at',
                'wipe_notice_d60_sent_at',
                'wipe_notice_d83_sent_at',
            ]);
        });
    }
};
