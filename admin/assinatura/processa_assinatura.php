<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexão e Sessão (Caminhos Absolutos a partir da raiz)
$rootDir = dirname(__DIR__, 2); // Raiz (sema-php)
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php'; // admin/conexao.php

// Validar login
if (function_exists('verificaLogin')) {
    verificaLogin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conteudo = trim($_POST['conteudo_parecer'] ?? '');
    $requerimento_id = trim($_POST['requerimento_id'] ?? '');

    if (empty($conteudo)) {
        die("ERRO: O conteúdo do parecer não pode estar vazio.");
    }

    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        die("ERRO: Sessão expirada ou não encontrada.");
    }

    try {
        $stmt = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
             die("ERRO: Administrador não encontrado no banco.");
        }
    } catch (Exception $e) {
        die("ERRO SQL: " . $e->getMessage());
    }

    // Preparar dados do assinante
    $assinante = [
        'nome' => ($admin['nome_completo'] ?: ($admin['nome'] ?: $_SESSION['admin_nome'])),
        'cargo' => ($admin['cargo'] ?: 'Administrador(a)'),
        'cpf' => ($admin['cpf'] ?? ''),
        'matricula' => ($admin['matricula_portaria'] ?? ''),
        'data_hora' => date('d/m/Y \à\s H:i:s')
    ];

    $numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

    // Requerer a classe TCPDF estendida
    require_once __DIR__ . '/gerar_pdf.php';
    require_once $rootDir . '/includes/assinatura_digital_service.php';
    
    // 1. Gerar e Salvar o PDF no disco
    $resultadoPdf = emitirParecerAssinado($conteudo, $assinante, $numero_processo, $requerimento_id);
    
    if (!$resultadoPdf || !isset($resultadoPdf['caminho_absoluto'])) {
         die("ERRO: Falha ao gerar arquivo PDF.");
    }
    
    // 2. Gerar Hash do Arquivo Salvo
    $hashFinal = hash_file('sha256', $resultadoPdf['caminho_absoluto']);
    $assinaturaService = new AssinaturaDigitalService($pdo);
    $assinaturaCriptografada = $assinaturaService->assinarHash($hashFinal);
    $documentoId = bin2hex(random_bytes(32));

    // 3. Salvar no Banco de Dados (Metadados da Assinatura)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO assinaturas_digitais (
                documento_id, requerimento_id, tipo_documento, nome_arquivo,
                caminho_arquivo, hash_documento, assinante_id, assinante_nome,
                assinante_cpf, assinante_cargo, tipo_assinatura, assinatura_visual,
                assinatura_criptografada, timestamp_assinatura, ip_assinante
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
            
        $stmt->execute([
            $documentoId,
            $requerimento_id ?: null,
            'parecer',
            $resultadoPdf['nome_arquivo'],
            $resultadoPdf['caminho_absoluto'], // Caminho completo no servidor
            $hashFinal,
            $admin_id,
            $assinante['nome'],
            $assinante['cpf'],
            $assinante['cargo'],
            'govbr', // Padrão
            json_encode(['texto' => $assinante['nome'] . ' - Emitido eletronicamente']),
            $assinaturaCriptografada,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Registrar no histórico se estiver atrelado a um requerimento
        if ($requerimento_id) {
            $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmtHist->execute([
                $admin_id,
                $requerimento_id,
                "Assinou digitalmente um parecer através do editor unificado (ID: {$documentoId})"
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao salvar assinatura_digital: " . $e->getMessage());
        // Mesmo com erro de banco provisório, o arquivo já está no servidor. Mas o ideal é notificar.
    }

    // 4. Forçar o Download para o Usuário
    if (file_exists($resultadoPdf['caminho_absoluto'])) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($resultadoPdf['nome_arquivo']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($resultadoPdf['caminho_absoluto']));
        readfile($resultadoPdf['caminho_absoluto']);
        exit;
    } else {
        die("ERRO: O arquivo foi gerado mas não pôde ser lido para download.");
    }

} else {
    header("Location: ../index.php");
    exit;
}
