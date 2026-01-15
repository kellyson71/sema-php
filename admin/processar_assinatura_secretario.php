<?php
require_once 'conexao.php';
verificaLogin();

// Verificar permissão
if (!($_SESSION['admin_nivel'] === 'secretario' || $_SESSION['admin_email'] === 'secretario@sema.rn.gov.br')) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: secretario_dashboard.php");
    exit;
}

$id = isset($_POST['requerimento_id']) ? (int)$_POST['requerimento_id'] : 0;
$acao = isset($_POST['acao']) ? $_POST['acao'] : '';
$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : '';

if (!$id) {
    die("ID inválido");
}

try {
    if ($acao === 'aprovar') {
        // 1. Buscar assinatura anterior para pegar o mesmo caminho de arquivo e ID do documento
        $stmtDoc = $pdo->prepare("SELECT * FROM assinaturas_digitais WHERE requerimento_id = ? ORDER BY timestamp_assinatura DESC LIMIT 1");
        $stmtDoc->execute([$id]);
        $docAnterior = $stmtDoc->fetch();

        if (!$docAnterior) {
            die("Documento original não encontrado para cont assinatura.");
        }

        $pdo->beginTransaction();

        // 2. Inserir Assinatura do Secretário
        // Usamos os mesmos dados criptográficos do documento original pois é o mesmo arquivo
        $stmtAss = $pdo->prepare("INSERT INTO assinaturas_digitais (
            documento_id, requerimento_id, tipo_documento, nome_arquivo, caminho_arquivo, 
            hash_documento, assinante_id, assinante_nome, assinante_cpf, assinante_cargo, 
            tipo_assinatura, assinatura_criptografada, timestamp_assinatura, ip_assinante
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        
        $stmtAss->execute([
            $docAnterior['documento_id'], // Mantém o MESMO ID de documento
            $id,
            $docAnterior['tipo_documento'] ?? 'parecer', // Usa o tipo original ou 'parecer'
            $docAnterior['nome_arquivo'], // Nome do arquivo (obrigatório)
            $docAnterior['caminho_arquivo'],
            $docAnterior['hash_documento'], // Hash do documento original
            $_SESSION['admin_id'],
            $_SESSION['admin_nome'],
            null, // CPF (permite NULL)
            'Secretário Municipal de Meio Ambiente',
            'texto',
            $docAnterior['assinatura_criptografada'], // Assinatura do hash (sistema)
            $_SERVER['REMOTE_ADDR']
        ]);

        // 3. Atualizar Status do Requerimento
        $stmtUpdate = $pdo->prepare("UPDATE requerimentos SET status = 'Alvará Emitido', data_atualizacao = NOW() WHERE id = ?");
        $stmtUpdate->execute([$id]);

        // 4. Registrar Histórico
        $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao, data_acao) VALUES (?, ?, ?, NOW())");
        $stmtHist->execute([
            $_SESSION['admin_id'], 
            $id, 
            "Aprovou e Assinou o Alvará (Secretário)"
        ]);

        $pdo->commit();

        header("Location: secretario_dashboard.php?msg=sucesso_assinatura");
        exit;

    } elseif ($acao === 'corrigir') {
        $pdo->beginTransaction();

        // Volta status para Em análise (ou Pendente, conforme fluxo)
        $novoStatus = 'Em análise';
        $obsFinal = "DEVOLVIDO PELO SECRETÁRIO: " . $observacao;

        $stmtUpdate = $pdo->prepare("UPDATE requerimentos SET status = ?, observacoes = ?, data_atualizacao = NOW() WHERE id = ?");
        $stmtUpdate->execute([$novoStatus, $obsFinal, $id]);

        // Histórico
        $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao, data_acao) VALUES (?, ?, ?, NOW())");
        $stmtHist->execute([
            $_SESSION['admin_id'], 
            $id, 
            "Devolveu para correção: " . $observacao
        ]);

        $pdo->commit();

        header("Location: secretario_dashboard.php?msg=devolvido");
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erro ao processar: " . $e->getMessage());
}
