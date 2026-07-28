<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')
                ->constrained('platform_announcements')
                ->cascadeOnDelete();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();

            $table->unique(
                ['announcement_id', 'tenant_id', 'user_id'],
                'pa_dismissals_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcement_dismissals');
    }
};
