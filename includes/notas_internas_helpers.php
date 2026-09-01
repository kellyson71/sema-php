<?php

/**
 * Observações internas: chat/feed de anotações da equipe por requerimento,
 * visíveis exclusivamente aos administradores e operadores internos.
 */

function buscarNotasInternas(PDO $pdo, int $requerimentoId): array
{
    $stmt = $pdo->prepare("
        SELECT n.*,
               COALESCE(NULLIF(a.nome_completo, ''), a.nome) AS admin_nome,
               a.cargo AS admin_cargo,
               a.nivel AS admin_nivel,
               a.email AS admin_email
        FROM requerimento_notas_internas n
        LEFT JOIN administradores a ON a.id = n.admin_id
        WHERE n.requerimento_id = ?
        ORDER BY n.criado_em ASC, n.id ASC
    ");
    $stmt->execute([$requerimentoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarNotaInterna(PDO $pdo, int $requerimentoId): ?array
{
    $stmt = $pdo->prepare("
        SELECT n.*,
               COALESCE(NULLIF(a.nome_completo, ''), a.nome) AS admin_nome,
               a.cargo AS admin_cargo,
               a.nivel AS admin_nivel
        FROM requerimento_notas_internas n
        LEFT JOIN administradores a ON a.id = n.admin_id
        WHERE n.requerimento_id = ?
        ORDER BY n.criado_em DESC, n.id DESC
        LIMIT 1
    ");
    $stmt->execute([$requerimentoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
function adicionarNotaInterna(PDO $pdo, int $requerimentoId, string $texto, ?int $adminId): int
{
    $stmt = $pdo->prepare("
        INSERT INTO requerimento_notas_internas (requerimento_id, texto, admin_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$requerimentoId, $texto, $adminId]);
    return (int) $pdo->lastInsertId();
}

function salvarNotaInterna(PDO $pdo, int $requerimentoId, string $texto, ?int $adminId): void
{
    adicionarNotaInterna($pdo, $requerimentoId, $texto, $adminId);
}

function excluirNotaInterna(PDO $pdo, int $notaId, int $requerimentoId, int $adminId, bool $isAdminGeral = false): bool
{
    if ($isAdminGeral) {
        $stmt = $pdo->prepare("DELETE FROM requerimento_notas_internas WHERE id = ? AND requerimento_id = ?");
        $stmt->execute([$notaId, $requerimentoId]);
        return $stmt->rowCount() > 0;
    }

    $stmt = $pdo->prepare("DELETE FROM requerimento_notas_internas WHERE id = ? AND requerimento_id = ? AND admin_id = ?");
    $stmt->execute([$notaId, $requerimentoId, $adminId]);
    return $stmt->rowCount() > 0;
}
