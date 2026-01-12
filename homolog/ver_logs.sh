#!/bin/bash
echo "=== Últimas 100 linhas do log de erros PHP ==="
tail -n 100 /opt/lampp/logs/php_error_log | grep -A 5 -B 5 "prepararTemplateA4ParaPdf\|HANDLER\|PDF"

