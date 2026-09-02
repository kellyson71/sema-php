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
require_once $rootDir . '/includes/assinatura_workflow_helpers.php';
require_once $rootDir . '/includes/admin_notifications.php';

function respostaJson(array $payload, int $httpStatus = 200): void {
    http_response_code($httpStatus);
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson(['success' => false, 'code' => 'method_not_allowed', 'error' => 'Método inválido.'], 405);
}

if (!assinaturaSessaoAdminAtiva($pdo)) {
    respostaJson([
        'success' => false,
        'code' => 'session_expired',
        'error' => 'Sua sessão realmente expirou. Entre novamente para continuar.',
    ], 401);
}

$csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
$csrfSessao = (string) ($_SESSION['csrf_token'] ?? '');
if ($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
    header('Content-Type: application/json');
    respostaJson(['success' => false, 'error' => 'A sessão de assinatura expirou. Recarregue a página e tente novamente.']);
}

$conteudo        = sanitizarHtmlParaPdf(trim($_POST['conteudo_parecer'] ?? ''));
$requerimento_id = trim($_POST['requerimento_id'] ?? '');
$salvar_banco    = filter_var($_POST['salvar_banco'] ?? false, FILTER_VALIDATE_BOOLEAN);
$template_salvo  = $_POST['template_salvo'] ?? 'Documento Eletrônico';
$nomeCurto_template = preg_replace('/\.html$/i', '', basename((string) $template_salvo));
$numeroDocumentoInformado = trim((string) ($_POST['numero_documento'] ?? ''));
// Retificação: documento_id da versão que está sendo corrigida. Quando vem
// preenchido, a reemissão mantém o número original e aposenta a versão anterior.
$retificaDocumentoId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['retifica_documento_id'] ?? ''));
$motivoRetificacao = mb_substr(trim((string) ($_POST['motivo_retificacao'] ?? '')), 0, 500);

// Modo de assinatura: 'assinar' (padrão), 'sem_assinar', 'assinar_e_requisitar'
$modoAssinatura = $_POST['modo_assinatura'] ?? 'assinar';
if (!in_array($modoAssinatura, ['assinar', 'sem_assinar', 'assinar_e_requisitar'], true)) {
    $modoAssinatura = 'assinar';
}
$ehAssinaturaDigital = ($modoAssinatura !== 'sem_assinar');
$tipoAssinanteManual = trim((string) ($_POST['assinatura_manual_tipo'] ?? 'secretario'));
$nomeAssinanteManual = (string) ($_POST['assinatura_manual_nome'] ?? '');
$cargoAssinanteManual = (string) ($_POST['assinatura_manual_cargo'] ?? '');

if ($salvar_banco) {
    header('Content-Type: application/json');
}

if ($salvar_banco) {
    try {
        validarCsrfAssinatura($_POST['csrf_token'] ?? null);
    } catch (Throwable $e) {
        $erro = respostaErroAssinatura($e, '[processa_assinatura] CSRF');
        respostaJson($erro['payload'], $erro['status']);
    }
}

if (empty($conteudo)) {
    if ($salvar_banco) respostaJson(['success' => false, 'error' => 'O conteúdo do documento não pode estar vazio.']);
    die("ERRO: O conteúdo do documento não pode estar vazio.");
}

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    if ($salvar_banco) respostaJson([
        'success' => false,
        'code' => 'session_expired',
        'error' => 'Sua sessão realmente expirou. Entre novamente para continuar.',
    ], 401);
    die("ERRO: Sessão expirada ou não encontrada.");
}
// PDF e criptografia podem demorar; a partir daqui a sessão é somente leitura.
// Liberar o lock evita que autosave/outra aba pareça ter encerrado a sessão.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Co-assinatura: resolver os destinatários ANTES de gravar qualquer coisa. Antes a
// lista era lida só na hora de criar as solicitações, depois do documento já assinado
// — uma lista vazia gerava uma assinatura individual silenciosa, sem ninguém para
// co-assinar e sem erro para o usuário.
$destinatarios = [];
if (!empty($_POST['coassinatura_destinatarios']) && is_array($_POST['coassinatura_destinatarios'])) {
    $destinatarios = array_map('intval', $_POST['coassinatura_destinatarios']);
} elseif (!empty($_POST['coassinatura_destinatario_id'])) {
    $destinatarios = [(int) $_POST['coassinatura_destinatario_id']];
}
$destinatarios = array_values(array_unique(array_filter($destinatarios, fn($d) => $d > 0 && $d !== (int) $admin_id)));

if ($modoAssinatura === 'assinar_e_requisitar' && empty($destinatarios)) {
    $erroDestinatarios = 'Selecione ao menos um servidor para co-assinar o documento.';
    if ($salvar_banco) respostaJson(['success' => false, 'error' => $erroDestinatarios]);
    die("ERRO: " . $erroDestinatarios);
}

if ($modoAssinatura === 'assinar_e_requisitar') {
    $marcadores = implode(',', array_fill(0, count($destinatarios), '?'));
    $stmtDestinatarios = $pdo->prepare("SELECT id FROM administradores
        WHERE ativo = 1 AND id IN ($marcadores)");
    $stmtDestinatarios->execute($destinatarios);
    $destinatariosValidos = array_map('intval', $stmtDestinatarios->fetchAll(PDO::FETCH_COLUMN));
    sort($destinatariosValidos);
    $destinatariosEsperados = $destinatarios;
    sort($destinatariosEsperados);
    if ($destinatariosValidos !== $destinatariosEsperados) {
        if ($salvar_banco) respostaJson([
            'success' => false,
            'code' => 'invalid_cosigners',
            'error' => 'Um dos destinatários selecionados não está mais ativo. Atualize a página.',
        ], 409);
        die('ERRO: destinatário de coassinatura inválido.');
    }
}

try {
    $stmt = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        if ($salvar_banco) respostaJson(['success' => false, 'error' => 'Administrador não encontrado.']);
        die("ERRO: Administrador não encontrado no banco.");
    }
} catch (Throwable $e) {
    if ($salvar_banco) {
        $erro = respostaErroAssinatura($e, '[processa_assinatura] Consulta do assinante');
        respostaJson($erro['payload'], $erro['status']);
    }
    die("ERRO SQL: " . $e->getMessage());
}

// ── Assinatura avançada: validar PIN e assinar o hash do conteúdo ──────────
// O RSA assina o hash do HTML-fonte (não do PDF) para que co-assinaturas
// futuras — que regravam o PDF — não invalidem esta assinatura.
$assinaturaRsa  = null;   // ['assinatura' => b64, 'chave_publica' => PEM]
$hashConteudo   = AssinaturaAvancadaService::hashConteudo($conteudo);
$servicoAvancada = new AssinaturaAvancadaService($pdo);

if ($ehAssinaturaDigital && $salvar_banco) {
    try {
        $pin = trim($_POST['pin_assinatura'] ?? '');

        if ($pin === '') {
            respostaJson([
                'success' => false,
                'code' => 'credential_required',
                'error' => 'Informe sua credencial para confirmar a assinatura.',
            ], 422);
        }

        if ($servicoAvancada->temChave((int) $admin_id)) {
            // Conta com chave avançada confirma com o PIN que cifra a chave privada.
            try {
                $assinaturaRsa = $servicoAvancada->assinar((int) $admin_id, $pin, $hashConteudo);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'PIN_INCORRETO') {
                    respostaJson(['success' => false, 'code' => 'credential_invalid',
                        'error' => 'PIN de assinatura incorreto.'], 422);
                }
                throw $e;
            }
        } else {
            // Conta sem chave avançada confirma com a senha de login.
            $stSenha = $pdo->prepare("SELECT senha FROM administradores WHERE id = ?");
            $stSenha->execute([$admin_id]);
            $hashSenha = $stSenha->fetchColumn();
            if (!$hashSenha || !password_verify($pin, $hashSenha)) {
                respostaJson(['success' => false, 'code' => 'credential_invalid',
                    'error' => 'Senha de acesso incorreta.'], 422);
            }
        }
    } catch (Throwable $e) {
        $erro = respostaErroAssinatura($e, '[processa_assinatura] Validação da credencial');
        respostaJson($erro['payload'], $erro['status']);
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

$assinanteManual = null;
if ($modoAssinatura === 'sem_assinar') {
    try {
        $secretarioManual = $tipoAssinanteManual === 'secretario'
            ? buscarSecretarioAtivoUnico($pdo)
            : null;
        $assinanteManual = resolverAssinanteManual(
            $tipoAssinanteManual,
            array_merge(['id' => (int) $admin_id], $admin),
            $secretarioManual,
            $nomeAssinanteManual,
            $cargoAssinanteManual
        );
    } catch (Throwable $e) {
        if ($salvar_banco) {
            $erro = respostaErroAssinatura($e, '[processa_assinatura] Assinante manual');
            respostaJson($erro['payload'], $erro['status']);
        }
        throw $e;
    }
}

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
        ];

        // 1. Gerar e salvar fisicamente o PDF no disco "F"
        if ($modoAssinatura === 'sem_assinar') {
            emitirParecerAssinado($conteudo, array_merge($assinanteManual, ['tipo' => 'manual']), $numero_processo, 'F', $caminhoFisico, $opcoesPdf);
        } else {
            emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'F', $caminhoFisico, $opcoesPdf);
        }

        if (!file_exists($caminhoFisico)) {
            respostaJson(['success' => false, 'error' => 'A biblioteca PDF falhou ao gravar o arquivo físico.']);
        }

        // Guarda o HTML que gerou este PDF. É o que permite reabrir um documento
        // já assinado para retificação — sem isto, só sobra o PDF, e o texto
        // teria que ser redigitado do zero.
        @file_put_contents($dirDestino . '/' . $documentoId . '.html', $conteudo);

        // 2. Metadados
        $hashDocumento = hash_file('sha256', $caminhoFisico);
        $tipoAssinatura    = $ehAssinaturaDigital ? 'digital_sema' : 'sem_assinatura';
        $nivelAssinatura   = $ehAssinaturaDigital
            ? ($assinaturaRsa !== null ? 'avancada' : 'simples')
            : 'sem_assinatura';
        $assinanteCpfReg   = $ehAssinaturaDigital ? $assinante['cpf'] : '';
        $metadadosAssinatura = $modoAssinatura === 'sem_assinar'
            ? json_encode([
                'gerado_por_id' => (int) $admin_id,
                'assinatura_manual_tipo' => $tipoAssinanteManual,
                'assinatura_manual' => $assinanteManual,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $pdo->beginTransaction();

        // A numeração é independente por tipo/ano. Uma alteração manual maior
        // também avança a sequência: se este documento for 60, o próximo será 61.
        if (DocumentoRegras::templateNumerado($nomeCurto_template)) {
            $numeroInterpretado = DocumentoRegras::interpretarNumero($numeroDocumentoInformado);
            if (!$numeroInterpretado || $numeroInterpretado['numero'] < 1) {
                throw new RuntimeException('Informe o número do documento no formato número/ano.');
            }

            // O número já pertence a este mesmo processo e tipo de documento?
            // Então isto é uma retificação: o número continua o mesmo e a versão
            // anterior é aposentada. Número de outro processo segue bloqueado.
            $stmtNumero = $pdo->prepare('SELECT id, requerimento_id, documento_id FROM document_numbers
                WHERE template_key = ? AND ano = ? AND numero = ? LIMIT 1');
            $stmtNumero->execute([$nomeCurto_template, $numeroInterpretado['ano'], $numeroInterpretado['numero']]);
            $numeroExistente = $stmtNumero->fetch(PDO::FETCH_ASSOC);

            if ($numeroExistente && (int) $numeroExistente['requerimento_id'] === (int) $requerimento_id) {
                $documentoSubstituido = (string) $numeroExistente['documento_id'];
                $pdo->prepare('UPDATE document_numbers SET documento_id = ?, criado_por_id = ?, criado_em = NOW()
                    WHERE id = ?')
                    ->execute([$documentoId, $admin_id, $numeroExistente['id']]);

                if ($documentoSubstituido !== '' && $documentoSubstituido !== $documentoId) {
                    $pdo->prepare('UPDATE assinaturas_digitais
                        SET substituido_por_documento_id = ?, substituido_em = NOW(),
                            substituido_por_admin_id = ?, motivo_substituicao = ?
                        WHERE documento_id = ? AND substituido_por_documento_id IS NULL')
                        ->execute([$documentoId, $admin_id, ($motivoRetificacao !== '' ? $motivoRetificacao : null), $documentoSubstituido]);
                }
            } else {
                $pdo->prepare('INSERT INTO document_numbers
                    (template_key, ano, numero, requerimento_id, documento_id, criado_por_id)
                    VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$nomeCurto_template, $numeroInterpretado['ano'], $numeroInterpretado['numero'],
                        $requerimento_id, $documentoId, $admin_id]);
            }

            $pdo->prepare('INSERT INTO document_number_sequences (template_key, ano, ultimo_numero)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE ultimo_numero = GREATEST(ultimo_numero, VALUES(ultimo_numero))')
                ->execute([$nomeCurto_template, $numeroInterpretado['ano'], $numeroInterpretado['numero']]);
        }

        // Retificação pedida explicitamente (reabrindo um documento assinado):
        // aposenta a versão anterior mesmo em documento sem numeração própria.
        if ($retificaDocumentoId !== '' && $retificaDocumentoId !== $documentoId) {
            $pdo->prepare('UPDATE assinaturas_digitais
                SET substituido_por_documento_id = ?, substituido_em = NOW(),
                    substituido_por_admin_id = ?, motivo_substituicao = ?
                WHERE documento_id = ? AND requerimento_id = ? AND substituido_por_documento_id IS NULL')
                ->execute([$documentoId, $admin_id, ($motivoRetificacao !== '' ? $motivoRetificacao : null),
                    $retificaDocumentoId, $requerimento_id]);
        }

        // 3. Persistência — assinatura_criptografada agora é a assinatura RSA
        //    real do admin sobre hash_conteudo (verificável com chave_publica).
        $stmt = $pdo->prepare("
            INSERT INTO assinaturas_digitais (
                documento_id, requerimento_id, tipo_documento, nome_arquivo,
                caminho_arquivo, hash_documento, hash_conteudo, assinante_id, assinante_nome,
                assinante_cpf, assinante_cargo, tipo_assinatura, nivel_assinatura, assinatura_visual,
                assinatura_criptografada, chave_publica, timestamp_assinatura, ip_assinante, metadados_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
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
            $_SERVER['REMOTE_ADDR'] ?? null,
            $metadadosAssinatura,
        ]);

        // 4. Persistir HTML-fonte (base imutável das assinaturas). A posição do
        //    carimbo não é mais persistida: ela é derivada do próprio PDF na
        //    hora de gerar, sempre no rodapé da última folha real.
        $pdo->prepare("
            INSERT IGNORE INTO documentos_fonte
                (documento_id, requerimento_id, conteudo_html, tipo_documento, caminho_arquivo, criado_por_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$documentoId, $requerimento_id, $conteudo, $nomeCurto_template, $caminhoRelativo, $admin_id]);

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

            }
        }

        $pdo->commit();

        // Notificações ficam fora da transação do documento: o helper legado
        // pode validar/criar sua estrutura e DDL não pode provocar commit
        // implícito enquanto documento, solicitação e histórico são gravados.
        if ($modoAssinatura === 'assinar_e_requisitar') {
            foreach ($destinatarios as $destinatarioId) {
                try {
                    createAdminNotificationForRequerimento($pdo, (int) $requerimento_id, 'coassinatura_solicitada', [
                        'destinatario_admin_id' => $destinatarioId,
                        'link_url' => 'coassinar_documento.php?documento_id=' . $documentoId,
                    ]);
                } catch (Throwable $e) {
                    error_log('[processa_assinatura] Solicitação criada, mas a notificação falhou: ' . $e->getMessage());
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

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!empty($caminhoFisico) && is_file($caminhoFisico)) @unlink($caminhoFisico);
        if (strpos($e->getMessage(), 'uq_document_number') !== false) {
            respostaJson([
                'success' => false,
                'code' => 'document_number_conflict',
                'error' => 'Este número já foi usado neste tipo de documento e ano. Escolha outro número.',
            ], 409);
        }
        $erro = respostaErroAssinatura($e, '[processa_assinatura] Falha ao registrar documento');
        respostaJson($erro['payload'], $erro['status']);
    }

} else {
    // Fluxo Antigo Direto (Força Download no Navegador) — sem registro, sem QR
    if ($modoAssinatura === 'sem_assinar') {
        emitirParecerAssinado($conteudo, array_merge($assinanteManual, ['tipo' => 'manual']), $numero_processo, 'D');
    } else {
        emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'D');
    }
    exit;
}
