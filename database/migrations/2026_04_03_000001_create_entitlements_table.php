<?php

use App\Enums\Common\EntitlementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Chave dot-notation: ex. prospection, viabilities.enabled, users');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('type')->default(EntitlementType::FEATURE->value)->comment('feature | limit');
            $table->json('default_value')->nullable()->comment('Valor padrão serializado em JSON');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
