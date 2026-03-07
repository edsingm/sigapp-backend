#!/usr/bin/env bash
set -euo pipefail

: "${PGHOST:?PGHOST is required}"
: "${PGPORT:=5432}"
: "${PGUSER:?PGUSER is required}"
: "${PGPASSWORD:?PGPASSWORD is required}"
: "${PGDATABASE:?PGDATABASE is required}"

query_central='
SELECT ''public.users'' AS table_ref, count(*) AS total FROM public.users
UNION ALL
SELECT ''public.tenants'', count(*) FROM public.tenants
UNION ALL
SELECT ''public.domains'', count(*) FROM public.domains
ORDER BY table_ref;
'

PGPASSWORD="${PGPASSWORD}" psql \
  --host="${PGHOST}" \
  --port="${PGPORT}" \
  --username="${PGUSER}" \
  --dbname="${PGDATABASE}" \
  --command="${query_central}"

echo
echo "Tenant schemas and table counts (users, terrenos when available):"

PGPASSWORD="${PGPASSWORD}" psql \
  --host="${PGHOST}" \
  --port="${PGPORT}" \
  --username="${PGUSER}" \
  --dbname="${PGDATABASE}" <<'SQL'
DO $$
DECLARE
    s record;
    users_count bigint;
    terrenos_count bigint;
BEGIN
    FOR s IN
        SELECT schema_name
        FROM information_schema.schemata
        WHERE schema_name LIKE 'tenant\_%' ESCAPE '\'
        ORDER BY schema_name
    LOOP
        users_count := NULL;
        terrenos_count := NULL;

        IF EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = s.schema_name AND table_name = 'users'
        ) THEN
            EXECUTE format('SELECT count(*) FROM %I.users', s.schema_name) INTO users_count;
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = s.schema_name AND table_name = 'terrenos'
        ) THEN
            EXECUTE format('SELECT count(*) FROM %I.terrenos', s.schema_name) INTO terrenos_count;
        END IF;

        RAISE NOTICE '% | users=% | terrenos=%', s.schema_name, COALESCE(users_count::text, 'n/a'), COALESCE(terrenos_count::text, 'n/a');
    END LOOP;
END;
$$;
SQL
