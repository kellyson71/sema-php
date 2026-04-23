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
            // Excluir requerimentos e dados relacionados
            $stmt = $pdo->prepare("DELETE FROM requerimentos WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            $mensagem = count($ids) . ' requerimento(s) excluído(s) com sucesso!';
            registrarAcao("Excluiu " . count($ids) . " requerimentos em massa");
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
