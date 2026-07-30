<?php

declare(strict_types=1);

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{key: string, label: string}> */
    private const MIGRATIONS = [
        'projects_room' => [
            'key' => 'projects.enabled',
            'label' => 'Projetos — CRUD',
        ],
        'projects.room' => [
            'key' => 'projects.planning',
            'label' => 'Projetos — Planejamento',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::MIGRATIONS as $legacyKey => $target) {
                $legacy = DB::table('entitlements')->where('key', $legacyKey)->first();
                if ($legacy === null) {
                    continue;
                }

                $canonical = DB::table('entitlements')->where('key', $target['key'])->first();
                $targetId = $canonical !== null
                    ? (int) $canonical->id
                    : DB::table('entitlements')->insertGetId([
                        'key' => $target['key'],
                        'label' => $target['label'],
                        'description' => 'Chave canônica criada pelo saneamento de catálogo.',
                        'type' => EntitlementType::FEATURE->value,
                        'scope' => EntitlementScope::API->value,
                        'default_value' => json_encode(false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->copyLinks('plan_entitlements', (int) $legacy->id, $targetId);
                $this->copyLinks('tenant_entitlements', (int) $legacy->id, $targetId);

                DB::table('plan_entitlements')->where('entitlement_id', $legacy->id)->delete();
                DB::table('tenant_entitlements')->where('entitlement_id', $legacy->id)->delete();
                DB::table('entitlements')->where('id', $legacy->id)->delete();
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (array_reverse(self::MIGRATIONS, true) as $legacyKey => $target) {
                $canonical = DB::table('entitlements')->where('key', $target['key'])->first();
                if ($canonical === null) {
                    continue;
                }

                $legacyId = DB::table('entitlements')->insertGetId([
                    'key' => $legacyKey,
                    'label' => $target['label'].' (legado)',
                    'description' => null,
                    'type' => EntitlementType::FEATURE->value,
                    'scope' => EntitlementScope::API->value,
                    'default_value' => json_encode(false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->copyLinks('plan_entitlements', (int) $canonical->id, $legacyId);
                $this->copyLinks('tenant_entitlements', (int) $canonical->id, $legacyId);
                DB::table('plan_entitlements')->where('entitlement_id', $canonical->id)->delete();
                DB::table('tenant_entitlements')->where('entitlement_id', $canonical->id)->delete();
                DB::table('entitlements')->where('id', $canonical->id)->delete();
            }
        });
    }

    private function copyLinks(string $table, int $sourceId, int $targetId): void
    {
        DB::table($table)
            ->where('entitlement_id', $sourceId)
            ->orderBy('id')
            ->eachById(function (object $row) use ($table, $targetId): void {
                $attributes = (array) $row;
                unset($attributes['id']);
                $attributes['entitlement_id'] = $targetId;
                DB::table($table)->insert($attributes);
            });
    }
};
