#!/bin/bash
# install.sh — Instala dependências do serviço PDF no servidor Hostinger
#
# Uso:
#   cd ~/domains/sema.protocolosead.com/public_html/services/pdf
#   bash install.sh
#
# Pré-requisitos:
#   - Node.js disponível em /opt/alt/alt-nodejs20/

set -e

NODE_BIN="/opt/alt/alt-nodejs20/root/usr/bin/node"
NPM_BIN="/opt/alt/alt-nodejs20/root/usr/bin/npm"

# Detectar Node.js
if [ ! -x "$NODE_BIN" ]; then
    echo "ERRO: Node.js não encontrado em $NODE_BIN"
    echo "Tentando alternativas..."
    for v in 22 18 24; do
        ALT="/opt/alt/alt-nodejs${v}/root/usr/bin/node"
        if [ -x "$ALT" ]; then
            NODE_BIN="$ALT"
            NPM_BIN="${ALT%node}npm"
            echo "Encontrado: $NODE_BIN"
            break
        fi
    done
fi

echo "=== Instalando dependências do serviço PDF ==="
echo "Node.js: $($NODE_BIN --version)"
echo "npm: $($NPM_BIN --version)"

cd "$(dirname "$0")"

# Instalar Puppeteer (baixa Chromium automaticamente)
$NPM_BIN install --production

echo ""
echo "=== Instalação concluída ==="
echo ""
echo "Testar geração de PDF:"
echo "  echo '<h1>Teste</h1>' | $NODE_BIN generate.js - /tmp/teste.pdf"
echo "  ls -la /tmp/teste.pdf"
echo ""
echo "Iniciar microserviço (opcional):"
echo "  $NODE_BIN server.js &"
echo ""
