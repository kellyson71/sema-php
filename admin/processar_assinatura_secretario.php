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
        // 1. Buscar TODOS os documentos técnicos associados a este requerimento
        // Agrupando por nome do arquivo para garantir que assinamos cada arquivo físico uma única vez
        $stmtDoc = $pdo->prepare("SELECT * FROM assinaturas_digitais 
                                  WHERE requerimento_id = ? 
                                  GROUP BY nome_arquivo 
                                  ORDER BY timestamp_assinatura DESC");
        $stmtDoc->execute([$id]);
        $documentosAnteriores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

        if (empty($documentosAnteriores)) {
            die("Nenhum documento original encontrado para assinatura.");
        }

        $pdo->beginTransaction();

        require_once '../includes/assinatura_digital_service.php';
        $assinaturaService = new AssinaturaDigitalService($pdo);

        // 2. Inserir Assinatura do Secretário para CADA documento encontrado
        foreach ($documentosAnteriores as $docAnterior) {
            
            // Gerar novo ID único para esta assinatura
            $novoDocumentoId = bin2hex(random_bytes(32));
            
            // Reassinar o hash do documento original
            $novaAssinaturaCriptografada = $assinaturaService->assinarHash($docAnterior['hash_documento']);

            $stmtAss = $pdo->prepare("INSERT INTO assinaturas_digitais (
                documento_id, requerimento_id, tipo_documento, nome_arquivo, caminho_arquivo, 
                hash_documento, assinante_id, assinante_nome, assinante_cpf, assinante_cargo, 
                tipo_assinatura, assinatura_criptografada, timestamp_assinatura, ip_assinante
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            
            $stmtAss->execute([
                $novoDocumentoId, // NOVO ID único
                $id,
                $docAnterior['tipo_documento'] ?? 'parecer',
                $docAnterior['nome_arquivo'],
                $docAnterior['caminho_arquivo'],
                $docAnterior['hash_documento'],
                $_SESSION['admin_id'],
                $_SESSION['admin_nome'],
                null,
                'Secretário Municipal de Meio Ambiente',
                'texto',
                $novaAssinaturaCriptografada,
                $_SERVER['REMOTE_ADDR']
            ]);
        }

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
