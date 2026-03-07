# PostgreSQL Migration (Central + Tenant Schemas)

This document complements the code changes for PostgreSQL schema-based tenancy.

## Target layout

- PostgreSQL database: one physical database (default schema `public`)
- Central tables: `public`
- Tenant tables: one schema per tenant (e.g. `tenant_acme`)

## Prerequisites

- `pgloader`
- `psql`
- `mysql` client
- Backend code already switched to schema tenancy (this branch)

## Environment variables used by the helper scripts

- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_USER`
- `MYSQL_PASSWORD`
- `MYSQL_CENTRAL_DATABASE`
- `PGHOST`
- `PGPORT`
- `PGUSER`
- `PGPASSWORD`
- `PGDATABASE`
- `TENANT_PREFIX` (default `tenant_`)

## Suggested migration flow (non-production)

1. Stop app/workers that write to MySQL.
2. Backup MySQL central + all tenant databases.
3. Create PostgreSQL target database.
4. Run central migrations (`public`) to validate schema compatibility.
5. Create tenant schemas in PostgreSQL using `scripts/pgsql/create_tenant_schemas.sh`.
6. Load central database with `pgloader` into `public`.
7. Load each MySQL tenant database into its PostgreSQL tenant schema.
8. Reset PostgreSQL sequences using `scripts/pgsql/reset_sequences.sql`.
9. Run validation queries / count checks.
10. Switch `.env` to PostgreSQL and run smoke tests.

## pgloader notes for tenant schemas

`pgloader` supports MySQL -> PostgreSQL directly, but tenant schema mapping usually needs a dedicated load file per tenant (or an `AFTER LOAD DO` section that moves objects into the tenant schema).

Recommended approach:

1. Generate one `.load` file per tenant database.
2. Ensure the tenant schema exists first.
3. Set PostgreSQL `search_path` to the tenant schema during load, or move imported tables to the tenant schema in `AFTER LOAD DO`.

Store custom load files outside the repo if they contain credentials.

## Sequence reset

Run:

```bash
psql -f scripts/pgsql/reset_sequences.sql
```

This defines and executes a helper function that resets all sequences in `public` and all tenant schemas matching `tenant_%`.

## Validation helpers

- `scripts/pgsql/discover_tenants.sh`: list MySQL tenant databases
- `scripts/pgsql/create_tenant_schemas.sh`: create target PostgreSQL schemas from tenant DB names
- `scripts/pgsql/validate_counts.sh`: basic row-count spot checks for central and tenant schemas
