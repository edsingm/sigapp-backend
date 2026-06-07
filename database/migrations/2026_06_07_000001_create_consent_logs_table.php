<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('consent_id')->index();          // ID gerado no browser (localStorage)
            $table->json('categories');                    // { functional, analytics, marketing }
            $table->string('version', 10);                // versão da política de cookies
            $table->string('ip_hash', 64)->nullable();    // sha256 do IP — anônimo (LGPD)
            $table->text('user_agent')->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
