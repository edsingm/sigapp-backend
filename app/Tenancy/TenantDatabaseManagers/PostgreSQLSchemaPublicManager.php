<?php

declare(strict_types=1);

namespace App\Tenancy\TenantDatabaseManagers;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager;

class PostgreSQLSchemaPublicManager extends PostgreSQLSchemaManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $schema = $this->quoteIdentifier($tenant->database()->getName());

        return $this->database()->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $schema = $this->quoteIdentifier($tenant->database()->getName());

        return $this->database()->statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }

    public function databaseExists(string $name): bool
    {
        return (bool) $this->database()->select(
            'SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?',
            [$name]
        );
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['search_path'] = "{$databaseName},public";

        return $baseConfig;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
