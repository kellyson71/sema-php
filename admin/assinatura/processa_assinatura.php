<?php
require_once '../conexao.php';
verificaLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

$requerimento_id = filter_input(INPUT_POST, 'requerimento_id', FILTER_VALIDATE_INT);
$conteudo_documento = trim($_POST['conteudo_documento'] ?? '');

if (!$requerimento_id || empty($conteudo_documento)) {
    die("Dados incompletos para assinatura.");
}

try {
    // Buscar informações relevantes do requerimento
    $stmt = $pdo->prepare("SELECT status FROM requerimentos WHERE id = ?");
    $stmt->execute([$requerimento_id]);
    $req = $stmt->fetch();

    if (!$req) {
        die("Requerimento não encontrado.");
    }

    // Gerar historico
    $acao = "Parecer Técnico/Documento assinado digitalmente e gerado em PDF.";
    $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao, data_acao) VALUES (?, ?, ?, NOW())");
    $stmtHist->execute([$_SESSION['admin_id'], $requerimento_id, $acao]);

    // Atualiza status se for novo ou devolvido
    if (in_array($req['status'], ['Novo', 'Devolvido', 'Aguardando Correção'])) {
        $stmtStatus = $pdo->prepare("UPDATE requerimentos SET status = 'Em análise' WHERE id = ?");
        $stmtStatus->execute([$requerimento_id]);
        
        $acaoStatus = "Status alterado para Em análise";
        $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao, data_acao) VALUES (?, ?, ?, NOW())");
        $stmtHist->execute([$_SESSION['admin_id'], $requerimento_id, $acaoStatus]);
    }

    // Salvar o conteúdo na base é opcional, mas vamos forçar o PDF
    // Chamar o gerador passando as variáveis necessárias
    require_once 'gerar_pdf.php';

} catch (Exception $e) {
    die("Erro ao processar assinatura: " . $e->getMessage());
}
