#!/usr/bin/env bash

set -Eeuo pipefail

required_variables=(
    DOKPLOY_URL
    DOKPLOY_API_TOKEN
    DOKPLOY_COMPOSE_ID
    HEALTH_URL
    EXPECTED_REVISION
    DEPLOYMENT_ENVIRONMENT
)

for variable in "${required_variables[@]}"; do
    if [[ -z "${!variable:-}" ]]; then
        echo "Required variable ${variable} is not configured." >&2
        exit 1
    fi
done

if [[ ! "${EXPECTED_REVISION}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "EXPECTED_REVISION must be a full lowercase Git SHA." >&2
    exit 1
fi

dokploy_base_url="${DOKPLOY_URL%/}"
deploy_payload="$(jq -n \
    --arg composeId "${DOKPLOY_COMPOSE_ID}" \
    --arg title "SIGAPP ${DEPLOYMENT_ENVIRONMENT} ${EXPECTED_REVISION:0:12}" \
    --arg description "GitHub Actions deployment of ${EXPECTED_REVISION}" \
    '{composeId: $composeId, title: $title, description: $description}')"

echo "Triggering ${DEPLOYMENT_ENVIRONMENT} deployment for ${EXPECTED_REVISION}."
curl \
    --fail-with-body \
    --silent \
    --show-error \
    --retry 2 \
    --retry-all-errors \
    --request POST \
    --header "Content-Type: application/json" \
    --header "x-api-key: ${DOKPLOY_API_TOKEN}" \
    --data "${deploy_payload}" \
    "${dokploy_base_url}/api/compose.deploy"

echo
echo "Dokploy accepted the deployment. Waiting for readiness and revision confirmation."
"$(dirname "$0")/verify-deployment-health.sh"
