<?php
require_once 'conexao.php';

try {
    // Modify 'nivel' ENUM in 'administradores'
    $pdo->exec("ALTER TABLE administradores MODIFY COLUMN nivel ENUM('admin', 'operador', 'secretario', 'analista', 'fiscal') NOT NULL DEFAULT 'operador'");
    echo "Coluna nivel atualizada.\n";
} catch (PDOException $e) {
    echo "Erro nivel: " . $e->getMessage() . "\n";
}

try {
    // Modify 'status' ENUM in 'requerimentos' to include 'Aguardando Fiscalização'
    $pdo->exec("ALTER TABLE requerimentos MODIFY COLUMN status ENUM('Pendente', 'Em análise', 'Aguardando Fiscalização', 'Aprovado', 'Reprovado', 'Cancelado', 'Indeferido', 'Finalizado', 'Apto a gerar alvará', 'Alvará Emitido') NOT NULL DEFAULT 'Pendente'");
    echo "Coluna status atualizada.\n";
} catch (PDOException $e) {
    echo "Erro status: " . $e->getMessage() . "\n";
}

// Modify requerimentos_arquivados just in case it exists and has the same ENUM
try {
    $pdo->exec("ALTER TABLE requerimentos_arquivados MODIFY COLUMN status ENUM('Pendente', 'Em análise', 'Aguardando Fiscalização', 'Aprovado', 'Reprovado', 'Cancelado', 'Indeferido', 'Finalizado', 'Apto a gerar alvará', 'Alvará Emitido') NOT NULL DEFAULT 'Pendente'");
    echo "Coluna status (arquivados) atualizada.\n";
} catch (PDOException $e) {
    echo "Erro status (arquivados): " . $e->getMessage() . "\n";
}
