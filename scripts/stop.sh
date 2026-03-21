#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "[INFO] Parando containers..."
docker compose down

echo "✔  Containers parados. Os dados do banco foram preservados no volume 'sema_db_data'."
echo ""
echo "   Portas liberadas: 8090 (web), 8091 (phpMyAdmin), 3307 (MariaDB)"
echo ""
echo "   Para parar E apagar os dados do banco:"
echo "   docker compose down -v"
