#!/usr/bin/env bash
# =============================================================================
# scripts/run-tests.sh — Executa todos os testes e salva resultados em JSON
# Uso:
#   ./scripts/run-tests.sh                     # unitários + E2E (sematst)
#   ./scripts/run-tests.sh --unit-only         # apenas PHPUnit
#   ./scripts/run-tests.sh --e2e-only          # apenas Playwright
#   BASE_URL=http://localhost:8090 ./scripts/run-tests.sh
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESULTS="$ROOT/tests/results"
UNIT_ONLY=false
E2E_ONLY=false

for arg in "$@"; do
  case $arg in
    --unit-only) UNIT_ONLY=true ;;
    --e2e-only)  E2E_ONLY=true  ;;
  esac
done

mkdir -p "$RESULTS"

# URL padrão: ambiente de homologação
BASE_URL="${BASE_URL:-https://sematst.protocolosead.com}"

echo ""
echo "========================================================"
echo "  SEMA — Suite de Testes Automatizados"
echo "  Alvo: $BASE_URL"
echo "  Branch: $(git branch --show-current 2>/dev/null || echo 'N/A')"
echo "  Commit: $(git rev-parse --short HEAD 2>/dev/null || echo 'N/A')"
echo "  Data: $(date '+%d/%m/%Y %H:%M:%S')"
echo "========================================================"

PHPUNIT_OK=true
E2E_OK=true
PHPUNIT_TOTAL=0
PHPUNIT_PASSED=0
E2E_TOTAL=0
E2E_PASSED=0

# ─── 1. Testes Unitários (PHPUnit) ───────────────────────────────────────────
if [ "$E2E_ONLY" = false ]; then
  echo ""
  echo "▶  Executando testes unitários (PHPUnit)..."

  if [ ! -f "$ROOT/vendor/bin/phpunit" ]; then
    echo "   ⚠ PHPUnit não encontrado. Execute: composer install"
    PHPUNIT_OK=false
  else
    set +e
    "$ROOT/vendor/bin/phpunit" \
      --log-junit "$RESULTS/phpunit-junit.xml" \
      --no-progress 2>&1
    PHPUNIT_EXIT=$?
    set -e

    php "$ROOT/tests/helpers/junit-to-json.php" \
        "$RESULTS/phpunit-junit.xml" \
        "$RESULTS/phpunit-results.json"

    PHPUNIT_TOTAL=$(php -r "
      \$j = json_decode(file_get_contents('$RESULTS/phpunit-results.json'), true);
      echo \$j['summary']['total'];
    ")
    PHPUNIT_PASSED=$(php -r "
      \$j = json_decode(file_get_contents('$RESULTS/phpunit-results.json'), true);
      echo \$j['summary']['passed'];
    ")

    [ $PHPUNIT_EXIT -eq 0 ] || PHPUNIT_OK=false
    echo "   PHPUnit: $PHPUNIT_PASSED/$PHPUNIT_TOTAL passou"
  fi
fi

# ─── 2. Testes E2E (Playwright) ──────────────────────────────────────────────
if [ "$UNIT_ONLY" = false ]; then
  echo ""
  echo "▶  Executando testes E2E (Playwright) → $BASE_URL"

  if ! command -v npx &>/dev/null; then
    echo "   ⚠ npx não encontrado. Instale o Node.js."
    E2E_OK=false
  else
    set +e
    BASE_URL="$BASE_URL" npx playwright test \
      --reporter=json \
      --output="$RESULTS/playwright-artifacts" \
      2>"$RESULTS/playwright-raw.json"
    E2E_EXIT=$?
    set -e

    php "$ROOT/tests/helpers/playwright-to-json.php" \
        "$RESULTS/playwright-raw.json" \
        "$RESULTS/playwright-results.json"

    E2E_TOTAL=$(php -r "
      \$j = json_decode(file_get_contents('$RESULTS/playwright-results.json'), true);
      echo \$j['summary']['total'];
    ")
    E2E_PASSED=$(php -r "
      \$j = json_decode(file_get_contents('$RESULTS/playwright-results.json'), true);
      echo \$j['summary']['passed'];
    ")

    [ $E2E_EXIT -eq 0 ] || E2E_OK=false
    echo "   Playwright: $E2E_PASSED/$E2E_TOTAL passou"

    # Gera relatório HTML do Playwright também
    BASE_URL="$BASE_URL" npx playwright show-report --host 127.0.0.1 &>/dev/null || true
  fi
fi

# ─── 3. Meta-dados ───────────────────────────────────────────────────────────
TIMESTAMP=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
BRANCH=$(git branch --show-current 2>/dev/null || echo 'N/A')
COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo 'N/A')
COMMIT_MSG=$(git log -1 --pretty=format:'%s' 2>/dev/null || echo 'N/A')

cat > "$RESULTS/meta.json" <<EOF
{
  "timestamp": "$TIMESTAMP",
  "branch": "$BRANCH",
  "commit": "$COMMIT",
  "commit_message": "$COMMIT_MSG",
  "base_url": "$BASE_URL",
  "phpunit_ok": $( [ "$PHPUNIT_OK" = true ] && echo 'true' || echo 'false' ),
  "e2e_ok": $( [ "$E2E_OK" = true ] && echo 'true' || echo 'false' ),
  "unit_only": $( [ "$UNIT_ONLY" = true ] && echo 'true' || echo 'false' ),
  "e2e_only": $( [ "$E2E_ONLY" = true ] && echo 'true' || echo 'false' )
}
EOF

echo ""
echo "========================================================"
echo "  Resultados salvos em tests/results/"
if [ "$E2E_ONLY" = false ]; then
  [ "$PHPUNIT_OK" = true ] && echo "  ✓ PHPUnit: $PHPUNIT_PASSED/$PHPUNIT_TOTAL" || echo "  ✗ PHPUnit: falhou"
fi
if [ "$UNIT_ONLY" = false ]; then
  [ "$E2E_OK" = true ] && echo "  ✓ Playwright: $E2E_PASSED/$E2E_TOTAL" || echo "  ✗ Playwright: falhou"
fi
echo ""
echo "  Acesse o relatório em:"
echo "  https://sematst.protocolosead.com/admin/testes.php"
echo "========================================================"
echo ""

# Retorna código de saída não-zero se algum teste falhou
[ "$PHPUNIT_OK" = true ] && [ "$E2E_OK" = true ] && exit 0 || exit 1
