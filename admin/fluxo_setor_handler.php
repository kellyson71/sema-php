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
            createAdminNotificationForRequerimento($pdo, $id, 'encaminhado_setor2');
            break;

        case 'enviar_setor3':
            atualizaSetor($pdo, $id, 'setor3', 'revisao_setor3');
            registraHistorico($pdo, $adminId, $id, "Enviou processo ao Setor 3 — Revisão Final" . ($motivo ? ": $motivo" : ''));
            createAdminNotificationForRequerimento($pdo, $id, 'encaminhado_setor3');
            break;

        case 'devolver_setor2':
            if (!$motivo) {
                $pdo->rollBack();
                header("Location: visualizar_requerimento.php?id=$id&error=motivo_obrigatorio");
                exit;
            }
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            registraHistorico($pdo, $adminId, $id, "Setor 3 devolveu ao Setor 2 — Motivo: $motivo");
            createAdminNotificationForRequerimento($pdo, $id, 'devolvido_setor2');
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
            // Validar que o secretário assinou pelo menos 1 documento
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_digitais WHERE requerimento_id = ? AND assinante_id = ?");
            $stmt->execute([$id, $adminId]);
            $temAssinatura = (int) $stmt->fetchColumn();
            if (!$temAssinatura) {
                throw new RuntimeException('Você precisa assinar pelo menos um documento antes de aprovar e retornar.');
            }
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            registraHistorico($pdo, $adminId, $id, "Setor 3 aprovou e retornou ao Setor 2 para envio ao cidadão" . ($motivo ? ": $motivo" : ''));
            createAdminNotificationForRequerimento($pdo, $id, 'setor3_aprovado');
            break;

        case 'setor3_recusado':
            if (!$motivo) {
                $pdo->rollBack();
                $dest = ($_POST['referer'] ?? '') === 'visualizar_documento'
                    ? "visualizar_documento.php?requerimento_id=$id&error=motivo_obrigatorio"
                    : "visualizar_requerimento.php?id=$id&error=motivo_obrigatorio";
                header("Location: $dest");
                exit;
            }
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            $pdo->prepare("UPDATE requerimentos SET motivo_devolucao = ?, data_atualizacao = NOW() WHERE id = ?")->execute([$motivo, $id]);
            registraHistorico($pdo, $adminId, $id, "Setor 3 recusou e devolveu ao Setor 2 — Motivo: $motivo");
            createAdminNotificationForRequerimento($pdo, $id, 'devolvido_setor2');
            break;

        case 'setor3_sem_decisao':
            atualizaSetor($pdo, $id, 'setor2', 'analise_setor2');
            registraHistorico($pdo, $adminId, $id, "Setor 3 retornou ao Setor 2 sem decisão" . ($motivo ? ": $motivo" : ''));
            createAdminNotificationForRequerimento($pdo, $id, 'devolvido_setor2');
            break;

        case 'doc_final_envio':
            $documentoIds = array_filter(array_map('intval', $_POST['documento_ids'] ?? []));
            if (empty($documentoIds)) {
                throw new RuntimeException('Selecione pelo menos um documento para enviar ao cidadão.');
            }
            $instrucoes = trim($_POST['instrucoes_doc_final'] ?? '');
            $loteId = bin2hex(random_bytes(16));
            $token = gerarTokenDocumentoFinal((int) $id, $req['protocolo']);

            // Limpar envios anteriores deste requerimento
            $pdo->prepare("DELETE FROM documentos_finais WHERE requerimento_id = ?")->execute([$id]);

            $stmtDoc = $pdo->prepare("SELECT id, nome_arquivo, caminho_arquivo FROM assinaturas_digitais WHERE id = ? AND requerimento_id = ?");
            $stmtInsert = $pdo->prepare("
                INSERT INTO documentos_finais (requerimento_id, caminho_arquivo, nome_arquivo, instrucoes, token_acesso, admin_envio_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($documentoIds as $docId) {
                $stmtDoc->execute([$docId, $id]);
                $docRow = $stmtDoc->fetch();
                if (!$docRow) continue;
                $stmtInsert->execute([$id, $docRow['caminho_arquivo'], $docRow['nome_arquivo'], $instrucoes, $token, $adminId]);
                // Tokens subsequentes usam ID de lote para unicidade
                $token = $loteId . '_' . $docId;
            }
            // Restaurar o token real no primeiro registro para compatibilidade com documento_final.php
            $tokenFinal = gerarTokenDocumentoFinal((int) $id, $req['protocolo']);
            $pdo->prepare("UPDATE documentos_finais SET token_acesso = ? WHERE requerimento_id = ? ORDER BY id ASC LIMIT 1")->execute([$tokenFinal, $id]);

            atualizaSetor($pdo, $id, 'setor2', 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status = 'Finalizado', data_atualizacao = NOW() WHERE id = ?")->execute([$id]);
            registraHistorico($pdo, $adminId, $id, 'Finalizou o processo enviando ' . count($documentoIds) . ' documento(s) final(is) ao requerente');
            break;

        default:
            $pdo->rollBack();
            header("Location: visualizar_requerimento.php?id=$id&error=acao_invalida");
            exit;
    }

    $pdo->commit();
    $fromDocViewer = ($_POST['referer'] ?? '') === 'visualizar_documento';
    if ($fromDocViewer) {
        header("Location: visualizar_documento.php?requerimento_id=$id&success=fluxo_atualizado");
    } else {
        header("Location: visualizar_requerimento.php?id=$id&success=fluxo_atualizado");
    }
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = urlencode($e->getMessage());
    $fromDocViewer = ($_POST['referer'] ?? '') === 'visualizar_documento';
    if ($fromDocViewer) {
        header("Location: visualizar_documento.php?requerimento_id=$id&error=erro_fluxo&details=$msg");
    } else {
        header("Location: visualizar_requerimento.php?id=$id&error=erro_fluxo&details=$msg");
    }
    exit;
}
