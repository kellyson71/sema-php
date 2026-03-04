<?php
require_once '../conexao.php';
verificaLogin();

// O login já foi validado na linha acima pelo verificaLogin()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conteudo = trim($_POST['conteudo_parecer'] ?? '');
    $requerimento_id = trim($_POST['requerimento_id'] ?? '');

    if (empty($conteudo)) {
        die("O conteúdo do parecer não pode estar vazio.");
    }

    // Buscar dados do administrador logado
    $admin_id = $_SESSION['admin_id'];
    $stmt = $pdo->prepare("SELECT nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if (!$admin) {
        die("Erro ao buscar dados do assinante.");
    }

    $assinante = [
        'nome' => $admin['nome_completo'] ?: $_SESSION['admin_nome'],
        'cargo' => $admin['cargo'] ?: 'Administrador(a)',
        'data_hora' => date('d/m/Y H:i:s')
    ];

    $numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

    // Registrar assinatura no banco, se necessário (tabela assinaturas_digitais)
    // $stmt_insert = $pdo->prepare("INSERT INTO assinaturas_digitais (codigo_verificacao, requerimento_id, admin_id, tipo_assinatura, ...) VALUES (...)");
    
    // Repassar dados para a geração de PDF
    require_once 'gerar_pdf.php';
    
    // Exemplo: chamando função que estará dentro do gerar_pdf.php
    emitirParecerAssinado($conteudo, $assinante, $numero_processo);
    exit;
} else {
    // Redireciona via get
    header("Location: ../index.php");
    exit;
}
