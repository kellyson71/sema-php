<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

// Conexão e Sessão (Caminhos Absolutos a partir da raiz)
$rootDir = dirname(__DIR__, 2); // Raiz (sema-php)
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php'; // admin/conexao.php
require_once $rootDir . '/includes/parecer_service.php';
require_once $rootDir . '/includes/pdf_sanitizer.php';
require_once $rootDir . '/includes/assinatura_avancada_service.php';

// Validar login
if (function_exists('verificaLogin')) {
    verificaLogin();
}

function respostaJson(array $payload): void {
    ob_clean();
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$conteudo        = sanitizarHtmlParaPdf(trim($_POST['conteudo_parecer'] ?? ''));
$requerimento_id = trim($_POST['requerimento_id'] ?? '');
$salvar_banco    = filter_var($_POST['salvar_banco'] ?? false, FILTER_VALIDATE_BOOLEAN);
$template_salvo  = $_POST['template_salvo'] ?? 'Documento Eletrônico';

// Modo de assinatura: 'assinar' (padrão), 'sem_assinar', 'assinar_e_requisitar'
$modoAssinatura = $_POST['modo_assinatura'] ?? 'assinar';
if (!in_array($modoAssinatura, ['assinar', 'sem_assinar', 'assinar_e_requisitar'], true)) {
    $modoAssinatura = 'assinar';
}
$ehAssinaturaDigital = ($modoAssinatura !== 'sem_assinar');

// Posição customizada do bloco de assinatura (mm na última página), vinda do
// arrasto no preview do editor. Vazio = posição padrão (inferior-direito).
$sigPosX = isset($_POST['sig_pos_x']) && $_POST['sig_pos_x'] !== '' ? (float) $_POST['sig_pos_x'] : null;
$sigPosY = isset($_POST['sig_pos_y']) && $_POST['sig_pos_y'] !== '' ? (float) $_POST['sig_pos_y'] : null;
$sigPos  = ($sigPosX !== null && $sigPosY !== null) ? ['x' => $sigPosX, 'y' => $sigPosY] : null;

if ($salvar_banco) {
    header('Content-Type: application/json');
}

if (empty($conteudo)) {
    if ($salvar_banco) respostaJson(['success' => false, 'error' => 'O conteúdo do documento não pode estar vazio.']);
    die("ERRO: O conteúdo do documento não pode estar vazio.");
}

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    if ($salvar_banco) respostaJson(['success' => false, 'error' => 'Sessão expirada ou não encontrada.']);
    die("ERRO: Sessão expirada ou não encontrada.");
}

try {
    $stmt = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        if ($salvar_banco) respostaJson(['success' => false, 'error' => 'Administrador não encontrado.']);
        die("ERRO: Administrador não encontrado no banco.");
    }
} catch (Exception $e) {
    if ($salvar_banco) respostaJson(['success' => false, 'error' => 'Erro SQL: ' . $e->getMessage()]);
    die("ERRO SQL: " . $e->getMessage());
}

// ── Assinatura avançada: validar PIN e assinar o hash do conteúdo ──────────
// O RSA assina o hash do HTML-fonte (não do PDF) para que co-assinaturas
// futuras — que regravam o PDF — não invalidem esta assinatura.
$assinaturaRsa  = null;   // ['assinatura' => b64, 'chave_publica' => PEM]
$hashConteudo   = AssinaturaAvancadaService::hashConteudo($conteudo);
$servicoAvancada = new AssinaturaAvancadaService($pdo);

if ($ehAssinaturaDigital && $salvar_banco) {
    $pin = $_POST['pin_assinatura'] ?? '';
    try {
        $assinaturaRsa = $servicoAvancada->assinar((int) $admin_id, $pin, $hashConteudo);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'PIN_SETUP_REQUIRED') {
            respostaJson(['success' => false, 'code' => 'pin_setup_required',
                'error' => 'Você ainda não configurou seu PIN de assinatura. Configure-o para assinar digitalmente.']);
        }
        if ($e->getMessage() === 'PIN_INCORRETO') {
            respostaJson(['success' => false, 'code' => 'pin_incorreto',
                'error' => 'PIN de assinatura incorreto.']);
        }
        error_log('[processa_assinatura] Erro RSA: ' . $e->getMessage());
        respostaJson(['success' => false, 'error' => 'Falha na operação criptográfica de assinatura.']);
    }
}

// Preparar dados do assinante para o Carimbo TCPDF
$assinante = [
    'nome' => ($admin['nome_completo'] ?: ($admin['nome'] ?: $_SESSION['admin_nome'])),
    'cargo' => ($admin['cargo'] ?: 'Administrador(a)'),
    'cpf' => ($admin['cpf'] ?? ''),
    'matricula' => ($admin['matricula_portaria'] ?? ''),
    'data_hora' => date('d/m/Y \à\s H:i:s')
];

$numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

// Requerer a classe TCPDF estendida
require_once __DIR__ . '/gerar_pdf.php';

if ($salvar_banco && $requerimento_id) {
    try {
        // Diretório de Salvamento
        $dirDestino = dirname(__DIR__) . '/pareceres/' . $requerimento_id;
        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        // documento_id forte gerado ANTES do PDF (embutido nos metadados).
        $documentoId    = bin2hex(random_bytes(16));
        $verifyUrlPdf   = rtrim(BASE_URL, '/') . '/verificar';                   // exibido no bloco (curto)
        $verifyUrlAcesso = $verifyUrlPdf . '?id=' . $documentoId;                // retorno ao front

        $nomeArquivoBase = 'Parecer_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $numero_processo) . '_' . date('His') . '.pdf';
        $caminhoFisico   = $dirDestino . '/' . $nomeArquivoBase;
        $caminhoRelativo = 'pareceres/' . $requerimento_id . '/' . $nomeArquivoBase;

        $opcoesPdf = [
            'verify_url' => $ehAssinaturaDigital ? $verifyUrlPdf : '',
            'doc_codigo' => $documentoId,
            'sig_pos'    => $sigPos,
        ];

        // 1. Gerar e salvar fisicamente o PDF no disco "F"
        if ($modoAssinatura === 'sem_assinar') {
            $assinanteManual = array_merge($assinante, ['tipo' => 'manual']);
            emitirParecerAssinado($conteudo, $assinanteManual, $numero_processo, 'F', $caminhoFisico, $opcoesPdf);
        } else {
            emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'F', $caminhoFisico, $opcoesPdf);
        }

        if (!file_exists($caminhoFisico)) {
            respostaJson(['success' => false, 'error' => 'A biblioteca PDF falhou ao gravar o arquivo físico.']);
        }

        // 2. Metadados
        $hashDocumento = hash_file('sha256', $caminhoFisico);
        $nomeCurto_template = preg_replace('/\.html$/i', '', $template_salvo);

        $tipoAssinatura    = $ehAssinaturaDigital ? 'digital_sema' : 'sem_assinatura';
        $nivelAssinatura   = $ehAssinaturaDigital ? 'avancada' : 'sem_assinatura';
        $assinanteCpfReg   = $ehAssinaturaDigital ? $assinante['cpf'] : '';

        // 3. Persistência — assinatura_criptografada agora é a assinatura RSA
        //    real do admin sobre hash_conteudo (verificável com chave_publica).
        $stmt = $pdo->prepare("
            INSERT INTO assinaturas_digitais (
                documento_id, requerimento_id, tipo_documento, nome_arquivo,
                caminho_arquivo, hash_documento, hash_conteudo, assinante_id, assinante_nome,
                assinante_cpf, assinante_cargo, tipo_assinatura, nivel_assinatura, assinatura_visual,
                assinatura_criptografada, chave_publica, timestamp_assinatura, ip_assinante
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
            $documentoId,
            $requerimento_id,
            $nomeCurto_template,
            $nomeArquivoBase,
            $caminhoRelativo,
            $hashDocumento,
            $hashConteudo,
            $admin_id,
            $assinante['nome'],
            $assinanteCpfReg,
            $assinante['cargo'],
            $tipoAssinatura,
            $nivelAssinatura,
            '{}',
            $assinaturaRsa['assinatura'] ?? '',
            $assinaturaRsa['chave_publica'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // 4. Persistir HTML-fonte (base imutável das assinaturas) + posição do bloco
        $pdo->prepare("
            INSERT IGNORE INTO documentos_fonte
                (documento_id, requerimento_id, conteudo_html, tipo_documento, caminho_arquivo, criado_por_id, sig_pos_x, sig_pos_y)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$documentoId, $requerimento_id, $conteudo, $nomeCurto_template, $caminhoRelativo, $admin_id, $sigPosX, $sigPosY]);

        // 5. Histórico
        $acaoHistorico = match ($modoAssinatura) {
            'sem_assinar'          => "Gerou documento sem assinatura: " . strtoupper(str_replace('_', ' ', $nomeCurto_template)),
            'assinar_e_requisitar' => "Gerou e assinou eletronicamente (requisitou co-assinatura): " . strtoupper(str_replace('_', ' ', $nomeCurto_template)),
            default                => "Gerou e assinou eletronicamente o documento: " . strtoupper(str_replace('_', ' ', $nomeCurto_template)),
        };
        $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)")
            ->execute([$admin_id, $requerimento_id, $acaoHistorico]);

        // 6. Modo assinar_e_requisitar: criar solicitações (aceita múltiplos destinatários)
        if ($modoAssinatura === 'assinar_e_requisitar') {
            $destinatarios = [];
            if (!empty($_POST['coassinatura_destinatarios']) && is_array($_POST['coassinatura_destinatarios'])) {
                $destinatarios = array_map('intval', $_POST['coassinatura_destinatarios']);
            } elseif (!empty($_POST['coassinatura_destinatario_id'])) {
                $destinatarios = [(int) $_POST['coassinatura_destinatario_id']];
            }
            $destinatarios = array_values(array_unique(array_filter($destinatarios, fn($d) => $d > 0 && $d !== (int) $admin_id)));

            $mensagemCoAs = trim($_POST['coassinatura_mensagem'] ?? '');
            foreach ($destinatarios as $destinatarioId) {
                $pdo->prepare("
                    INSERT INTO solicitacoes_assinatura
                        (documento_id, requerimento_id, solicitante_id, destinatario_id, mensagem, status)
                    VALUES (?, ?, ?, ?, ?, 'pendente')
                    ON DUPLICATE KEY UPDATE
                        mensagem = VALUES(mensagem),
                        status   = 'pendente',
                        criado_em = NOW()
                ")->execute([$documentoId, $requerimento_id, $admin_id, $destinatarioId, $mensagemCoAs]);

                // Notificação DIRECIONADA ao destinatário, com link para a tela dedicada
                if (function_exists('createAdminNotificationForRequerimento')) {
                    createAdminNotificationForRequerimento($pdo, $requerimento_id, 'coassinatura_solicitada', [
                        'destinatario_admin_id' => $destinatarioId,
                        'link_url' => 'coassinar_documento.php?documento_id=' . $documentoId,
                    ]);
                }
            }
        }

        respostaJson([
            'success'      => true,
            'url_pdf'      => $caminhoRelativo,
            'nome_arquivo' => $nomeArquivoBase,
            'documento_id' => $documentoId,
            'verify_url'   => $ehAssinaturaDigital ? $verifyUrlAcesso : null,
        ]);

    } catch (Exception $e) {
        error_log("Erro em processa_assinatura no fluxo JSON -> " . $e->getMessage());
        respostaJson(['success' => false, 'error' => 'Falha crítica ao registrar documento: ' . $e->getMessage()]);
    }

} else {
    // Fluxo Antigo Direto (Força Download no Navegador) — sem registro, sem QR
    if ($modoAssinatura === 'sem_assinar') {
        emitirParecerAssinado($conteudo, [], $numero_processo, 'D');
    } else {
        emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'D');
    }
    exit;
}
