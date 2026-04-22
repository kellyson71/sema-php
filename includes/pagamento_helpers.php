<?php

require_once __DIR__ . '/functions.php';

/**
 * Busca os dados de pagamento do requerimento.
 */
function buscarPagamentoRequerimento(PDO $pdo, int $requerimentoId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM requerimento_pagamentos WHERE requerimento_id = ? LIMIT 1");
    $stmt->execute([$requerimentoId]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ?: null;
}

/**
 * Busca o documento mais recente de um campo específico.
 */
function buscarDocumentoPorCampo(PDO $pdo, int $requerimentoId, string $campoFormulario): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM documentos
        WHERE requerimento_id = ? AND campo_formulario = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$requerimentoId, $campoFormulario]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ?: null;
}

/**
 * Remove documentos anteriores de um campo específico.
 */
function removerDocumentoPorCampo(PDO $pdo, int $requerimentoId, string $campoFormulario): void
{
    $stmt = $pdo->prepare("SELECT id, caminho FROM documentos WHERE requerimento_id = ? AND campo_formulario = ?");
    $stmt->execute([$requerimentoId, $campoFormulario]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($documentos as $documento) {
        $caminho = dirname(__DIR__) . '/uploads/' . ltrim($documento['caminho'], '/\\');
        if (!empty($documento['caminho']) && file_exists($caminho)) {
            @unlink($caminho);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM documentos WHERE requerimento_id = ? AND campo_formulario = ?");
    $stmt->execute([$requerimentoId, $campoFormulario]);
}

/**
 * Salva um PDF de pagamento no diretório do protocolo e registra em documentos.
 *
 * @return array|false
 */
function salvarDocumentoPagamento(PDO $pdo, int $requerimentoId, string $protocolo, array $arquivo, string $campoFormulario)
{
    $diretorioUpload = rtrim(UPLOAD_DIR, '/\\') . '/' . $protocolo;
    $arquivoInfo = salvarArquivo($arquivo, $diretorioUpload, $campoFormulario);

    if (!$arquivoInfo) {
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO documentos (requerimento_id, campo_formulario, nome_original, nome_salvo, caminho, tipo_arquivo, tamanho)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $requerimentoId,
        $campoFormulario,
        $arquivoInfo['nome_original'],
        $arquivoInfo['nome_salvo'],
        $arquivoInfo['caminho'],
        $arquivoInfo['tipo'],
        $arquivoInfo['tamanho'],
    ]);

    $arquivoInfo['documento_id'] = (int) $pdo->lastInsertId();

    return $arquivoInfo;
}

/**
 * Constrói a URL pública de um arquivo salvo em uploads.
 */
function urlPublicaUpload(string $caminhoRelativo): string
{
    return './uploads/' . ltrim(str_replace('\\', '/', $caminhoRelativo), '/');
}
