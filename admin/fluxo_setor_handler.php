<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pagamento_helpers.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();
ensureAdminNotificationTables($pdo);

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
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            registraHistorico($pdo, $adminId, $id, "Setor 3 aprovou — processo retornou ao Setor 2 para envio ao cidadão" . ($motivo ? ": $motivo" : ''));
            createAdminNotificationForRequerimento($pdo, $id, 'setor3_aprovado');
            break;

        case 'doc_final_envio':
            // Setor 2 envia documento final ao cidadão
            $arquivo = $_FILES['doc_final_pdf'] ?? null;
            if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Anexe o PDF do documento final.');
            }
            $dir = dirname(__DIR__) . '/uploads/' . $req['protocolo'];
            $salvo = salvarArquivo($arquivo, $dir, 'doc_final');
            if (!$salvo) {
                throw new RuntimeException('Arquivo inválido. Envie apenas PDF (máx. 10MB).');
            }
            $instrucoes = trim($_POST['instrucoes_doc_final'] ?? '');
            $token = gerarTokenDocumentoFinal((int) $id, $req['protocolo']);
            $pdo->prepare("
                INSERT INTO documentos_finais (requerimento_id, caminho_arquivo, nome_arquivo, instrucoes, token_acesso, admin_envio_id)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE caminho_arquivo = VALUES(caminho_arquivo), nome_arquivo = VALUES(nome_arquivo),
                    instrucoes = VALUES(instrucoes), token_acesso = VALUES(token_acesso),
                    admin_envio_id = VALUES(admin_envio_id), enviado_em = NOW(), data_atualizacao = NOW()
            ")->execute([$id, $salvo['caminho_relativo'] ?? $salvo['caminho'], $salvo['nome_original'], $instrucoes, $token, $adminId]);
            atualizaSetor($pdo, $id, 'setor2', 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status = 'Finalizado', data_atualizacao = NOW() WHERE id = ?")->execute([$id]);
            registraHistorico($pdo, $adminId, $id, 'Finalizou o processo enviando documento final ao requerente');
            break;

        default:
            $pdo->rollBack();
            header("Location: visualizar_requerimento.php?id=$id&error=acao_invalida");
            exit;
    }

    $pdo->commit();
    header("Location: visualizar_requerimento.php?id=$id&success=fluxo_atualizado");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = urlencode($e->getMessage());
    header("Location: visualizar_requerimento.php?id=$id&error=erro_fluxo&details=$msg");
    exit;
}
