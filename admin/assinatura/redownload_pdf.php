<?php
/**
 * redownload_pdf.php
 *
 * Re-download / re-geração de PDFs assinados.
 *
 * GET ?id=DOCUMENTO_ID
 *   → Busca o registro em assinaturas_digitais
 *   → Lê o arquivo HTML salvo em disco (caminho_arquivo)
 *   → Re-gera o PDF assinado via emitirParecerAssinado()
 *   → Força download no navegador
 */

$rootDir = dirname(__DIR__, 2); // raiz (sema-php)
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php'; // admin/conexao.php

if (function_exists('verificaLogin')) {
    verificaLogin();
}

$documentoId = trim($_GET['id'] ?? '');
if (empty($documentoId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID do documento não informado.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM assinaturas_digitais 
        WHERE documento_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$documentoId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Documento não encontrado.']);
        exit;
    }

    // ── Resolver o caminho físico do HTML ──────────────────────────────────────
    // caminho_arquivo pode ser absoluto (ex: /home/u49.../pareceres/...)
    // ou relativo (ex: ../uploads/pareceres/123/arquivo.html)
    $caminho = $doc['caminho_arquivo'];
    
    // Tentar como caminho absoluto primeiro
    if (file_exists($caminho)) {
        $caminhoHtml = $caminho;
    } else {
        // Tentar relativo à raiz do projeto
        $caminhoHtml = $rootDir . '/' . ltrim($caminho, '/');
        if (!file_exists($caminhoHtml)) {
            // Tentar relativo à pasta admin
            $caminhoHtml = dirname(__DIR__) . '/' . ltrim($caminho, '/');
        }
    }

    if (!file_exists($caminhoHtml)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Arquivo HTML do documento não encontrado no servidor. Pode ter sido removido.',
            'caminho' => $caminho
        ]);
        exit;
    }

    // Verifica se o arquivo gravado no disco já é um PDF
    $ext = strtolower(pathinfo($caminhoHtml, PATHINFO_EXTENSION));
    
    if ($ext === 'pdf') {
        $isInline = isset($_GET['inline']) && $_GET['inline'] == '1';
        $disposition = $isInline ? 'inline' : 'attachment';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($caminhoHtml) . '"');
        header('Content-Length: ' . filesize($caminhoHtml));
        header('Accept-Ranges: bytes');
        @readfile($caminhoHtml);
        exit;
    }

    // Se não for PDF, é HTML. Ler o conteúdo para gerar o PDF dinamicamente
    $conteudoHtml = file_get_contents($caminhoHtml);

    if ($conteudoHtml === false || trim($conteudoHtml) === '') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Não foi possível ler o arquivo HTML.']);
        exit;
    }

    // Dados do assinante salvos no banco
    $assinante = [
        'nome'      => $doc['assinante_nome'],
        'cargo'     => $doc['assinante_cargo'],
        'cpf'       => $doc['assinante_cpf'],
        'matricula' => '',
        'data_hora' => date('d/m/Y \à\s H:i:s', strtotime($doc['timestamp_assinatura']))
    ];

    $numero_processo = $doc['requerimento_id']
        ? "Processo_#{$doc['requerimento_id']}"
        : 'Documento_Avulso';

    // Re-gerar e forçar download do PDF
    require_once __DIR__ . '/gerar_pdf.php';
    emitirParecerAssinado($conteudoHtml, $assinante, $numero_processo, 'D');
    exit;

} catch (Exception $e) {
    error_log('[redownload_pdf] Erro: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erro ao gerar o download: ' . $e->getMessage()]);
    exit;
}
