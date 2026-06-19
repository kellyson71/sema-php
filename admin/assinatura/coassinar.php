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

if (function_exists('verificaLogin')) {
    verificaLogin();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$documentoId    = trim($_POST['documento_id']    ?? '');
$requerimentoId = (int) ($_POST['requerimento_id'] ?? 0);
$adminId        = $_SESSION['admin_id'] ?? null;
$pinAssinatura  = $_POST['pin_assinatura'] ?? '';

if (!$documentoId || !$requerimentoId || !$adminId) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes ou sessão expirada.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Buscar fonte do documento (HTML + caminho do PDF)
    $stmtFonte = $pdo->prepare("SELECT * FROM documentos_fonte WHERE documento_id = ?");
    $stmtFonte->execute([$documentoId]);
    $fonte = $stmtFonte->fetch(PDO::FETCH_ASSOC);

    if (!$fonte) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Este documento foi criado antes da atualização de co-assinatura e não suporta múltiplas assinaturas.']);
        exit;
    }

    // 2. Verificar se o admin já assinou este documento
    $stmtCheck = $pdo->prepare("SELECT id FROM assinaturas_digitais WHERE documento_id = ? AND assinante_id = ?");
    $stmtCheck->execute([$documentoId, $adminId]);
    if ($stmtCheck->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Você já assinou este documento.']);
        exit;
    }

    // 3. Buscar dados do admin atual
    $stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmtAdmin->execute([$adminId]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Administrador não encontrado.']);
        exit;
    }

    // 3b. Assinatura avançada: PIN é opcional. Se fornecido e a chave existir,
    //     usa RSA (nível avançado). Caso contrário, registra como nível simples.
    $servicoAvancada = new AssinaturaAvancadaService($pdo);
    $hashConteudo    = AssinaturaAvancadaService::hashConteudo($fonte['conteudo_html']);
    $assinaturaRsa   = null;
    $pinAssinatura   = trim($pinAssinatura);
    if ($pinAssinatura !== '' && $servicoAvancada->temChave((int) $adminId)) {
        try {
            $assinaturaRsa = $servicoAvancada->assinar((int) $adminId, $pinAssinatura, $hashConteudo);
        } catch (RuntimeException $eRsa) {
            if ($eRsa->getMessage() === 'PIN_INCORRETO') {
                if ($pdo->inTransaction()) $pdo->rollBack();
                ob_clean();
                echo json_encode(['success' => false, 'code' => 'pin_incorreto',
                    'error' => 'PIN de assinatura incorreto.']);
                exit;
            }
            error_log('[coassinar] Erro RSA: ' . $eRsa->getMessage());
            // Falha inesperada → continua sem componente RSA
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
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Arquivo PDF original não encontrado no servidor.']);
        exit;
    }

    // 7. Regravar o PDF com assinantes acumulados (sobrescreve in-place)
    require_once __DIR__ . '/gerar_pdf.php';

    $numero_processo = "Processo_#{$requerimentoId}";
    $verifyUrl = rtrim(BASE_URL, '/') . '/verificar';
    $sigPos = ($fonte['sig_pos_x'] !== null && $fonte['sig_pos_y'] !== null)
        ? ['x' => (float) $fonte['sig_pos_x'], 'y' => (float) $fonte['sig_pos_y']]
        : null;

    emitirParecerAssinado($fonte['conteudo_html'], $assinantes, $numero_processo, 'F', $caminhoFisico, [
        'verify_url' => $verifyUrl,
        'doc_codigo' => $documentoId,
        'sig_pos'    => $sigPos,
    ]);

    if (!file_exists($caminhoFisico)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Falha ao regravar o PDF com a nova assinatura.']);
        exit;
    }

    $novoHash = hash_file('sha256', $caminhoFisico);

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

    // 8b. O PDF foi regravado: atualizar hash_documento de TODAS as linhas
    //     deste documento_id, senão as assinaturas anteriores apontariam para
    //     um hash de arquivo que não existe mais (falso "documento adulterado").
    $pdo->prepare("UPDATE assinaturas_digitais SET hash_documento = ? WHERE documento_id = ?")
        ->execute([$novoHash, $documentoId]);

    $pdo->commit();

    // 9. Marcar solicitações pendentes como assinadas
    try {
        $pdo->prepare("
            UPDATE solicitacoes_assinatura
            SET status = 'assinado', resolvido_em = NOW()
            WHERE documento_id = ? AND destinatario_id = ? AND status = 'pendente'
        ")->execute([$documentoId, $adminId]);
    } catch (Throwable $e) {
        // não bloquear se tabela não existir ou query falhar
    }

    // 10. Registrar no histórico
    $nomeAdmin = $admin['nome_completo'] ?: $admin['nome'];
    $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)")
        ->execute([$adminId, $requerimentoId, "Co-assinou digitalmente o documento: " . strtoupper($fonte['tipo_documento'] ?? 'DOCUMENTO')]);

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

    ob_clean();
    echo json_encode(['success' => true, 'hash' => $novoHash, 'completo' => $coassinaturaCompleta]);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[coassinar] Erro requerimento #' . ($requerimentoId ?? '?') . ': ' . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Erro ao processar co-assinatura. Tente novamente.']);
    exit;
}
