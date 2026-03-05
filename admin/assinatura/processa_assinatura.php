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
    $salvar_banco = filter_var($_POST['salvar_banco'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $template_salvo = $_POST['template_salvo'] ?? 'Documento Eletrônico';

    if (empty($conteudo)) {
        if ($salvar_banco) {
            echo json_encode(['success' => false, 'error' => 'ERRO: O conteúdo do parecer não pode estar vazio.']); exit;
        }
        die("ERRO: O conteúdo do parecer não pode estar vazio.");
    }

    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        if ($salvar_banco) {
            echo json_encode(['success' => false, 'error' => 'ERRO: Sessão expirada ou não encontrada.']); exit;
        }
        die("ERRO: Sessão expirada ou não encontrada.");
    }

    try {
        $stmt = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
             if ($salvar_banco) { echo json_encode(['success' => false, 'error' => 'Administrador não encontrado.']); exit; }
             die("ERRO: Administrador não encontrado no banco.");
        }
    } catch (Exception $e) {
        if ($salvar_banco) { echo json_encode(['success' => false, 'error' => 'ERRO SQL: ' . $e->getMessage()]); exit; }
        die("ERRO SQL: " . $e->getMessage());
    }

    // Preparar dados do assinante para o Carimbo TCPDF
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
    
    if ($salvar_banco && $requerimento_id) {
        try {
            // Diretório de Salvamento
            $dirDestino = dirname(__DIR__) . '/pareceres/' . $requerimento_id;
            if (!is_dir($dirDestino)) {
                mkdir($dirDestino, 0755, true);
            }
            
            $nomeArquivoBase = 'Parecer_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $numero_processo) . '_' . date('His') . '.pdf';
            $caminhoFisico = $dirDestino . '/' . $nomeArquivoBase;
            $caminhoRelativo = 'pareceres/' . $requerimento_id . '/' . $nomeArquivoBase;
            
            // 1. Gerar e salvar fisicamente o PDF no disco "F"
            emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'F', $caminhoFisico);
            
            if (!file_exists($caminhoFisico)) {
                echo json_encode(['success' => false, 'error' => 'A biblioteca PDF falhou ao gravar o arquivo físico.']); exit;
            }
            
            // 2. Coletar Metadados p/ Tabela
            $documentoId = uniqid('doc_');
            $hashDocumento = hash_file('sha256', $caminhoFisico);
            $nomeCurto_template = preg_replace('/\.html$/i', '', $template_salvo); // limpa .html caso chegue
            
            // 3. Persistência de Banco de Dados
            $stmt = $pdo->prepare("
                INSERT INTO assinaturas_digitais (
                    documento_id, requerimento_id, tipo_documento, nome_arquivo,
                    caminho_arquivo, hash_documento, assinante_id, assinante_nome,
                    assinante_cpf, assinante_cargo, tipo_assinatura, assinatura_visual,
                    assinatura_criptografada, timestamp_assinatura, ip_assinante,
                    conteudo_html
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            
            $stmt->execute([
                $documentoId,
                $requerimento_id,
                $nomeCurto_template,
                $nomeArquivoBase,
                $caminhoRelativo,
                $hashDocumento,
                $admin_id,
                $assinante['nome'],
                $assinante['cpf'],
                $assinante['cargo'],
                'digital_sema',
                '{}',
                hash('sha256', $documentoId . time() . $admin_id),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $conteudo  // HTML fonte para re-geração futura
            ]);

            
            // 4. Update Histórico de Protocolo
            $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmtHist->execute([
                $admin_id,
                $requerimento_id,
                "Gerou e assinou digitalmente o documento: " . strtoupper(str_replace('_', ' ', $nomeCurto_template))
            ]);
            
            echo json_encode([
                 'success' => true,
                 'url_pdf' => $caminhoRelativo,
                 'nome_arquivo' => $nomeArquivoBase,
                 'documento_id' => $documentoId
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Erro em processa_assinatura no fluxo JSON -> " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Falha Crítica ao registrar documento: ' . $e->getMessage()]); exit;
        }

    } else {
        // Fluxo Antigo Direto (Força Download no Navegador)
        emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'D');
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}
