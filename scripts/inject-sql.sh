#!/usr/bin/env bash
# Injeta um arquivo .sql no banco de dados do container MariaDB.
#
# Uso:
#   ./scripts/inject-sql.sh <arquivo.sql>
#   ./scripts/inject-sql.sh database/u492577848_SEMA.sql
#
# Se nenhum arquivo for passado, usa database/u492577848_SEMA.sql por padrão.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

SQL_FILE="${1:-database/u492577848_SEMA.sql}"

if [ ! -f "$SQL_FILE" ]; then
    echo "[ERRO] Arquivo SQL não encontrado: $SQL_FILE"
    exit 1
fi

# Verifica se o container está rodando
if ! docker compose ps db | grep -q "running"; then
    echo "[ERRO] Container 'db' não está rodando. Execute ./scripts/start.sh primeiro."
    exit 1
fi

echo "[INFO] Injetando '$SQL_FILE' no banco 'u492577848_SEMA'..."

docker compose exec -T db mysql \
    -u root \
    -proot \
    u492577848_SEMA < "$SQL_FILE"

echo "✔  SQL injetado com sucesso!"
