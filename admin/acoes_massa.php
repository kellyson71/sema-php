<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

// Verificar se recebeu dados via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requerimentos.php');
    exit;
}

$acao = $_POST['acao'] ?? '';
$ids = $_POST['ids'] ?? [];

// Validar dados
if (empty($acao) || empty($ids)) {
    header('Location: requerimentos.php?error=dados_invalidos');
    exit;
}

// Sanitizar IDs
$ids = array_map('intval', $ids);
$ids = array_filter($ids, function ($id) {
    return $id > 0;
});

if (empty($ids)) {
    header('Location: requerimentos.php?error=ids_invalidos');
    exit;
}

$placeholders = str_repeat('?,', count($ids) - 1) . '?';

try {
    $pdo->beginTransaction();

    switch ($acao) {
        case 'excluir':
            arquivarRequerimentos($ids, 'Excluído pela listagem de requerimentos');

            $mensagem = count($ids) . ' requerimento(s) removido(s) da lista com sucesso!';
            registrarAcao("Arquivou " . count($ids) . " requerimentos pela ação de excluir");
            break;

        case 'alterar_status':
            $novoStatus = $_POST['status'] ?? '';
            if (empty($novoStatus)) {
                throw new Exception('Status não informado');
            }
            if (!adminStatusPermitidoParaOperacao($novoStatus)) {
                throw new Exception('Status não disponível na operação atual');
            }

            $stmt = $pdo->prepare("UPDATE requerimentos SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$novoStatus], $ids);
            $stmt->execute($params);

            if ($novoStatus === 'Indeferido') {
                foreach ($ids as $id) {
                    createAdminNotificationForRequerimento($pdo, (int) $id, 'indeferido');
                }
            }

            $mensagem = count($ids) . ' requerimento(s) alterado(s) para "' . $novoStatus . '" com sucesso!';
            registrarAcao("Alterou status de " . count($ids) . " requerimentos para '$novoStatus'");
            break;

        case 'marcar_lido':
            $stmt = $pdo->prepare("UPDATE requerimentos SET visualizado = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $mensagem = count($ids) . ' protocolo(s) marcado(s) como abertos!';
            registrarAcao("Marcou " . count($ids) . " protocolos como abertos");
            break;

        case 'marcar_nao_lido':
            $stmt = $pdo->prepare("UPDATE requerimentos SET visualizado = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $mensagem = count($ids) . ' protocolo(s) devolvido(s) para a fila!';
            registrarAcao("Devolveu " . count($ids) . " protocolos para a fila");
            break;

        default:
            throw new Exception('Ação não reconhecida');
    }

    $pdo->commit();
    header('Location: requerimentos.php?success=acoes_massa&msg=' . urlencode($mensagem));
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: requerimentos.php?error=erro_acao&details=' . urlencode($e->getMessage()));
}

function registrarAcao($descricao)
{
    global $pdo;

    $adminId = $_SESSION['admin_id'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, acao, data_acao) VALUES (?, ?, NOW())");
    $stmt->execute([$adminId, $descricao]);
}

function arquivarRequerimentos(array $ids, string $motivo): void
{
    global $pdo;

    $adminId = $_SESSION['admin_id'] ?? null;

    $stmtBusca = $pdo->prepare("
        SELECT r.*,
               req.nome as requerente_nome,
               req.cpf_cnpj as requerente_cpf_cnpj,
               req.telefone as requerente_telefone,
               req.email as requerente_email,
               p.nome as proprietario_nome,
               p.cpf_cnpj as proprietario_cpf_cnpj
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        LEFT JOIN proprietarios p ON r.proprietario_id = p.id
        WHERE r.id = ?
    ");

    $stmtArquiva = $pdo->prepare("
        INSERT INTO requerimentos_arquivados (
            requerimento_id, protocolo, tipo_alvara, requerente_id, proprietario_id,
            endereco_objetivo, status, observacoes, data_envio, data_atualizacao,
            admin_arquivamento, motivo_arquivamento, requerente_nome, requerente_email,
            requerente_cpf_cnpj, requerente_telefone, proprietario_nome, proprietario_cpf_cnpj
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtHistorico = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
    $stmtRemove = $pdo->prepare("DELETE FROM requerimentos WHERE id = ?");

    foreach ($ids as $id) {
        $stmtBusca->execute([$id]);
        $dados = $stmtBusca->fetch();

        if (!$dados) {
            continue;
        }

        $stmtArquiva->execute([
            $dados['id'],
            $dados['protocolo'],
            $dados['tipo_alvara'],
            $dados['requerente_id'],
            $dados['proprietario_id'],
            $dados['endereco_objetivo'],
            $dados['status'],
            $dados['observacoes'],
            $dados['data_envio'],
            $dados['data_atualizacao'],
            $adminId,
            $motivo,
            $dados['requerente_nome'],
            $dados['requerente_email'],
            $dados['requerente_cpf_cnpj'],
            $dados['requerente_telefone'],
            $dados['proprietario_nome'] ?? null,
            $dados['proprietario_cpf_cnpj'] ?? null
        ]);

        $stmtHistorico->execute([$adminId, $id, "Arquivou o processo pela ação de excluir"]);
        $stmtRemove->execute([$id]);
    }
}
