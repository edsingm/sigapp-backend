<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legal_acceptances')) {
            Schema::table('legal_acceptances', function (Blueprint $table) {
                if (! Schema::hasColumn('legal_acceptances', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->index(['user_id', 'document_key', 'accepted_at']);
                }
            });

            return;
        }

        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_key');
            $table->string('document_version');
            $table->string('document_hash', 64);
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['user_id', 'document_key', 'accepted_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('legal_acceptances', 'tenant_id')) {
            Schema::table('legal_acceptances', function (Blueprint $table) {
                if (Schema::hasColumn('legal_acceptances', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
            });

            return;
        }

        Schema::dropIfExists('legal_acceptances');
    }
};
