#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "[INFO] Subindo containers..."
docker compose up -d --build

# Aguarda o banco ficar pronto
echo "[INFO] Aguardando banco de dados ficar pronto..."
until docker compose exec -T db mysqladmin ping -u root -proot --silent 2>/dev/null; do
    sleep 2
done

echo ""
echo "✔  Serviços disponíveis:"
echo "   Aplicação PHP → http://localhost:8090"
echo "   phpMyAdmin    → http://localhost:8091"
echo "   MariaDB       → localhost:3307  (user: root / pass: root)"
echo ""
echo "   Banco de dados: u492577848_SEMA"
echo ""

# Abre no navegador (detecta Linux/macOS/WSL)
open_browser() {
    if command -v xdg-open &>/dev/null; then
        xdg-open "$1"
    elif command -v open &>/dev/null; then
        open "$1"
    elif command -v wslview &>/dev/null; then
        wslview "$1"
    fi
}

echo "[INFO] Abrindo no navegador..."
open_browser "http://localhost:8090"
sleep 1
open_browser "http://localhost:8091"
