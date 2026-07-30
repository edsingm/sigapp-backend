<?php

declare(strict_types=1);

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CREATED_DESCRIPTION = 'Chave canônica criada pela migration de saneamento de catálogo.';

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
                        'description' => self::CREATED_DESCRIPTION,
                        'type' => EntitlementType::FEATURE->value,
                        'scope' => EntitlementScope::API->value,
                        'default_value' => json_encode(false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->mergeLinks('plan_entitlements', 'plan_id', (int) $legacy->id, $targetId);
                $this->mergeLinks('tenant_entitlements', 'tenant_id', (int) $legacy->id, $targetId);

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

                $legacy = DB::table('entitlements')->where('key', $legacyKey)->first();
                $legacyId = $legacy !== null
                    ? (int) $legacy->id
                    : DB::table('entitlements')->insertGetId([
                        'key' => $legacyKey,
                        'label' => $target['label'].' (legado)',
                        'description' => null,
                        'type' => EntitlementType::FEATURE->value,
                        'scope' => EntitlementScope::API->value,
                        'default_value' => json_encode(false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->mergeLinks('plan_entitlements', 'plan_id', (int) $canonical->id, $legacyId);
                $this->mergeLinks('tenant_entitlements', 'tenant_id', (int) $canonical->id, $legacyId);

                if ($canonical->description === self::CREATED_DESCRIPTION) {
                    DB::table('plan_entitlements')->where('entitlement_id', $canonical->id)->delete();
                    DB::table('tenant_entitlements')->where('entitlement_id', $canonical->id)->delete();
                    DB::table('entitlements')->where('id', $canonical->id)->delete();
                }
            }
        });
    }

    private function mergeLinks(
        string $table,
        string $ownerColumn,
        int $sourceId,
        int $targetId,
    ): void {
        DB::table($table)
            ->where('entitlement_id', $sourceId)
            ->orderBy('id')
            ->eachById(function (object $row) use ($table, $ownerColumn, $targetId): void {
                $ownerId = $row->{$ownerColumn};
                $targetExists = DB::table($table)
                    ->where($ownerColumn, $ownerId)
                    ->where('entitlement_id', $targetId)
                    ->exists();

                if ($targetExists) {
                    return;
                }

                $attributes = (array) $row;
                unset($attributes['id']);
                $attributes['entitlement_id'] = $targetId;
                DB::table($table)->insert($attributes);
            });
    }
};
