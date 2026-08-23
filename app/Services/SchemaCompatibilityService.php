<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SchemaCompatibilityService
{
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', array_merge(
            $this->migrationNames(database_path('migrations'), excludeTenantDirectory: true),
            $this->migrationNames(database_path('migrations/tenant')),
        )));
    }

    /** @return array{fingerprint: string, compatible: bool, central_pending: list<string>, tenants_checked: int, tenants_drifted: int, tenant_errors: array<string, string>} */
    public function scan(): array
    {
        $centralExpected = $this->migrationNames(database_path('migrations'), excludeTenantDirectory: true);
        $centralRan = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->map(static fn (mixed $name): string => (string) $name)->all()
            : [];
        $centralPending = array_values(array_diff($centralExpected, $centralRan));
        $tenantExpected = $this->migrationNames(database_path('migrations/tenant'));
        $checked = 0;
        $drifted = 0;
        $errors = [];

        Tenant::query()
            ->where('database_created', true)
            ->whereNull('wiped_at')
            ->select(['id'])
            ->toBase()
            ->chunkById(50, function ($rows) use ($tenantExpected, &$checked, &$drifted, &$errors): void {
                foreach ($rows as $row) {
                    $tenant = Tenant::query()->findOrFail((string) $row->id);
                    $checked++;

                    try {
                        $pending = $tenant->run(function () use ($tenantExpected): array {
                            if (! Schema::connection('tenant')->hasTable('migrations')) {
                                return $tenantExpected;
                            }

                            $ran = DB::connection('tenant')->table('migrations')->pluck('migration')->all();

                            return array_values(array_diff($tenantExpected, $ran));
                        });

                        if ($pending !== []) {
                            $drifted++;
                            $errors[(string) $tenant->getKey()] = count($pending).' migration(s) pendente(s)';
                        }
                    } catch (Throwable $exception) {
                        $drifted++;
                        $errors[(string) $tenant->getKey()] = $exception->getMessage();
                    }
                }
            });

        return [
            'fingerprint' => $this->fingerprint(),
            'compatible' => $centralPending === [] && $drifted === 0,
            'central_pending' => $centralPending,
            'tenants_checked' => $checked,
            'tenants_drifted' => $drifted,
            'tenant_errors' => $errors,
        ];
    }

    /** @return list<string> */
    private function migrationNames(string $path, bool $excludeTenantDirectory = false): array
    {
        $names = collect(File::files($path))
            ->reject(fn ($file): bool => $excludeTenantDirectory && str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR))
            ->map(fn ($file): string => $file->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->all();

        return array_values($names);
    }
}
