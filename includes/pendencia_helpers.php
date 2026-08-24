<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pagamento_helpers.php';

/**
 * Prefixo de campo_formulario usado pelos anexos de complementação.
 * Ex.: pendencia_12 → anexos da pendência 12.
 */
function campoFormularioPendencia(int $pendenciaId): string
{
    return 'pendencia_' . $pendenciaId;
}

function buscarPendencia(PDO $pdo, int $pendenciaId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM requerimento_pendencias WHERE id = ? LIMIT 1");
    $stmt->execute([$pendenciaId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Todas as pendências de um requerimento, da mais recente para a mais antiga.
 */
function listarPendenciasRequerimento(PDO $pdo, int $requerimentoId): array
{
    $stmt = $pdo->prepare("
        SELECT p.*, a.nome AS admin_nome
        FROM requerimento_pendencias p
        LEFT JOIN administradores a ON a.id = p.admin_id
        WHERE p.requerimento_id = ?
        ORDER BY p.criado_em DESC, p.id DESC
    ");
    $stmt->execute([$requerimentoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Anexos enviados pelo cidadão em resposta a uma pendência.
 */
function listarAnexosPendencia(PDO $pdo, int $requerimentoId, int $pendenciaId): array
{
    $stmt = $pdo->prepare("
        SELECT * FROM documentos
        WHERE requerimento_id = ? AND campo_formulario = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$requerimentoId, campoFormularioPendencia($pendenciaId)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marca uma pendência aberta ou respondida como aceita/resolvida.
 * $manual = true quando a equipe resolveu por fora (telefone, presencial etc.),
 * sem resposta formal do requerente pelo link.
 */
function resolverPendencia(PDO $pdo, int $pendenciaId, bool $manual = false): void
{
    $stmt = $pdo->prepare("
        UPDATE requerimento_pendencias
        SET status = 'aceita', decidido_em = NOW(), resolvido_manualmente = ?
        WHERE id = ?
    ");
    $stmt->execute([$manual ? 1 : 0, $pendenciaId]);
}

/**
 * Reabre uma complementação: cria uma nova pendência a partir da anterior
 * (mesmo título, nova descrição) para o requerente ser cobrado de novo,
 * mantendo o rastro de qual pendência originou o pedido repetido.
 */
function reabrirPendencia(PDO $pdo, int $pendenciaAnteriorId, string $titulo, string $descricao, ?int $adminId): int
{
    $stmt = $pdo->prepare("
        INSERT INTO requerimento_pendencias (requerimento_id, titulo, descricao, admin_id, reaberta_de_id)
        SELECT requerimento_id, ?, ?, ?, id FROM requerimento_pendencias WHERE id = ?
    ");
    $stmt->execute([$titulo, $descricao, $adminId, $pendenciaAnteriorId]);
    return (int) $pdo->lastInsertId();
}

/**
 * Normaliza $_FILES['anexos[]'] (formato colunar do PHP) numa lista de arquivos
 * individuais no formato que salvarArquivo() espera. Ignora slots vazios.
 *
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function normalizarUploadMultiplo(?array $files): array
{
    if (!$files || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $arquivos = [];
    foreach ($files['name'] as $i => $nome) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $arquivos[] = [
            'name'     => $nome,
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }
    return $arquivos;
}
