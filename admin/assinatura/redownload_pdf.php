<?php
/**
 * redownload_pdf.php
 * 
 * Endpoint para re-download / re-geração de PDFs assinados.
 * 
 * GET ?id=DOCUMENTO_ID
 *   → Busca em assinaturas_digitais
 *   → Se o arquivo físico existe no disco: serve diretamente
 *   → Se não existe mas há conteudo_html: re-gera o PDF na hora
 */

$rootDir = dirname(__DIR__, 2); // raiz (sema-php)
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php'; // admin/conexao.php

if (function_exists('verificaLogin')) {
    verificaLogin();
}

header('Content-Type: application/json');

$documentoId = trim($_GET['id'] ?? '');
if (empty($documentoId)) {
    echo json_encode(['success' => false, 'error' => 'ID do documento não informado.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT *, 
               CONCAT(nome_arquivo) as nome_arquivo_limpo
        FROM assinaturas_digitais 
        WHERE documento_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$documentoId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        echo json_encode(['success' => false, 'error' => 'Documento não encontrado.']);
        exit;
    }

    $caminhoFisico = dirname(__DIR__) . '/' . ltrim($doc['caminho_arquivo'], '/\\');

    // ── Caso 1: PDF ainda está no disco ──
    if (file_exists($caminhoFisico)) {
        $nomeArquivo = basename($caminhoFisico);

        // Servir o arquivo diretamente
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Content-Length: ' . filesize($caminhoFisico));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($caminhoFisico);
        exit;
    }

    // ── Caso 2: PDF não existe mais, mas temos o HTML ──
    if (empty($doc['conteudo_html'])) {
        echo json_encode([
            'success' => false,
            'error'   => 'O arquivo PDF não foi encontrado no servidor e não há conteúdo HTML salvo para re-geração. Documento possivelmente perdido.'
        ]);
        exit;
    }

    // Reconstruir dados do assinante a partir do que foi salvo
    $assinante = [
        'nome'      => $doc['assinante_nome'],
        'cargo'     => $doc['assinante_cargo'],
        'cpf'       => $doc['assinante_cpf'],
        'matricula' => '',
        'data_hora' => date('d/m/Y \à\s H:i:s', strtotime($doc['timestamp_assinatura']))
    ];

    $numero_processo = $doc['requerimento_id'] ? "Processo_#{$doc['requerimento_id']}" : "Documento_Avulso";

    // Re-criar diretório se necessário
    $dirDestino = dirname($caminhoFisico);
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    // Re-gerar PDF
    require_once __DIR__ . '/gerar_pdf.php';
    emitirParecerAssinado($doc['conteudo_html'], $assinante, $numero_processo, 'D');
    exit;

} catch (Exception $e) {
    error_log('[redownload_pdf] Erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar o download: ' . $e->getMessage()]);
    exit;
}
