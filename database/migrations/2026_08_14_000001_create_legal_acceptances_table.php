<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('document_key');
            $table->string('document_version');
            $table->string('document_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'document_key', 'accepted_at']);
            $table->index('actor_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
