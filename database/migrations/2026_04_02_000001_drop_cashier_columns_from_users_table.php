<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove as colunas do Cashier que foram adicionadas erroneamente na tabela `users`.
 *
 * O model Billable desta aplicação é `Tenant` (não `User`), portanto estas colunas
 * nunca são populadas e representam apenas débito técnico.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'stripe_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Remove o índice antes da coluna (evita erro no PostgreSQL)
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });
    }
};
