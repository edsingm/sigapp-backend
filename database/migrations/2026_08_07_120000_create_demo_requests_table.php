<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('company');
            $table->string('city')->nullable();
            $table->string('role')->nullable();
            $table->text('land_context')->nullable();
            $table->string('source', 100)->default('site');
            $table->string('page', 100)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
