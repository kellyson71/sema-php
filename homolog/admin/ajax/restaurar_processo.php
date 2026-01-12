<?php
require_once '../conexao.php';
verificaLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../requerimentos_arquivados.php?error=id_invalido");
    exit;
}

$id = (int)$_GET['id'];

try {
    $pdo->beginTransaction();

    // Buscar dados do processo arquivado
    $stmt = $pdo->prepare("SELECT * FROM requerimentos_arquivados WHERE id = ?");
    $stmt->execute([$id]);
    $processoArquivado = $stmt->fetch();

    if (!$processoArquivado) {
        throw new Exception("Processo arquivado não encontrado.");
    }

    // Verificar se já existe um requerimento com o mesmo protocolo
    $stmt = $pdo->prepare("SELECT id FROM requerimentos WHERE protocolo = ?");
    $stmt->execute([$processoArquivado['protocolo']]);
    if ($stmt->fetch()) {
        throw new Exception("Já existe um requerimento ativo com este protocolo.");
    }

    // Verificar se os requerente e proprietário ainda existem, senão criar novos
    $requerente_id = $processoArquivado['requerente_id'];
    $stmt = $pdo->prepare("SELECT id FROM requerentes WHERE id = ?");
    $stmt->execute([$requerente_id]);
    if (!$stmt->fetch()) {
        // Recriar requerente
        $stmt = $pdo->prepare("
            INSERT INTO requerentes (nome, email, cpf_cnpj, telefone) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $processoArquivado['requerente_nome'],
            $processoArquivado['requerente_email'],
            $processoArquivado['requerente_cpf_cnpj'],
            $processoArquivado['requerente_telefone']
        ]);
        $requerente_id = $pdo->lastInsertId();
    }

    $proprietario_id = null;
    if ($processoArquivado['proprietario_id'] && !empty($processoArquivado['proprietario_nome'])) {
        $stmt = $pdo->prepare("SELECT id FROM proprietarios WHERE id = ?");
        $stmt->execute([$processoArquivado['proprietario_id']]);
        if (!$stmt->fetch()) {
            // Recriar proprietário
            $stmt = $pdo->prepare("
                INSERT INTO proprietarios (nome, cpf_cnpj, requerente_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $processoArquivado['proprietario_nome'],
                $processoArquivado['proprietario_cpf_cnpj'],
                $requerente_id
            ]);
            $proprietario_id = $pdo->lastInsertId();
        } else {
            $proprietario_id = $processoArquivado['proprietario_id'];
        }
    }

    // Restaurar requerimento na tabela principal
    $stmt = $pdo->prepare("
        INSERT INTO requerimentos (
            protocolo, tipo_alvara, requerente_id, proprietario_id,
            endereco_objetivo, status, observacoes, data_envio, data_atualizacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $processoArquivado['protocolo'],
        $processoArquivado['tipo_alvara'],
        $requerente_id,
        $proprietario_id,
        $processoArquivado['endereco_objetivo'],
        $processoArquivado['status'],
        $processoArquivado['observacoes'],
        $processoArquivado['data_envio'],
        $processoArquivado['data_atualizacao']
    ]);

    $novo_requerimento_id = $pdo->lastInsertId();

    // Registrar no histórico
    $stmt = $pdo->prepare("
        INSERT INTO historico_acoes (admin_id, requerimento_id, acao) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['admin_id'],
        $novo_requerimento_id,
        "Restaurou processo arquivado - ID arquivado: {$id}"
    ]);

    // Remover da tabela de arquivados
    $stmt = $pdo->prepare("DELETE FROM requerimentos_arquivados WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    // Redirecionar com sucesso
    header("Location: ../requerimentos_arquivados.php?success=restaurado");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: ../requerimentos_arquivados.php?error=restauracao&msg=" . urlencode($e->getMessage()));
    exit;
}
?> 