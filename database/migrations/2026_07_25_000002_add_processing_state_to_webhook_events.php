<?php

declare(strict_types=1);

use App\Enums\WebhookEventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->string('status')->default(WebhookEventStatus::PENDING->value);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->text('last_error')->nullable();
            $table->index(['status', 'processing_started_at']);
        });

        DB::table('webhook_events')
            ->whereNotNull('processed_at')
            ->update(['status' => WebhookEventStatus::PROCESSED->value]);
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropIndex(['status', 'processing_started_at']);
            $table->dropColumn(['status', 'attempts', 'processing_started_at', 'last_error']);
        });
    }
};
