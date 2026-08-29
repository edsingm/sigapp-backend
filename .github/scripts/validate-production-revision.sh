#!/usr/bin/env bash

set -Eeuo pipefail

if [[ -z "${REVISION:-}" || ! "${REVISION}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Revision must be a full 40-character lowercase Git SHA." >&2
    exit 1
fi

if ! git cat-file -e "${REVISION}^{commit}" 2>/dev/null; then
    echo "Revision ${REVISION} does not exist in this repository." >&2
    exit 1
fi

if ! git merge-base --is-ancestor "${REVISION}" origin/main; then
    echo "Revision ${REVISION} is not part of main." >&2
    exit 1
fi

echo "Revision ${REVISION} is a valid commit from main."
