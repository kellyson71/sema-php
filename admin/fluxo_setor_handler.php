<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pagamento_helpers.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../tipos_alvara.php';
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

$stmt = $pdo->prepare("SELECT id, status, setor_atual, aguardando_acao, protocolo, requerente_id FROM requerimentos WHERE id = ?");
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

function notificarCidadaoConclusao(PDO $pdo, int $id): void
{
    global $tipos_alvara;
    $stmt = $pdo->prepare("
        SELECT r.protocolo, r.tipo_alvara, re.nome, re.email
        FROM requerimentos r JOIN requerentes re ON re.id = r.requerente_id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || empty($row['email'])) return;
    $tipoNome = $tipos_alvara[$row['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $row['tipo_alvara']));
    (new EmailService())->enviarEmailAprovado($row['email'], $row['nome'], $row['protocolo'], $tipoNome, $id);
}

$adminId = $_SESSION['admin_id'];

// Autorização por setor: cada ação só pode ser disparada pelo role do setor responsável.
// admin/admin_geral têm acesso amplo. Defesa em profundidade — a UI já esconde os botões,
// mas o handler não pode confiar apenas nisso (POST pode ser forjado).
$nivelAtual = $_SESSION['admin_nivel'] ?? '';
$isSuper    = in_array($nivelAtual, ['admin', 'admin_geral'], true);
$rolePorAcao = [
    'enviar_setor2'      => 'analista',   // Setor 1
    'concluir_direto'    => 'analista',
    'enviar_setor3'      => 'fiscal',     // Setor 2
    'devolver_setor1'    => 'fiscal',
    'concluir_setor2'    => 'fiscal',
    'devolver_setor2'    => 'secretario', // Setor 3
    'setor3_aprovado'    => 'secretario',
    'setor3_recusado'    => 'secretario',
    'setor3_sem_decisao' => 'secretario',
];

// A entrega de documentos ao cidadão não pertence a um setor: Triagem e
// Fiscalização entregam, independente de onde o processo esteja parado. O Setor 1
// conclui a maioria dos processos sozinho e até aqui só podia mandar um número de
// protocolo, sem o documento. Diferente das demais ações, aceita mais de um role.
$rolesEntregaDocFinal = ['analista', 'fiscal'];
if ($acao === 'doc_final_envio' && !$isSuper && !in_array($nivelAtual, $rolesEntregaDocFinal, true)) {
    header("Location: visualizar_requerimento.php?id=$id&error=sem_permissao");
    exit;
}

if (isset($rolePorAcao[$acao]) && !$isSuper && $nivelAtual !== $rolePorAcao[$acao]) {
    $fromDocViewer = ($_POST['referer'] ?? '') === 'visualizar_documento';
    $dest = $fromDocViewer
        ? "visualizar_documento.php?requerimento_id=$id&error=sem_permissao"
        : "visualizar_requerimento.php?id=$id&error=sem_permissao";
    header("Location: $dest");
    exit;
}

$notificarConclusao = ($_POST['notificar_cidadao'] ?? '') === '1';

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
            // Conclui NO Setor 1. Antes gravava setor_atual='setor2', o que fazia o
            // processo constar como concluído pela Fiscalização — que nunca o viu.
            atualizaSetor($pdo, $id, 'setor1', 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status='Finalizado', data_atualizacao=NOW() WHERE id=?")
                ->execute([$id]);
            registraHistorico($pdo, $adminId, $id, "Concluiu processo diretamente no Setor 1" . ($motivo ? ": $motivo" : '') . ($notificarConclusao ? ' (cidadão notificado por email)' : ''));
            break;

        case 'concluir_setor2':
            atualizaSetor($pdo, $id, 'setor2', 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status='Finalizado', data_atualizacao=NOW() WHERE id=?")
                ->execute([$id]);
            registraHistorico($pdo, $adminId, $id, "Concluiu e finalizou processo no Setor 2" . ($motivo ? ": $motivo" : '') . ($notificarConclusao ? ' (cidadão notificado por email)' : ''));
            break;

        case 'setor3_aprovado':
            // Validar que o secretário assinou pelo menos 1 documento
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_digitais WHERE requerimento_id = ? AND assinante_id = ?");
            $stmt->execute([$id, $adminId]);
            $temAssinatura = (int) $stmt->fetchColumn();
            if (!$temAssinatura) {
                throw new RuntimeException('Você precisa assinar pelo menos um documento antes de aprovar e retornar.');
            }
            atualizaSetor($pdo, $id, 'setor2', 'retorno_aprovado');
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
            atualizaSetor($pdo, $id, 'setor2', 'retorno_recusado');
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

            // Um token por LOTE, compartilhado por todos os documentos deste envio.
            // Antes cada linha recebia um token diferente e a página pública filtrava
            // por token, então o cidadão só enxergava o primeiro documento da lista.
            $loteId    = bin2hex(random_bytes(16));
            $token     = gerarTokenDocumentoFinal((int) $id);
            $expiraEm  = date('Y-m-d H:i:s', strtotime('+' . ENTREGA_LINK_VALIDADE_DIAS . ' days'));

            // Entregas anteriores são revogadas, não apagadas: o link antigo para de
            // funcionar mas o registro de o quê / quem / quando permanece auditável.
            $pdo->prepare("
                UPDATE documentos_finais SET revogado_em = NOW()
                WHERE requerimento_id = ? AND revogado_em IS NULL
            ")->execute([$id]);

            $stmtDoc = $pdo->prepare("SELECT id, documento_id, nome_arquivo, caminho_arquivo FROM assinaturas_digitais WHERE id = ? AND requerimento_id = ?");
            $stmtInsert = $pdo->prepare("
                INSERT INTO documentos_finais
                    (requerimento_id, lote_id, documento_id, caminho_arquivo, nome_arquivo, instrucoes, token_acesso, admin_envio_id, expira_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $docsEmail = [];
            foreach ($documentoIds as $docId) {
                $stmtDoc->execute([$docId, $id]);
                $docRow = $stmtDoc->fetch();
                if (!$docRow) continue;

                $caminhoFisico = UPLOAD_DIR . ltrim($docRow['caminho_arquivo'], '/');
                if (!file_exists($caminhoFisico)) {
                    throw new RuntimeException('O arquivo "' . $docRow['nome_arquivo'] . '" não foi encontrado no servidor. O documento pode ter sido removido. Gere um novo documento antes de enviar.');
                }

                $stmtInsert->execute([
                    $id, $loteId, $docRow['documento_id'], $docRow['caminho_arquivo'],
                    $docRow['nome_arquivo'], $instrucoes, $token, $adminId, $expiraEm,
                ]);

                $docsEmail[] = [
                    'nome'   => $docRow['nome_arquivo'],
                    'rotulo' => rotuloDocumento($docRow['nome_arquivo']),
                ];
            }

            if (empty($docsEmail)) {
                throw new RuntimeException('Nenhum dos documentos selecionados pertence a este processo.');
            }

            // O processo é concluído no setor em que está, não sempre no Setor 2.
            atualizaSetor($pdo, $id, $req['setor_atual'], 'concluido');
            $pdo->prepare("UPDATE requerimentos SET status = 'Finalizado', data_atualizacao = NOW() WHERE id = ?")->execute([$id]);
            registraHistorico($pdo, $adminId, $id, 'Finalizou o processo enviando ' . count($docsEmail) . ' documento(s) final(is) ao requerente');

            $stmtReq = $pdo->prepare("
                SELECT r.tipo_alvara, re.nome AS requerente_nome, re.email AS requerente_email
                FROM requerimentos r
                JOIN requerentes re ON re.id = r.requerente_id
                WHERE r.id = ?
            ");
            $stmtReq->execute([$id]);
            $reqData = $stmtReq->fetch();

            if ($reqData && !empty($reqData['requerente_email'])) {
                $tipoNome = $tipos_alvara[$reqData['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $reqData['tipo_alvara']));

                $emailService = new EmailService();
                $emailService->enviarEmailDocumentoFinal(
                    $reqData['requerente_email'],
                    $reqData['requerente_nome'],
                    $req['protocolo'],
                    $tipoNome,
                    $docsEmail,
                    $instrucoes,
                    $id,
                    gerarUrlDocumentoFinal($token),
                    ENTREGA_LINK_VALIDADE_DIAS
                );
            }
            break;

        default:
            $pdo->rollBack();
            header("Location: visualizar_requerimento.php?id=$id&error=acao_invalida");
            exit;
    }

    $pdo->commit();

    // Notificação opcional ao cidadão nas conclusões sem documento final (após commit: falha de email não desfaz o fluxo)
    if ($notificarConclusao && in_array($acao, ['concluir_direto', 'concluir_setor2'], true)) {
        try {
            notificarCidadaoConclusao($pdo, $id);
        } catch (Throwable $eMail) {
            error_log('[fluxo_setor] Falha ao notificar cidadão da conclusão #' . $id . ': ' . $eMail->getMessage());
        }
    }

    $fromDocViewer = ($_POST['referer'] ?? '') === 'visualizar_documento';
    if ($fromDocViewer) {
        header("Location: visualizar_documento.php?requerimento_id=$id&success=fluxo_atualizado");
    } else {
        header("Location: visualizar_requerimento.php?id=$id&success=fluxo_atualizado");
    }
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[fluxo_setor] Erro requerimento #' . ($id ?? '?') . ': ' . $e->getMessage());
    $fromDocViewer = ($_POST['referer'] ?? '') === 'visualizar_documento';
    if ($fromDocViewer) {
        header("Location: visualizar_documento.php?requerimento_id=$id&error=erro_fluxo");
    } else {
        header("Location: visualizar_requerimento.php?id=$id&error=erro_fluxo");
    }
    exit;
}
