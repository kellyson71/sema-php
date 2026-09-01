<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once $rootDir . '/includes/assinatura_avancada_service.php';
require_once $rootDir . '/includes/coassinatura_helper.php';
require_once $rootDir . '/includes/admin_notifications.php';
require_once $rootDir . '/includes/assinatura_workflow_helpers.php';

function coRespostaJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coRespostaJson(['success' => false, 'code' => 'method_not_allowed', 'error' => 'Método inválido.'], 405);
}

if (!assinaturaSessaoAdminAtiva($pdo)) {
    coRespostaJson([
        'success' => false,
        'code' => 'session_expired',
        'error' => 'Sua sessão realmente expirou. Entre novamente para continuar.',
    ], 401);
}

try {
    validarCsrfAssinatura($_POST['csrf_token'] ?? null);
} catch (Throwable $e) {
    $erro = respostaErroAssinatura($e, '[coassinar] CSRF');
    coRespostaJson($erro['payload'], $erro['status']);
}

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_token'] ?? '');
if ($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'A sessão de assinatura expirou. Recarregue a página e tente novamente.']);
    exit;
}

$documentoId    = trim($_POST['documento_id']    ?? '');
$adminId        = $_SESSION['admin_id'] ?? null;
$pinAssinatura  = $_POST['pin_assinatura'] ?? '';

// O restante do fluxo não altera $_SESSION e pode liberar o lock enquanto
// renderiza o PDF, permitindo que outras abas continuem respondendo.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// O processo a que o documento pertence é lido do banco (etapa 1), nunca do POST:
// um requerimento_id vindo do cliente registraria a assinatura no processo errado.
$requerimentoId = 0;

if (!$documentoId || !$adminId) {
    coRespostaJson(['success' => false, 'code' => 'invalid_request', 'error' => 'Documento não informado.'], 422);
}

$caminhoTemporario = null;
$caminhoBackup = null;
$arquivoSubstituido = false;

try {
    $pdo->beginTransaction();

    // 1. Buscar fonte do documento (HTML + caminho do PDF)
    $stmtFonte = $pdo->prepare("SELECT * FROM documentos_fonte WHERE documento_id = ?");
    $stmtFonte->execute([$documentoId]);
    $fonte = $stmtFonte->fetch(PDO::FETCH_ASSOC);

    if (!$fonte) {
        throw new AssinaturaWorkflowException(
            'document_source_missing',
            'Este documento é anterior à atualização e não suporta coassinatura.',
            409
        );
    }

    $requerimentoId = (int) ($fonte['requerimento_id'] ?? 0);
    if (!$requerimentoId) {
        throw new AssinaturaWorkflowException('document_source_invalid', 'Não foi possível identificar o processo deste documento.', 409);
    }

    // Só o destinatário de uma solicitação pendente pode coassinar.
    $stmtPendente = $pdo->prepare("SELECT id FROM solicitacoes_assinatura
        WHERE documento_id = ? AND destinatario_id = ? AND status = 'pendente'
        LIMIT 1 FOR UPDATE");
    $stmtPendente->execute([$documentoId, $adminId]);
    $solicitacaoId = (int) ($stmtPendente->fetchColumn() ?: 0);
    if (!$solicitacaoId) {
        throw new AssinaturaWorkflowException(
            'signature_request_missing',
            'Não há uma solicitação de assinatura pendente para você neste documento.',
            403
        );
    }

    // Somente o servidor indicado em uma solicitação pendente pode assinar.
    $stmtPermissao = $pdo->prepare("SELECT id FROM solicitacoes_assinatura WHERE documento_id = ? AND destinatario_id = ? AND status = 'pendente' LIMIT 1");
    $stmtPermissao->execute([$documentoId, $adminId]);
    if (!$stmtPermissao->fetchColumn()) {
        $pdo->rollBack();
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Não há uma solicitação de assinatura pendente para você neste documento.']);
        exit;
    }

    // 2. Verificar se o admin já assinou este documento
    $stmtCheck = $pdo->prepare("SELECT id FROM assinaturas_digitais WHERE documento_id = ? AND assinante_id = ?");
    $stmtCheck->execute([$documentoId, $adminId]);
    if ($stmtCheck->fetch()) {
        throw new AssinaturaWorkflowException('already_signed', 'Você já assinou este documento.', 409);
    }

    // 3. Buscar dados do admin atual
    $stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmtAdmin->execute([$adminId]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new AssinaturaWorkflowException('signer_not_found', 'Usuário assinante não encontrado.', 404);
    }

    // 3b. A credencial é obrigatória. Conta com chave usa PIN; conta sem chave
    //     usa a senha de acesso, exatamente como informado na interface.
    $servicoAvancada = new AssinaturaAvancadaService($pdo);
    $hashConteudo    = AssinaturaAvancadaService::hashConteudo($fonte['conteudo_html']);
    $assinaturaRsa = null;
    $pinAssinatura = trim($pinAssinatura);

    if ($pinAssinatura === '') {
        throw new AssinaturaWorkflowException('credential_required', 'Informe sua credencial para confirmar a assinatura.', 422);
    }
    if ($servicoAvancada->temChave((int) $adminId)) {
        // Admin com PIN configurado → RSA avançado
        try {
            $assinaturaRsa = $servicoAvancada->assinar((int) $adminId, $pinAssinatura, $hashConteudo);
        } catch (RuntimeException $eRsa) {
            if ($eRsa->getMessage() === 'PIN_INCORRETO') {
                throw new AssinaturaWorkflowException('credential_invalid', 'PIN de assinatura incorreto.', 422, $eRsa);
            }
            throw $eRsa;
        }
    } else {
        // Sem PIN → verifica senha de login como confirmação de identidade
        $stSenha = $pdo->prepare("SELECT senha FROM administradores WHERE id = ?");
        $stSenha->execute([$adminId]);
        $hashSenha = $stSenha->fetchColumn();
        if (!$hashSenha || !password_verify($pinAssinatura, $hashSenha)) {
            throw new AssinaturaWorkflowException('credential_invalid', 'Senha de acesso incorreta.', 422);
        }
    }
    $nivelCoAs = $assinaturaRsa !== null ? 'avancada' : 'simples';

    // 4. Buscar todos os assinantes existentes (ordem cronológica)
    $stmtSigs = $pdo->prepare("
        SELECT ad.assinante_nome, ad.assinante_cargo, ad.assinante_cpf,
               a.matricula_portaria, ad.timestamp_assinatura, ad.tipo_assinatura
        FROM assinaturas_digitais ad
        LEFT JOIN administradores a ON a.id = ad.assinante_id
        WHERE ad.documento_id = ?
        ORDER BY ad.timestamp_assinatura ASC
    ");
    $stmtSigs->execute([$documentoId]);
    $signatariosExistentes = $stmtSigs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Montar array de assinantes acumulados
    $assinantes = [];
    foreach ($signatariosExistentes as $sig) {
        if ($sig['tipo_assinatura'] === 'sem_assinatura') {
            continue;
        }
        $assinantes[] = [
            'nome'      => $sig['assinante_nome'],
            'cargo'     => $sig['assinante_cargo'] ?? '',
            'cpf'       => $sig['assinante_cpf'] ?? '',
            'matricula' => $sig['matricula_portaria'] ?? '',
            'data_hora' => date('d/m/Y \à\s H:i:s', strtotime($sig['timestamp_assinatura'])),
        ];
    }
    // Adicionar assinante atual
    $assinantes[] = [
        'nome'      => $admin['nome_completo'] ?: $admin['nome'],
        'cargo'     => $admin['cargo'] ?? '',
        'cpf'       => $admin['cpf'] ?? '',
        'matricula' => $admin['matricula_portaria'] ?? '',
        'data_hora' => date('d/m/Y \à\s H:i:s'),
    ];

    // 6. Resolver caminho físico do PDF
    $caminhoRelativo = $fonte['caminho_arquivo'];
    $adminDir = dirname(__DIR__); // admin/

    if (file_exists($adminDir . '/' . ltrim($caminhoRelativo, '/'))) {
        $caminhoFisico = $adminDir . '/' . ltrim($caminhoRelativo, '/');
    } elseif (file_exists($rootDir . '/' . ltrim($caminhoRelativo, '/'))) {
        $caminhoFisico = $rootDir . '/' . ltrim($caminhoRelativo, '/');
    } else {
        throw new AssinaturaWorkflowException('pdf_not_found', 'Arquivo PDF original não encontrado no servidor.', 404);
    }

    // 7. Gerar em arquivo temporário. O original só é substituído depois de
    // todas as gravações SQL estarem prontas para commit.
    require_once __DIR__ . '/gerar_pdf.php';

    $numero_processo = "Processo_#{$requerimentoId}";
    $verifyUrl = rtrim(BASE_URL, '/') . '/verificar';
    // Posição do carimbo agora é determinística no próprio gerar_pdf.php — não
    // depende mais de sig_pos_x/sig_pos_y (removidos junto com a reescrita da
    // paginação, ver f69e2f8).
    $sufixoTemporario = bin2hex(random_bytes(8));
    $caminhoTemporario = $caminhoFisico . '.tmp.' . $sufixoTemporario;
    $caminhoBackup = $caminhoFisico . '.bak.' . $sufixoTemporario;

    emitirParecerAssinado($fonte['conteudo_html'], $assinantes, $numero_processo, 'F', $caminhoTemporario, [
        'verify_url' => $verifyUrl,
        'doc_codigo' => $documentoId,
    ]);

    if (!is_file($caminhoTemporario) || filesize($caminhoTemporario) === 0) {
        throw new AssinaturaWorkflowException('pdf_generation_failed', 'Falha ao gerar o PDF com a nova assinatura.', 500);
    }

    $novoHash = hash_file('sha256', $caminhoTemporario);

    // 8. Inserir nova linha em assinaturas_digitais (mesmo documento_id, novo assinante)
    //    assinatura_criptografada = RSA real do co-assinante sobre hash_conteudo
    $nomeArquivo = basename($caminhoFisico);
    $pdo->prepare("
        INSERT INTO assinaturas_digitais
            (documento_id, requerimento_id, tipo_documento, nome_arquivo, caminho_arquivo,
             hash_documento, hash_conteudo, assinante_id, assinante_nome, assinante_cpf, assinante_cargo,
             tipo_assinatura, nivel_assinatura, assinatura_visual, assinatura_criptografada, chave_publica,
             timestamp_assinatura, ip_assinante)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'digital_sema', ?, '{}', ?, ?, NOW(), ?)
    ")->execute([
        $documentoId,
        $requerimentoId,
        $fonte['tipo_documento'],
        $nomeArquivo,
        $caminhoRelativo,
        $novoHash,
        $hashConteudo,
        $adminId,
        $admin['nome_completo'] ?: $admin['nome'],
        $admin['cpf'] ?? '',
        $admin['cargo'] ?? '',
        $nivelCoAs,
        $assinaturaRsa['assinatura'] ?? '',
        $assinaturaRsa['chave_publica'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    // 8b. O PDF será regravado: atualizar hash_documento de TODAS as linhas
    //     deste documento_id, senão as assinaturas anteriores apontariam para
    //     um hash de arquivo que não existe mais (falso "documento adulterado").
    $pdo->prepare("UPDATE assinaturas_digitais SET hash_documento = ? WHERE documento_id = ?")
        ->execute([$novoHash, $documentoId]);

    // 9. Resolver a solicitação e registrar o histórico na mesma transação.
    $stmtResolver = $pdo->prepare("UPDATE solicitacoes_assinatura
        SET status = 'assinado', resolvido_em = NOW()
        WHERE id = ? AND status = 'pendente'");
    $stmtResolver->execute([$solicitacaoId]);
    if ($stmtResolver->rowCount() !== 1) {
        throw new AssinaturaWorkflowException('signature_request_changed', 'A solicitação foi alterada por outra sessão. Recarregue a página.', 409);
    }

    $nomeAdmin = $admin['nome_completo'] ?: $admin['nome'];
    $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)")
        ->execute([$adminId, $requerimentoId, "Co-assinou digitalmente o documento: " . strtoupper($fonte['tipo_documento'] ?? 'DOCUMENTO')]);

    // Troca recuperável do arquivo. Se o commit falhar, o catch restaura o backup.
    if (!copy($caminhoFisico, $caminhoBackup)) {
        throw new RuntimeException('Não foi possível criar o backup temporário do PDF.');
    }
    if (!rename($caminhoTemporario, $caminhoFisico)) {
        throw new RuntimeException('Não foi possível ativar o novo PDF assinado.');
    }
    $arquivoSubstituido = true;

    $pdo->commit();
    if (is_file($caminhoBackup)) {
        @unlink($caminhoBackup);
    }

    // 11. Se todas as assinaturas solicitadas foram concluídas, avisa o solicitante
    $coassinaturaCompleta = false;
    try {
        $status = statusAssinaturasDocumento($pdo, $documentoId);
        if ($status['completo'] && $status['solicitante_id'] && $status['solicitante_id'] !== $adminId
            && function_exists('createAdminNotificationForRequerimento')) {
            createAdminNotificationForRequerimento($pdo, $requerimentoId, 'coassinatura_concluida', [
                'destinatario_admin_id' => $status['solicitante_id'],
                'link_url' => 'visualizar_documento.php?requerimento_id=' . $requerimentoId,
            ]);
            $coassinaturaCompleta = true;
        }
    } catch (Throwable $e) {
    }

    coRespostaJson(['success' => true, 'hash' => $novoHash, 'completo' => $coassinaturaCompleta]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($arquivoSubstituido && is_string($caminhoBackup) && is_file($caminhoBackup)) {
        if (!copy($caminhoBackup, $caminhoFisico)) {
            error_log('[coassinar] ATENÇÃO: falha ao restaurar backup do PDF ' . $documentoId);
        }
    }
    foreach ([$caminhoTemporario, $caminhoBackup] as $temporario) {
        if (is_string($temporario) && is_file($temporario)) @unlink($temporario);
    }
    $erro = respostaErroAssinatura($e, '[coassinar] Erro requerimento #' . ($requerimentoId ?? '?'));
    coRespostaJson($erro['payload'], $erro['status']);
}
