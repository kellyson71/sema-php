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
    
    emitirParecerAssinado($conteudo, $assinante, $numero_processo);
    exit;
} else {
    header("Location: ../index.php");
    exit;
}
