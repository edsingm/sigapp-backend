#!/usr/bin/env bash

set -Eeuo pipefail

if [[ -z "${HEALTH_URL:-}" || -z "${EXPECTED_REVISION:-}" ]]; then
    echo "HEALTH_URL and EXPECTED_REVISION are required." >&2
    exit 1
fi

if [[ ! "${EXPECTED_REVISION}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "EXPECTED_REVISION must be a full lowercase Git SHA." >&2
    exit 1
fi

health_url="${HEALTH_URL%/}/api/v1/health/ready"
max_attempts=60
interval_seconds=10

for ((attempt = 1; attempt <= max_attempts; attempt++)); do
    response="$(curl --silent --show-error --max-time 8 "${health_url}" || true)"
    status="$(jq -r '.status // empty' <<<"${response}" 2>/dev/null || true)"
    revision="$(jq -r '.revision // empty' <<<"${response}" 2>/dev/null || true)"

    if [[ "${status}" != "down" && -n "${status}" && "${revision}" == "${EXPECTED_REVISION}" ]]; then
        echo "Deployment is ready at revision ${revision}."
        exit 0
    fi

    echo "Attempt ${attempt}/${max_attempts}: status=${status:-unavailable}, revision=${revision:-unavailable}."

    if ((attempt < max_attempts)); then
        sleep "${interval_seconds}"
    fi
done

echo "Deployment did not become ready at revision ${EXPECTED_REVISION} within $((max_attempts * interval_seconds)) seconds." >&2
exit 1
