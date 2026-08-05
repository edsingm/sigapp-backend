<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('billing_profile_type', 2)->nullable();
            $table->text('billing_tax_id')->nullable();
            $table->string('billing_legal_name')->nullable();
            $table->string('billing_trade_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 20)->nullable();
            $table->string('billing_postal_code', 8)->nullable();
            $table->string('billing_street')->nullable();
            $table->string('billing_number', 30)->nullable();
            $table->string('billing_complement')->nullable();
            $table->string('billing_neighborhood')->nullable();
            $table->string('billing_city')->nullable();
            $table->char('billing_state', 2)->nullable();
            $table->char('billing_country', 2)->default('BR');
            $table->string('billing_municipal_registration')->nullable();
            $table->string('billing_tax_regime', 80)->nullable();
            $table->boolean('billing_profile_required')->default(true);
            $table->timestamp('billing_profile_completed_at')->nullable();
        });

        // O rollout não pode bloquear clientes que já utilizavam a plataforma.
        DB::table('tenants')->update(['billing_profile_required' => false]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_profile_type',
                'billing_tax_id',
                'billing_legal_name',
                'billing_trade_name',
                'billing_email',
                'billing_phone',
                'billing_postal_code',
                'billing_street',
                'billing_number',
                'billing_complement',
                'billing_neighborhood',
                'billing_city',
                'billing_state',
                'billing_country',
                'billing_municipal_registration',
                'billing_tax_regime',
                'billing_profile_required',
                'billing_profile_completed_at',
            ]);
        });
    }
};
