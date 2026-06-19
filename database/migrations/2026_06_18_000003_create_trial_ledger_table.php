<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_ledger', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('tenant_slug');
            $table->timestamp('granted_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_ledger');
    }
};
