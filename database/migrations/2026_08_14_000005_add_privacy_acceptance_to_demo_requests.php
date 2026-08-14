<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->boolean('accepted_privacy')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->string('privacy_document_key', 64)->nullable();
            $table->string('privacy_document_version', 64)->nullable();
            $table->string('privacy_document_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'accepted_privacy',
                'accepted_at',
                'privacy_document_key',
                'privacy_document_version',
                'privacy_document_hash',
            ]);
        });
    }
};
