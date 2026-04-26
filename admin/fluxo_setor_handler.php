<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requerimentos.php');
    exit;
}

$id     = (int) ($_POST['requerimento_id'] ?? 0);
$acao   = $_POST['fluxo_acao'] ?? '';
$motivo = trim($_POST['motivo'] ?? '');

if (!$id || !$acao) {
    header("Location: requerimentos.php?error=dados_invalidos");
    exit;
}

$stmt = $pdo->prepare("SELECT id, status, setor_atual, aguardando_acao, protocolo FROM requerimentos WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    header("Location: requerimentos.php?error=dados_invalidos");
    exit;
}

function registraHistorico(PDO $pdo, int $adminId, int $reqId, string $acao): void
{
    $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?,?,?)")
        ->execute([$adminId, $reqId, $acao]);
}

function atualizaSetor(PDO $pdo, int $id, string $setor, string $acao): void
{
    $pdo->prepare("UPDATE requerimentos SET setor_atual=?, aguardando_acao=?, data_atualizacao=NOW() WHERE id=?")
        ->execute([$setor, $acao, $id]);
}

$adminId = $_SESSION['admin_id'];

try {
    $pdo->beginTransaction();

    switch ($acao) {
        case 'enviar_setor2':
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            registraHistorico($pdo, $adminId, $id, "Enviou processo ao Setor 2 — Análise" . ($motivo ? ": $motivo" : ''));
            notificarAdmins($pdo, $id, 'setor2', "Processo #{$req['protocolo']} chegou ao Setor 2.");
            break;

        case 'enviar_setor3':
            atualizaSetor($pdo, $id, 'setor3', 'revisao_setor3');
            registraHistorico($pdo, $adminId, $id, "Enviou processo ao Setor 3 — Revisão Final" . ($motivo ? ": $motivo" : ''));
            notificarAdmins($pdo, $id, 'setor3', "Processo #{$req['protocolo']} chegou ao Setor 3 para revisão.");
            break;

        case 'devolver_setor2':
            if (!$motivo) {
                $pdo->rollBack();
                header("Location: visualizar_requerimento.php?id=$id&error=motivo_obrigatorio");
                exit;
            }
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            registraHistorico($pdo, $adminId, $id, "Setor 3 devolveu ao Setor 2 — Motivo: $motivo");
            notificarAdmins($pdo, $id, 'setor2', "Processo #{$req['protocolo']} devolvido pelo Setor 3: $motivo");
            break;

        case 'devolver_setor1':
            atualizaSetor($pdo, $id, 'setor1', 'triagem_setor1');
            registraHistorico($pdo, $adminId, $id, "Setor 2 devolveu ao Setor 1" . ($motivo ? ": $motivo" : ''));
            break;

        case 'concluir_direto':
            atualizaSetor($pdo, $id, 'setor1', 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status='Finalizado', data_atualizacao=NOW() WHERE id=?")
                ->execute([$id]);
            registraHistorico($pdo, $adminId, $id, "Concluiu processo diretamente no Setor 1" . ($motivo ? ": $motivo" : ''));
            break;

        case 'concluir_setor2':
            atualizaSetor($pdo, $id, 'setor2', 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status='Finalizado', data_atualizacao=NOW() WHERE id=?")
                ->execute([$id]);
            registraHistorico($pdo, $adminId, $id, "Concluiu e finalizou processo no Setor 2" . ($motivo ? ": $motivo" : ''));
            break;

        case 'setor3_aprovado':
            // Setor 3 assinou/aprovou → volta ao setor 2 para envio ao cidadão
            atualizaSetor($pdo, $id, 'setor2', 'envio_cidadao');
            registraHistorico($pdo, $adminId, $id, "Setor 3 aprovou — processo retornou ao Setor 2 para envio ao cidadão");
            notificarAdmins($pdo, $id, 'setor2', "Processo #{$req['protocolo']} aprovado pelo Setor 3. Pronto para envio ao cidadão.");
            break;

        default:
            $pdo->rollBack();
            header("Location: visualizar_requerimento.php?id=$id&error=acao_invalida");
            exit;
    }

    $pdo->commit();
    header("Location: visualizar_requerimento.php?id=$id&success=fluxo_atualizado");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: visualizar_requerimento.php?id=$id&error=erro_fluxo");
    exit;
}

/**
 * Envia notificação para admins que operam o setor alvo.
 * Usa a tabela admin_notifications se existir, caso contrário ignora silenciosamente.
 */
function notificarAdmins(PDO $pdo, int $reqId, string $setorAlvo, string $mensagem): void
{
    try {
        $pdo->prepare(
            "INSERT INTO admin_notifications (requerimento_id, tipo, mensagem, lida, data_criacao)
             VALUES (?, 'fluxo_setor', ?, 0, NOW())"
        )->execute([$reqId, $mensagem]);
    } catch (Exception $e) {
        // tabela pode não existir ainda
    }
}
