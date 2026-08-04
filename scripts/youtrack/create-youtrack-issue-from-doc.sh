#!/usr/bin/env bash
# Cria o ticket do plano de correções backend no YouTrack.
# Verifica o último idReadable do projeto SIG e evita duplicata por summary.
#
# Uso:
#   export YOUTRACK_TOKEN='perm:...'
#   ./scripts/youtrack/create-sig-7-plano-correcoes.sh
#
# Dry-run (só consulta, não cria):
#   DRY_RUN=1 ./scripts/youtrack/create-sig-7-plano-correcoes.sh

set -euo pipefail

BASE_URL="${YOUTRACK_BASE_URL:-https://sigapp.youtrack.cloud}"

# Carrega .env da raiz do repo se YOUTRACK_TOKEN não estiver definido
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
if [[ -z "${YOUTRACK_TOKEN:-}" && -f "${REPO_ROOT}/.env" ]]; then
  # shellcheck disable=SC1091
  set -a && source "${REPO_ROOT}/.env" && set +a
fi

TOKEN="${YOUTRACK_TOKEN:?Defina YOUTRACK_TOKEN no .env ou no ambiente}"
DRY_RUN="${DRY_RUN:-0}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOC="${SCRIPT_DIR}/../../docs/2026-08-03-sig-14-plano-correcoes-backend-review.md"

SUMMARY="Backend: plano de correções pós-review (LGPD IA, performance, arquitetura)"
SUMMARY_KEY="plano de correções pós-review"

if [[ ! -f "$DOC" ]]; then
  echo "Documento não encontrado: $DOC" >&2
  exit 1
fi

api_get() {
  local path="$1"
  curl -sf "${BASE_URL}${path}" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H 'Accept: application/json'
}

echo "==> Autenticando e resolvendo projeto SIG..."
PROJECT_JSON="$(api_get '/api/admin/projects?fields=id,shortName,name')"
PROJECT_ID="$(echo "$PROJECT_JSON" | php -r '
  $data = json_decode(stream_get_contents(STDIN), true);
  foreach ($data as $p) {
    if (($p["shortName"] ?? "") === "SIG") { echo $p["id"]; exit(0); }
  }
  fwrite(STDERR, "Projeto SIG não encontrado\n"); exit(1);
')"
echo "    Project SIG id: ${PROJECT_ID}"

echo "==> Listando issues recentes do projeto SIG..."
ISSUES_JSON="$(api_get '/api/issues?query=project:SIG&fields=idReadable,summary,created&\$top=50&sort=created%20desc')"

echo "$ISSUES_JSON" | php -r '
  $issues = json_decode(stream_get_contents(STDIN), true);
  if (! is_array($issues)) { fwrite(STDERR, "Resposta inválida\n"); exit(1); }
  $maxNum = 0;
  $duplicates = [];
  $key = getenv("SUMMARY_KEY");
  if (! is_string($key) || $key === "") {
    fwrite(STDERR, "SUMMARY_KEY inválida\n"); exit(1);
  }
  foreach ($issues as $issue) {
    $readable = (string) ($issue["idReadable"] ?? "");
    if (preg_match("/^SIG-(\d+)$/", $readable, $m)) {
      $maxNum = max($maxNum, (int) $m[1]);
    }
    $summary = (string) ($issue["summary"] ?? "");
    if (stripos($summary, $key) !== false) {
      $duplicates[] = $readable . ": " . $summary;
    }
  }
  echo "Último idReadable conhecido: SIG-{$maxNum}\n";
  echo "Próximo provável (YouTrack auto): SIG-" . ($maxNum + 1) . "\n";
  if ($duplicates !== []) {
    echo "\n⚠️  Possível duplicata encontrada:\n";
    foreach ($duplicates as $d) { echo "   - {$d}\n"; }
    exit(2);
  }
' SUMMARY_KEY="$SUMMARY_KEY" || {
  code=$?
  if [[ $code -eq 2 ]]; then
    echo "Abortando criação para evitar duplicata." >&2
    exit 1
  fi
  exit $code
}

if [[ "$DRY_RUN" == "1" ]]; then
  echo "DRY_RUN=1 — nenhuma issue criada."
  exit 0
fi

DESCRIPTION="$(cat "$DOC")"

PAYLOAD="$(php -r '
  $projectId = getenv("YOUTRACK_PROJECT_ID");
  $summary = getenv("SUMMARY");
  $description = stream_get_contents(STDIN);
  echo json_encode([
    "project" => ["id" => $projectId],
    "summary" => $summary,
    "description" => $description,
  ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
' SUMMARY="$SUMMARY" YOUTRACK_PROJECT_ID="$PROJECT_ID" <<< "$DESCRIPTION")"

echo "==> Criando issue..."
RESPONSE="$(curl -sf -X POST "${BASE_URL}/api/issues?fields=id,idReadable,summary,description" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d "$PAYLOAD")"

echo "$RESPONSE" | php -r '
  $issue = json_decode(stream_get_contents(STDIN), true);
  $readable = $issue["idReadable"] ?? "?";
  $url = "https://sigapp.youtrack.cloud/issue/" . $readable;
  echo "\n✅ Issue criada: {$readable}\n   {$url}\n";
  echo "\nAtualize o doc com o id real se diferente de SIG-7.\n";
'
