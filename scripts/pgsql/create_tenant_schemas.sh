#!/usr/bin/env bash
set -euo pipefail

: "${PGHOST:?PGHOST is required}"
: "${PGPORT:=5432}"
: "${PGUSER:?PGUSER is required}"
: "${PGPASSWORD:?PGPASSWORD is required}"
: "${PGDATABASE:?PGDATABASE is required}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

"${script_dir}/discover_tenants.sh" | while IFS= read -r tenant_db; do
  [ -z "${tenant_db}" ] && continue

  schema_name="${tenant_db}"

  PGPASSWORD="${PGPASSWORD}" psql \
    --host="${PGHOST}" \
    --port="${PGPORT}" \
    --username="${PGUSER}" \
    --dbname="${PGDATABASE}" \
    --command="CREATE SCHEMA IF NOT EXISTS \"${schema_name}\";"

  echo "Schema ensured: ${schema_name}"
done
