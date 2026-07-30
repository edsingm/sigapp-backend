<?php

declare(strict_types=1);

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use App\Support\EntitlementCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table): void {
            $table->string('scope')
                ->default(EntitlementScope::INTERNAL->value)
                ->after('type');
        });

        DB::table('entitlements')
            ->where('type', EntitlementType::FEATURE->value)
            ->orderBy('id')
            ->eachById(function (object $entitlement): void {
                DB::table('entitlements')
                    ->where('id', $entitlement->id)
                    ->update([
                        'scope' => EntitlementCatalog::scopeForFeature((string) $entitlement->key)->value,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });
    }
};
