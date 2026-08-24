<?php

/**
 * Observação interna: uma nota por requerimento, só visível à equipe.
 * Sobrescrita a cada edição (mesmo padrão de buscarPagamentoRequerimento()).
 */
function buscarNotaInterna(PDO $pdo, int $requerimentoId): ?array
{
    $stmt = $pdo->prepare("
        SELECT n.*, a.nome AS admin_nome
        FROM requerimento_notas_internas n
        LEFT JOIN administradores a ON a.id = n.admin_id
        WHERE n.requerimento_id = ?
        LIMIT 1
    ");
    $stmt->execute([$requerimentoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function salvarNotaInterna(PDO $pdo, int $requerimentoId, string $texto, ?int $adminId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO requerimento_notas_internas (requerimento_id, texto, admin_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE texto = VALUES(texto), admin_id = VALUES(admin_id), atualizado_em = NOW()
    ");
    $stmt->execute([$requerimentoId, $texto, $adminId]);
}
