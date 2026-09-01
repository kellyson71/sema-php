<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once '../includes/email_service.php';
require_once '../includes/pagamento_helpers.php';
require_once '../includes/pendencia_helpers.php';
require_once '../includes/notas_internas_helpers.php';
require_once '../includes/admin_notifications.php';
require_once '../includes/coassinatura_helper.php';
require_once '../includes/documento_regras.php';
require_once '../tipos_alvara.php';
verificaLogin();

// CSRF — este arquivo ainda não era coberto pelo token do portal público
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function adminPostCsrfValido(): bool
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
ensureAdminNotificationTables($pdo);

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: requerimentos.php");
    exit;
}

$id = (int)$_GET['id'];

// Marcar como visualizado — registrar no histórico apenas na 1ª vez
$stmtCheckVis = $pdo->prepare("SELECT visualizado FROM requerimentos WHERE id = ?");
$stmtCheckVis->execute([$id]);
$eraVisualizadoAntes = (int)$stmtCheckVis->fetchColumn();

$stmtVisualizado = $pdo->prepare("UPDATE requerimentos SET visualizado = 1 WHERE id = ?");
$stmtVisualizado->execute([$id]);

if ($eraVisualizadoAntes === 0) {
    $stmtHist = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
    $stmtHist->execute([$_SESSION['admin_id'], $id, "Visualizou o requerimento pela primeira vez"]);
}

// Função para buscar dados do requerimento
function buscarDadosRequerimento($pdo, $id)
{
    $stmt = $pdo->prepare("
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
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Buscar dados do requerimento PRIMEIRO
$requerimento = buscarDadosRequerimento($pdo, $id);

if (!$requerimento) {
    header("Location: requerimentos.php");
    exit;
}

// Processar atualização de status
$mensagem = '';
$mensagemTipo = '';

$flash = getMensagem();
if ($flash) {
    $mensagem = $flash['texto'];
    $mensagemTipo = $flash['tipo'];
}

// Editar os dados técnicos do processo mantendo o valor original para consulta.
$camposEditaveisProcesso = [
    'endereco_objetivo', 'area_construcao', 'numero_pavimentos', 'area_construida',
    'area_lote', 'area_total_terreno', 'area_remanescente', 'tipo_edificacao',
    'responsavel_tecnico_nome', 'responsavel_tecnico_registro', 'responsavel_tecnico_tipo_documento',
    'responsavel_tecnico_numero', 'responsavel_tecnico_email', 'responsavel_tecnico_telefone',
    'especificacao', 'cadastro_imobiliario', 'matricula_imovel',
    'inicio_obra', 'termino_obra', 'alvara_construcao_numero', 'habite_uso', 'habite_pavimento',
    'habite_tipo_construcao', 'habite_padrao', 'eng_fiscal_nome', 'eng_fiscal_registro',
    'ctf_numero', 'licenca_anterior_numero', 'publicacao_diario_oficial',
    'tipo_estudo_ambiental', 'possui_estudo_ambiental', 'notificado_fiscal_obras', 'observacoes'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dados_processo'])) {
    $csrfEdit = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfEdit)) {
        setMensagem('danger', 'Sessão expirada. Recarregue a página e tente novamente.');
        header("Location: visualizar_requerimento.php?id={$id}&tab=informacoes");
        exit;
    }

    try {
        $stmtAtual = $pdo->prepare('SELECT * FROM requerimentos WHERE id = ?');
        $stmtAtual->execute([$id]);
        $dadosAtuais = $stmtAtual->fetch(PDO::FETCH_ASSOC);
        if (!$dadosAtuais) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $alteracoes = [];
        foreach ($camposEditaveisProcesso as $campo) {
            if (!array_key_exists($campo, $_POST) || !array_key_exists($campo, $dadosAtuais)) {
                continue;
            }
            $novo = trim((string) $_POST[$campo]);
            if (in_array($campo, ['possui_estudo_ambiental', 'notificado_fiscal_obras'], true)) {
                $novo = $novo === '' ? null : (int) $novo;
            } elseif ($novo === '') {
                $novo = null;
            }
            $antigo = $dadosAtuais[$campo];
            $antigoComparacao = $antigo === null ? '' : (string) $antigo;
            $novoComparacao = $novo === null ? '' : (string) $novo;
            if ($antigoComparacao !== $novoComparacao) {
                $alteracoes[$campo] = [$antigo, $novo];
            }
        }

        if ($alteracoes) {
            $pdo->beginTransaction();
            $sets = [];
            $params = [];
            foreach ($alteracoes as $campo => [$antigo, $novo]) {
                $sets[] = "`{$campo}` = ?";
                $params[] = $novo;
                $stmtEdicao = $pdo->prepare('INSERT INTO requerimento_edicoes (requerimento_id, admin_id, campo, valor_original, valor_novo) VALUES (?, ?, ?, ?, ?)');
                $stmtEdicao->execute([$id, $_SESSION['admin_id'], $campo, $antigo, $novo]);
            }
            $params[] = $id;
            $pdo->prepare('UPDATE requerimentos SET ' . implode(', ', $sets) . ', data_atualizacao = NOW() WHERE id = ?')->execute($params);

            $rotulosEdicao = [
                'endereco_objetivo' => 'endereço', 'area_construcao' => 'área de construção',
                'numero_pavimentos' => 'pavimentos', 'area_construida' => 'área construída',
                'area_lote' => 'área do lote', 'area_total_terreno' => 'área total do terreno',
                'area_remanescente' => 'área remanescente', 'tipo_edificacao' => 'tipo de edificação',
                'responsavel_tecnico_nome' => 'responsável técnico', 'responsavel_tecnico_registro' => 'registro profissional',
                'responsavel_tecnico_tipo_documento' => 'tipo de documento técnico', 'responsavel_tecnico_numero' => 'número do documento técnico',
                'responsavel_tecnico_email' => 'e-mail do responsável técnico', 'responsavel_tecnico_telefone' => 'telefone do responsável técnico',
                'especificacao' => 'especificação', 'cadastro_imobiliario' => 'cadastro imobiliário',
                'matricula_imovel' => 'matrícula do imóvel (RGI)', 'alvara_construcao_numero' => 'alvará de construção anterior',
                'habite_uso' => 'uso do imóvel (habite-se)', 'habite_pavimento' => 'pavimentos (habite-se)',
                'habite_tipo_construcao' => 'tipo de construção (habite-se)', 'habite_padrao' => 'padrão construtivo (habite-se)',
                'inicio_obra' => 'início da obra', 'termino_obra' => 'término da obra',
                'ctf_numero' => 'CTF', 'licenca_anterior_numero' => 'licença anterior',
                'publicacao_diario_oficial' => 'publicação em diário oficial',
                'tipo_estudo_ambiental' => 'tipo de estudo ambiental', 'possui_estudo_ambiental' => 'possui estudo ambiental',
                'notificado_fiscal_obras' => 'notificado pelo fiscal de obras',
                'observacoes' => 'observações'
            ];
            $nomes = array_map(static fn ($campo) => $rotulosEdicao[$campo] ?? str_replace('_', ' ', $campo), array_keys($alteracoes));
            $stmtHist = $pdo->prepare('INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)');
            $stmtHist->execute([$_SESSION['admin_id'], $id, 'Editou dados do processo: ' . implode(', ', $nomes)]);
            $pdo->commit();
            setMensagem('success', 'Dados do processo atualizados. Os valores originais continuam disponíveis na edição.');
        } else {
            setMensagem('info', 'Nenhuma alteração foi identificada.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setMensagem('danger', 'Não foi possível atualizar os dados: ' . $e->getMessage());
    }
    header("Location: visualizar_requerimento.php?id={$id}&tab=informacoes");
    exit;
}

// Edição rápida inline: um campo por vez, com histórico do valor original.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_campo_processo'])) {
    $csrfInline = $_POST['csrf_token'] ?? '';
    $campoInline = (string) ($_POST['campo'] ?? '');
    $valorInline = trim((string) ($_POST['valor'] ?? ''));
    $mapaCamposInline = [
        'protocolo' => ['requerimentos', 'protocolo', 'id'],
        'tipo_alvara' => ['requerimentos', 'tipo_alvara', 'id'],
        'endereco_objetivo' => ['requerimentos', 'endereco_objetivo', 'id'],
        'requerente_nome' => ['requerentes', 'nome', 'requerente_id'],
        'requerente_email' => ['requerentes', 'email', 'requerente_id'],
        'requerente_cpf_cnpj' => ['requerentes', 'cpf_cnpj', 'requerente_id'],
        'requerente_telefone' => ['requerentes', 'telefone', 'requerente_id'],
        'proprietario_nome' => ['proprietarios', 'nome', 'proprietario_id'],
        'proprietario_cpf_cnpj' => ['proprietarios', 'cpf_cnpj', 'proprietario_id'],
        'tipo_edificacao' => ['requerimentos', 'tipo_edificacao', 'id'],
        'area_construcao' => ['requerimentos', 'area_construcao', 'id'],
        'area_construida' => ['requerimentos', 'area_construida', 'id'],
        'numero_pavimentos' => ['requerimentos', 'numero_pavimentos', 'id'],
        'area_lote' => ['requerimentos', 'area_lote', 'id'],
        'area_total_terreno' => ['requerimentos', 'area_total_terreno', 'id'],
        'area_remanescente' => ['requerimentos', 'area_remanescente', 'id'],
        'especificacao' => ['requerimentos', 'especificacao', 'id'],
        'cadastro_imobiliario' => ['requerimentos', 'cadastro_imobiliario', 'id'],
        'matricula_imovel' => ['requerimentos', 'matricula_imovel', 'id'],
        'alvara_construcao_numero' => ['requerimentos', 'alvara_construcao_numero', 'id'],
        'habite_uso' => ['requerimentos', 'habite_uso', 'id'],
        'habite_pavimento' => ['requerimentos', 'habite_pavimento', 'id'],
        'habite_tipo_construcao' => ['requerimentos', 'habite_tipo_construcao', 'id'],
        'habite_padrao' => ['requerimentos', 'habite_padrao', 'id'],
        'inicio_obra' => ['requerimentos', 'inicio_obra', 'id'],
        'termino_obra' => ['requerimentos', 'termino_obra', 'id'],
        'responsavel_tecnico_nome' => ['requerimentos', 'responsavel_tecnico_nome', 'id'],
        'responsavel_tecnico_registro' => ['requerimentos', 'responsavel_tecnico_registro', 'id'],
        'responsavel_tecnico_tipo_documento' => ['requerimentos', 'responsavel_tecnico_tipo_documento', 'id'],
        'responsavel_tecnico_numero' => ['requerimentos', 'responsavel_tecnico_numero', 'id'],
        'responsavel_tecnico_email' => ['requerimentos', 'responsavel_tecnico_email', 'id'],
        'responsavel_tecnico_telefone' => ['requerimentos', 'responsavel_tecnico_telefone', 'id'],
        'ctf_numero' => ['requerimentos', 'ctf_numero', 'id'],
        'licenca_anterior_numero' => ['requerimentos', 'licenca_anterior_numero', 'id'],
        'publicacao_diario_oficial' => ['requerimentos', 'publicacao_diario_oficial', 'id'],
        'localizacao_google_maps' => ['requerimentos', 'localizacao_google_maps', 'id'],
        'enquadramento_atividade' => ['requerimentos', 'enquadramento_atividade', 'id'],
        'tipo_estudo_ambiental' => ['requerimentos', 'tipo_estudo_ambiental', 'id'],
        'possui_estudo_ambiental' => ['requerimentos', 'possui_estudo_ambiental', 'id'],
        'notificado_fiscal_obras' => ['requerimentos', 'notificado_fiscal_obras', 'id'],
    ];
    try {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfInline)) {
            throw new RuntimeException('Sessão expirada.');
        }
        if (!isset($mapaCamposInline[$campoInline])) {
            throw new RuntimeException('Campo não permitido.');
        }
        if ($campoInline === 'requerente_email' && ($valorInline === '' || strlen($valorInline) > 191 || !filter_var($valorInline, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('Informe um endereço de e-mail válido para o cidadão.');
        }
        [$tabelaInline, $colunaInline, $chaveInline] = $mapaCamposInline[$campoInline];
        $idAlvoInline = $chaveInline === 'id' ? $id : (int) ($requerimento[$chaveInline] ?? 0);
        if (!$idAlvoInline) {
            throw new RuntimeException('Registro relacionado não encontrado.');
        }
        $stmtInline = $pdo->prepare("SELECT `{$colunaInline}` FROM `{$tabelaInline}` WHERE id = ?");
        $stmtInline->execute([$idAlvoInline]);
        $originalAtualInline = $stmtInline->fetchColumn();
        $stmtInline = $pdo->prepare("UPDATE `{$tabelaInline}` SET `{$colunaInline}` = ? WHERE id = ?");
        $stmtInline->execute([$valorInline !== '' ? $valorInline : null, $idAlvoInline]);

        $stmtOriginalInline = $pdo->prepare('SELECT valor_original FROM requerimento_edicoes WHERE requerimento_id = ? AND campo = ? ORDER BY id ASC LIMIT 1');
        $stmtOriginalInline->execute([$id, $campoInline]);
        $valorBaseInline = $stmtOriginalInline->fetchColumn();
        if ($valorBaseInline === false) {
            $valorBaseInline = $originalAtualInline;
        }
        $stmtInline = $pdo->prepare('INSERT INTO requerimento_edicoes (requerimento_id, admin_id, campo, valor_original, valor_novo) VALUES (?, ?, ?, ?, ?)');
        $stmtInline->execute([$id, $_SESSION['admin_id'], $campoInline, $valorBaseInline, $valorInline !== '' ? $valorInline : null]);
        $stmtInline = $pdo->prepare('INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)');
        $stmtInline->execute([$_SESSION['admin_id'], $id, "Editou {$campoInline} inline (original: " . ($valorBaseInline ?: 'Não informado') . ")"]);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'valor' => $valorInline, 'original' => $valorBaseInline], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Processar marcar como não lido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_nao_lido'])) {
    try {
        $stmt = $pdo->prepare("UPDATE requerimentos SET visualizado = 0 WHERE id = ?");
        $stmt->execute([$id]);

        // Registrar no histórico de ações
        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, "Marcou o requerimento como não lido"]);

        // Redirecionar para a lista de requerimentos com mensagem de sucesso
        header("Location: requerimentos.php?success=nao_lido");
        exit;
    } catch (PDOException $e) {
        $mensagem = "Erro ao marcar como não lido: " . $e->getMessage();
        $mensagemTipo = "danger";
    }
}

// Processar envio manual de boleto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_boleto_pagamento'])) {
    $instrucoesBoleto = trim($_POST['instrucoes_boleto'] ?? '');
    $arquivoBoleto = $_FILES['boleto_pdf'] ?? null;
    $arquivoFoiEnviado = $arquivoBoleto && ($arquivoBoleto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

    if (!adminPostCsrfValido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif (!$arquivoFoiEnviado) {
        $mensagem = "Anexe o PDF do boleto para prosseguir.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            $pagamentoAtual = buscarPagamentoRequerimento($pdo, $id);
            if ($pagamentoAtual) {
                $stmt = $pdo->prepare("
                    UPDATE requerimento_pagamentos
                    SET instrucoes = ?, enviado_em = NOW(), admin_envio_id = ?,
                        comprovante_enviado_em = NULL, data_atualizacao = NOW()
                    WHERE requerimento_id = ?
                ");
                $stmt->execute([
                    $instrucoesBoleto ?: null,
                    $_SESSION['admin_id'],
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO requerimento_pagamentos (requerimento_id, instrucoes, enviado_em, admin_envio_id)
                    VALUES (?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $id,
                    $instrucoesBoleto ?: null,
                    $_SESSION['admin_id']
                ]);
            }

            if ($arquivoFoiEnviado) {
                $salvouArquivo = salvarDocumentoPagamento($pdo, $id, $requerimento['protocolo'], $arquivoBoleto, 'boleto_pagamento_admin');
                if ($salvouArquivo === false) {
                    throw new RuntimeException('Não foi possível salvar o PDF do boleto. Envie um arquivo PDF válido.');
                }
            }

            $emailService = new EmailService();
            $emailEnviado = $emailService->enviarEmailBoleto(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $requerimento['protocolo'],
                $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'])),
                gerarUrlPagamento($id, $requerimento['protocolo']),
                $instrucoesBoleto,
                $id
            );

            if (!$emailEnviado) {
                throw new RuntimeException('O e-mail com o boleto não foi aceito pelo provedor. Nada foi alterado no processo. Confira o endereço do cidadão e tente novamente.');
            }

            // As mudanças operacionais só acontecem após o provedor aceitar a
            // mensagem. Assim uma falha não apaga o comprovante anterior.
            removerDocumentoPorCampo($pdo, $id, 'comprovante_pagamento_boleto');

            $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Aguardando boleto', aguardando_acao = 'boleto_pendente', comprovante_pagamento = NULL, data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("
                INSERT INTO requerimento_pagamento_historico (requerimento_id, documento_id, instrucoes, admin_envio_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $salvouArquivo['documento_id'] ?? null,
                $instrucoesBoleto ?: null,
                $_SESSION['admin_id'],
            ]);

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $id, "Enviou nova versão do boleto para pagamento"]);
            createAdminNotificationForRequerimento($pdo, $id, 'boleto_enviado');

            $pdo->commit();

            $requerimento = buscarDadosRequerimento($pdo, $id);
            $mensagem = "Boleto enviado para {$requerimento['requerente_email']}.";
            $mensagemTipo = "success";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = "Erro ao enviar boleto: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar solicitação de complementação (reabertura do formulário para o cidadão)
$linkPendenciaGerado = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_complementacao'])) {
    $tituloPendencia = trim($_POST['titulo_pendencia'] ?? '');
    $descricaoPendencia = trim($_POST['descricao_pendencia'] ?? '');
    $csrfEnviado = $_POST['csrf_token'] ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfEnviado)) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif ($tituloPendencia === '' || $descricaoPendencia === '') {
        $mensagem = "Informe o título e a descrição do que está faltando.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO requerimento_pendencias (requerimento_id, titulo, descricao, admin_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id, $tituloPendencia, $descricaoPendencia, $_SESSION['admin_id']]);
            $pendenciaId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Aguardando complementação', aguardando_acao = 'pendencia_aberta', data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $id, "Solicitou complementação ao requerente: " . $tituloPendencia]);

            $linkPendenciaGerado = gerarUrlPendencia($pendenciaId, $requerimento['protocolo']);

            $emailService = new EmailService();
            $emailEnviado = $emailService->enviarEmailPendencia(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $requerimento['protocolo'],
                $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'])),
                $tituloPendencia . "\n\n" . $descricaoPendencia,
                $id,
                $linkPendenciaGerado
            );

            if (!$emailEnviado) {
                throw new RuntimeException('O e-mail de complementação não foi aceito pelo provedor. A solicitação não foi aberta. Confira o endereço do cidadão e tente novamente.');
            }

            $pdo->commit();

            $mensagem = "Solicitação de complementação enviada para {$requerimento['requerente_email']}.";
            $mensagemTipo = "success";
            setMensagem($mensagemTipo, $mensagem);
            header("Location: visualizar_requerimento.php?id=$id");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = "Erro ao solicitar complementação: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Aceitar uma complementação (o requerente já respondeu e está ok)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aceitar_pendencia'])) {
    $pendenciaId = (int) ($_POST['pendencia_id'] ?? 0);
    $pendenciaAlvo = $pendenciaId ? buscarPendencia($pdo, $pendenciaId) : null;
    if (!$pendenciaAlvo || (int) $pendenciaAlvo['requerimento_id'] !== $id) {
        $mensagem = "Complementação não encontrada.";
        $mensagemTipo = "danger";
    } else {
        resolverPendencia($pdo, $pendenciaId, false);
        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, "Aceitou a complementação: " . $pendenciaAlvo['titulo']]);
        $mensagem = "Complementação aceita.";
        $mensagemTipo = "success";
    }
}

// Marcar uma complementação como resolvida manualmente (fora do link, ex. telefone/presencial)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolver_pendencia_manual'])) {
    $pendenciaId = (int) ($_POST['pendencia_id'] ?? 0);
    $pendenciaAlvo = $pendenciaId ? buscarPendencia($pdo, $pendenciaId) : null;
    if (!$pendenciaAlvo || (int) $pendenciaAlvo['requerimento_id'] !== $id) {
        $mensagem = "Complementação não encontrada.";
        $mensagemTipo = "danger";
    } else {
        resolverPendencia($pdo, $pendenciaId, true);
        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, "Marcou como resolvida manualmente a complementação: " . $pendenciaAlvo['titulo']]);
        $mensagem = "Complementação marcada como resolvida.";
        $mensagemTipo = "success";
    }
}

// Pedir novamente: reabre uma complementação já respondida/aceita, criando uma
// nova pendência a partir da anterior (mesmo fluxo de solicitar_complementacao)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reabrir_pendencia'])) {
    $pendenciaAnteriorId = (int) ($_POST['pendencia_id'] ?? 0);
    $descricaoPendencia = trim($_POST['descricao_pendencia'] ?? '');
    $pendenciaAnterior = $pendenciaAnteriorId ? buscarPendencia($pdo, $pendenciaAnteriorId) : null;

    if (!adminPostCsrfValido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif (!$pendenciaAnterior || (int) $pendenciaAnterior['requerimento_id'] !== $id) {
        $mensagem = "Complementação não encontrada.";
        $mensagemTipo = "danger";
    } elseif ($descricaoPendencia === '') {
        $mensagem = "Descreva o que ainda falta antes de pedir novamente.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $novaPendenciaId = reabrirPendencia($pdo, $pendenciaAnteriorId, $pendenciaAnterior['titulo'], $descricaoPendencia, $_SESSION['admin_id']);
            $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Aguardando complementação', aguardando_acao = 'pendencia_aberta', data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $id, "Pediu novamente a complementação: " . $pendenciaAnterior['titulo']]);
            $linkPendenciaGerado = gerarUrlPendencia($novaPendenciaId, $requerimento['protocolo']);
            $emailService = new EmailService();
            $emailEnviado = $emailService->enviarEmailPendencia(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $requerimento['protocolo'],
                $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'])),
                $pendenciaAnterior['titulo'] . "\n\n" . $descricaoPendencia,
                $id,
                $linkPendenciaGerado
            );
            if (!$emailEnviado) {
                throw new RuntimeException('O novo e-mail de complementação não foi aceito pelo provedor. A reabertura foi cancelada. Confira o endereço do cidadão e tente novamente.');
            }
            $pdo->commit();
            $mensagem = "Nova solicitação enviada para {$requerimento['requerente_email']}.";
            $mensagemTipo = "success";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = "Erro ao reabrir complementação: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Adicionar observação interna do processo (chat / anotações da equipe — só a equipe vê)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['adicionar_nota_interna']) || isset($_POST['salvar_nota_interna']))) {
    $notaTexto = trim($_POST['nota_interna_texto'] ?? '');
    if (!adminPostCsrfValido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif ($notaTexto === '') {
        $mensagem = "Escreva algo antes de enviar a observação.";
        $mensagemTipo = "danger";
    } else {
        adicionarNotaInterna($pdo, $id, $notaTexto, (int) $_SESSION['admin_id']);
        $mensagem = "Observação interna adicionada com sucesso.";
        $mensagemTipo = "success";
        setMensagem($mensagemTipo, $mensagem);
        header("Location: visualizar_requerimento.php?id={$id}&tab=pendencias#card-observacoes-internas");
        exit;
    }
}

// Excluir observação interna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_nota_interna'])) {
    $notaId = (int) ($_POST['nota_id'] ?? 0);
    $isAdminGeral = in_array($_SESSION['admin_nivel'] ?? '', ['admin', 'admin_geral'], true);
    if (!adminPostCsrfValido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif ($notaId <= 0) {
        $mensagem = "Observação inválida para exclusão.";
        $mensagemTipo = "danger";
    } else {
        $ok = excluirNotaInterna($pdo, $notaId, $id, (int) $_SESSION['admin_id'], $isAdminGeral);
        if ($ok) {
            $mensagem = "Observação interna removida.";
            $mensagemTipo = "success";
        } else {
            $mensagem = "Você não tem permissão para remover esta observação.";
            $mensagemTipo = "danger";
        }
        setMensagem($mensagemTipo, $mensagem);
        header("Location: visualizar_requerimento.php?id={$id}&tab=pendencias#card-observacoes-internas");
        exit;
    }
}

// Processar indeferimento de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['indeferir_processo'])) {
    $motivoIndeferimento = trim($_POST['motivo_indeferimento']);
    $orientacoesAdicionais = trim($_POST['orientacoes_adicionais']);

    if (!adminPostCsrfValido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif (empty($motivoIndeferimento)) {
        $mensagem = "É necessário informar o motivo do indeferimento.";
        $mensagemTipo = "danger";
    } elseif (strlen($motivoIndeferimento) < 10) {
        $mensagem = "O motivo do indeferimento deve ter pelo menos 10 caracteres.";
        $mensagemTipo = "danger";
    } else {
        try {
            $emailService = new EmailService();
            $email_enviado = $emailService->enviarEmailIndeferimento(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $requerimento['protocolo'],
                $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'])),
                $motivoIndeferimento,
                $orientacoesAdicionais,
                $id
            );

            if ($email_enviado) {
                try {
                    $pdo->beginTransaction();

                    // Criar observações combinadas
                    $observacoesCombinadas = "PROCESSO INDEFERIDO\n\nMotivo: " . $motivoIndeferimento;
                    if (!empty($orientacoesAdicionais)) {
                        $observacoesCombinadas .= "\n\nOrientações: " . $orientacoesAdicionais;
                    }

                    // Atualizar status para "Indeferido" automaticamente
                    // Se o indeferimento aconteceu no Setor 1, o processo passa a ficar visível para o Setor 2
                    $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Indeferido', aguardando_acao = 'concluido', setor_atual = IF(setor_atual = 'setor1', 'setor2', setor_atual), observacoes = ?, data_atualizacao = NOW() WHERE id = ?");
                    $stmt->execute([$observacoesCombinadas, $id]);

                    // Registrar no histórico de ações
                    $acao = "Indeferiu o processo e enviou e-mail de notificação. Motivo: {$motivoIndeferimento}";
                    $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

                    createAdminNotificationForRequerimento($pdo, $id, 'indeferido');

                    $pdo->commit();

                    // Recarregar dados do requerimento para refletir as mudanças
                    $requerimento = buscarDadosRequerimento($pdo, $id);

                    $mensagem = "Processo indeferido. A notificação foi enviada para {$requerimento['requerente_email']}.";
                    $mensagemTipo = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $mensagem = "E-mail enviado, mas houve erro ao atualizar o status: " . $e->getMessage();
                    $mensagemTipo = "warning";
                }
            } else {
                $mensagem = "Erro ao enviar email. Verifique as configurações de email.";
                $mensagemTipo = "danger";
            }
        } catch (Throwable $e) {
            $mensagem = "Erro ao enviar email: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar arquivamento de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arquivar_processo'])) {
    $motivoArquivamento = trim($_POST['motivo_arquivamento']);

    if (empty($motivoArquivamento)) {
        $mensagem = "É necessário informar o motivo do arquivamento.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // Buscar todos os dados do requerimento com relacionamentos
            $stmt = $pdo->prepare("
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
            $stmt->execute([$id]);
            $dadosCompletos = $stmt->fetch();

            if (!$dadosCompletos) {
                throw new Exception("Requerimento não encontrado.");
            }

            // Inserir na tabela de arquivados
            $stmt = $pdo->prepare("
                INSERT INTO requerimentos_arquivados (
                    requerimento_id, protocolo, tipo_alvara, requerente_id, proprietario_id,
                    endereco_objetivo, status, observacoes, data_envio, data_atualizacao,
                    admin_arquivamento, motivo_arquivamento, requerente_nome, requerente_email,
                    requerente_cpf_cnpj, requerente_telefone, proprietario_nome, proprietario_cpf_cnpj
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dadosCompletos['id'],
                $dadosCompletos['protocolo'],
                $dadosCompletos['tipo_alvara'],
                $dadosCompletos['requerente_id'],
                $dadosCompletos['proprietario_id'],
                $dadosCompletos['endereco_objetivo'],
                $dadosCompletos['status'],
                $dadosCompletos['observacoes'],
                $dadosCompletos['data_envio'],
                $dadosCompletos['data_atualizacao'],
                $_SESSION['admin_id'],
                $motivoArquivamento,
                $dadosCompletos['requerente_nome'],
                $dadosCompletos['requerente_email'],
                $dadosCompletos['requerente_cpf_cnpj'],
                $dadosCompletos['requerente_telefone'],
                $dadosCompletos['proprietario_nome'] ?? null,
                $dadosCompletos['proprietario_cpf_cnpj'] ?? null
            ]);

            // Registrar no histórico antes de deletar
            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $id, "Arquivou o processo - Motivo: {$motivoArquivamento}"]);

            // Remover das tabelas principais (cascade vai remover documentos e histórico)
            $stmt = $pdo->prepare("DELETE FROM requerimentos WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();

            // Redirecionar para a lista com mensagem de sucesso
            header("Location: requerimentos.php?success=arquivado");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = "Erro ao arquivar o processo: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar reabertura de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reabrir_processo'])) {
    $novoStatus = $_POST['novo_status'];
    $motivoReabertura = trim($_POST['motivo_reabertura']);

    try {
        $pdo->beginTransaction();

        // Atualizar status do requerimento
        $stmt = $pdo->prepare("UPDATE requerimentos SET status = ?, data_atualizacao = NOW() WHERE id = ?");
        $stmt->execute([$novoStatus, $id]);

        // Registrar no histórico de ações
        $acao = "Reabriu o processo finalizado e alterou status para '{$novoStatus}'";
        if (!empty($motivoReabertura)) {
            $acao .= " - Motivo: {$motivoReabertura}";
        }

        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

        $pdo->commit();

        // Recarregar dados do requerimento para refletir as mudanças
        $requerimento = buscarDadosRequerimento($pdo, $id);

        $mensagem = "Processo reaberto com sucesso! Status alterado para '{$novoStatus}'.";
        $mensagemTipo = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao reabrir o processo: " . $e->getMessage();
        $mensagemTipo = "danger";
    }
}

// Processar envio de email com protocolo oficial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_email_protocolo'])) {
    $protocolo_oficial = trim($_POST['protocolo_oficial']);

    if (!adminPostCsrfValido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente novamente.";
        $mensagemTipo = "danger";
    } elseif (empty($protocolo_oficial)) {
        $mensagem = "É necessário informar o protocolo oficial da prefeitura.";
        $mensagemTipo = "danger";
    } else {
        $jaFinalizado = strtolower((string) $requerimento['status']) === 'finalizado';
        try {
            $emailService = new EmailService();
            $email_enviado = $emailService->enviarEmailProtocoloOficial(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $protocolo_oficial,
                $id
            );

            if ($email_enviado) {
                try {
                    $pdo->beginTransaction();

                    // O protocolo oficial é uma comunicação intermediária. A entrega
                    // do documento final é a ação responsável por encerrar o processo.
                    $stmt = $pdo->prepare("UPDATE requerimentos SET protocolo_oficial = ?, data_atualizacao = NOW() WHERE id = ?");
                    $stmt->execute([$protocolo_oficial, $id]);

                    // Registrar no histórico de ações
                    $acao = ($jaFinalizado ? "Reenviou" : "Enviou") . " e-mail com protocolo oficial: {$protocolo_oficial}";
                    $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

                    $pdo->commit();

                    // Recarregar dados do requerimento para refletir as mudanças
                    $requerimento = buscarDadosRequerimento($pdo, $id);

                    $mensagem = ($jaFinalizado ? "Protocolo oficial reenviado" : "Protocolo oficial enviado") . " para {$requerimento['requerente_email']}. O envio não altera a etapa atual do processo.";
                    $mensagemTipo = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $mensagem = "E-mail enviado, mas houve erro ao atualizar os dados do protocolo: " . $e->getMessage();
                    $mensagemTipo = "warning";
                }
            } else {
                $mensagem = "Erro ao enviar email. Verifique as configurações de email.";
                $mensagemTipo = "danger";
            }
        } catch (Throwable $e) {
            $mensagem = "Erro ao enviar email: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && isset($_POST['observacoes'])) {
    $novoStatus = $_POST['status'];
    $observacoes = $_POST['observacoes'];

    if (!adminStatusPermitidoParaOperacao($novoStatus)) {
        $mensagem = "Este status não está disponível na operação atual.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // Atualizar status e observações do requerimento
            $stmt = $pdo->prepare("UPDATE requerimentos SET status = ?, observacoes = ?, data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$novoStatus, $observacoes, $id]);

            // Registrar no histórico de ações
            $acao = "Alterou status para '{$novoStatus}'";
            if (!empty($observacoes)) {
                $acao .= " com a observação: {$observacoes}";
            }

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

            $pdo->commit();

            // Recarregar dados do requerimento para refletir as mudanças
            $requerimento = buscarDadosRequerimento($pdo, $id);

            $mensagem = "Status do requerimento atualizado com sucesso!";
            $mensagemTipo = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = "Erro ao atualizar status: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar envio para fiscalizacao
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_fiscalizacao'])) {
    $mensagem = "O encaminhamento para fiscalização está desativado nesta versão.";
    $mensagemTipo = "warning";
}

// Processar envio para secretario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_secretario'])) {
    $mensagem = "O encaminhamento para assinatura do secretário está desativado nesta versão.";
    $mensagemTipo = "warning";
}

// Buscar documentos do requerimento
$stmt = $pdo->prepare("SELECT * FROM documentos WHERE requerimento_id = ? ORDER BY id");
$stmt->execute([$id]);
$documentos = $stmt->fetchAll();

$pagamento = buscarPagamentoRequerimento($pdo, $id);
$documentoBoleto = buscarDocumentoPorCampo($pdo, $id, 'boleto_pagamento_admin');
$documentoComprovanteBoleto = buscarDocumentoPorCampo($pdo, $id, 'comprovante_pagamento_boleto');

$pendencias = listarPendenciasRequerimento($pdo, $id);
$notasInternas = buscarNotasInternas($pdo, $id);
$notaInterna = !empty($notasInternas) ? end($notasInternas) : null;

$valoresOriginaisProcesso = [];
$stmtEdicoesProcesso = $pdo->prepare('SELECT campo, valor_original FROM requerimento_edicoes WHERE requerimento_id = ? ORDER BY id ASC');
$stmtEdicoesProcesso->execute([$id]);
foreach ($stmtEdicoesProcesso->fetchAll(PDO::FETCH_ASSOC) as $edicaoProcesso) {
    if (!array_key_exists($edicaoProcesso['campo'], $valoresOriginaisProcesso)) {
        $valoresOriginaisProcesso[$edicaoProcesso['campo']] = $edicaoProcesso['valor_original'];
    }
}
$temEdicoesProcesso = !empty($valoresOriginaisProcesso);

$pendenciasAbertas = array_filter($pendencias, static fn ($p) => in_array($p['status'], ['aberta', 'respondida'], true));
$cobrancaAtiva = $pagamento && empty($documentoComprovanteBoleto);
$acoesAtivasCount = count($pendenciasAbertas) + ($cobrancaAtiva ? 1 : 0);

// Buscar histórico de ações
$stmt = $pdo->prepare("
    SELECT ha.*, a.nome as admin_nome
    FROM historico_acoes ha
    LEFT JOIN administradores a ON ha.admin_id = a.id
    WHERE ha.requerimento_id = ?
    ORDER BY ha.data_acao DESC
");
$stmt->execute([$id]);
$historico = $stmt->fetchAll();

// Todos os e-mails já enviados (ou tentados) neste processo, de qualquer tipo —
// confirmação, aprovação, indeferimento, boleto, protocolo oficial, documento
// final etc. O preview usa o HTML já salvo em email_logs.mensagem, então não
// precisamos reconstruir nada por tipo (ver admin/preview_email.php).
$stmtEmailsProcesso = $pdo->prepare("
    SELECT id, email_destino, assunto, status, erro, data_envio, usuario_envio
    FROM email_logs
    WHERE requerimento_id = ?
    ORDER BY data_envio DESC
");
$stmtEmailsProcesso->execute([$id]);
$emailsProcesso = $stmtEmailsProcesso->fetchAll();

// Calcular tempo por etapa usando o histórico já buscado
$etapas        = calcularTemposEtapas($historico, $requerimento['data_envio']);
$tEnvio        = $etapas['tEnvio'];
$tVisualizacao = $etapas['tVisualizacao'];
$tPendente     = $etapas['tPendente'];
$tFiscalizacao = $etapas['tFiscalizacao'];
$tSecretario   = $etapas['tSecretario'];
$tConclusao    = $etapas['tConclusao'];

// Etapa 1: Envio → 1ª Visualização
$tempoAteVisualizacao = ($tVisualizacao && $tVisualizacao >= $tEnvio) ? ($tVisualizacao - $tEnvio) : null;

// Etapa 2: Em análise → Pendente (triagem)
$tempoAnalisePendente = ($tPendente && $tPendente >= $tEnvio) ? ($tPendente - $tEnvio) : null;

// Etapa 3: Pendente/Visualização → Fiscalização
$tempoAnaliseFiscalizacao = null;
if ($tFiscalizacao) {
    $inicio = $tPendente ?? $tVisualizacao ?? $tEnvio;
    if ($tFiscalizacao >= $inicio) $tempoAnaliseFiscalizacao = $tFiscalizacao - $inicio;
}

// Etapa 4: Fiscalização → Secretário
$tempoFiscalizacaoSecretario = null;
if ($tSecretario) {
    $inicio = $tFiscalizacao ?? $tPendente ?? $tVisualizacao ?? $tEnvio;
    if ($tSecretario >= $inicio) $tempoFiscalizacaoSecretario = $tSecretario - $inicio;
}

$tempoTotalProcesso = ($tConclusao && $tConclusao >= $tEnvio) ? ($tConclusao - $tEnvio) : null;

// Tempo em aberto (processo ainda não concluído)
$tempoEmAberto = ($tConclusao === null) ? (time() - $tEnvio) : null;

include 'header.php';
?>

<style>
    /* Design System - Cores Profissionais */
    :root {
        --primary-600: #059669;
        --primary-700: #047857;
        --primary-50: #ecfdf5;
        --primary-100: #d1fae5;

        --green-600: #059669;
        --green-700: #047857;
        --green-50: #ecfdf5;

        --blue-600: #2563eb;
        --red-600: #dc2626;
        --amber-500: #f59e0b;
        --amber-600: #d97706;
        --purple-600: #9333ea;

        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;

        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --transition: all 0.2s ease;
        --radius: 8px;
        --radius-sm: 6px;
    }

    /* Custom Select Styles */
    #template-select {
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-sm);
        padding: 0.875rem;
        font-size: 0.875rem;
        transition: var(--transition);
        background: white;
        box-shadow: var(--shadow-sm);
        width: 100%;
        cursor: pointer;
    }

    #template-select:focus {
        border-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1), var(--shadow-sm);
        outline: none;
    }

    #template-select optgroup {
        font-weight: 600;
        color: var(--gray-700);
        font-size: 0.875rem;
        background: var(--gray-50);
        padding: 8px;
    }

    #template-select option {
        padding: 10px;
        font-size: 0.875rem;
    }

    /* Componentes base atualizados */
    .card-modern,
    .modern-card {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        background: white;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: var(--transition);
    }

    .card-modern:hover,
    .modern-card:hover {
        box-shadow: var(--shadow-md);
    }

    .modern-card-header,
    .data-table-header {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modern-card-header h6,
    .data-table-header h6 {
        margin: 0;
        font-weight: 600;
        color: var(--gray-800);
        font-size: 0.875rem;
    }

    .modern-card-header .icon,
    .data-table-header .icon {
        color: var(--gray-500);
        font-size: 1rem;
    }

    /* Tabela de dados moderna */
    .data-row {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid var(--gray-100);
        transition: var(--transition);
    }

    .data-row:last-child {
        border-bottom: none;
    }

    .data-row:hover {
        background: var(--gray-50);
    }

    .data-label {
        font-weight: 500;
        color: var(--gray-600);
        min-width: 140px;
        font-size: 0.875rem;
    }

    .data-value {
        flex: 1;
        color: var(--gray-900);
        font-size: 0.875rem;
    }

    .data-actions {
        display: flex;
        gap: 0.25rem;
        margin-left: auto;
    }

    /* Botão de copiar atualizado */
    .copy-btn {
        background: white;
        border: 1px solid var(--gray-300);
        color: var(--gray-600);
        border-radius: var(--radius-sm);
        padding: 0.375rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
    }

    .copy-btn:hover {
        background: var(--gray-50);
        border-color: var(--gray-400);
        color: var(--gray-700);
    }

    .copy-btn.copied {
        background: var(--primary-50);
        border-color: var(--primary-600);
        color: var(--primary-600);
    }

    /* Cards de ação administrativa */
    .admin-action-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 1.5rem;
        height: 100%;
        transition: var(--transition);
    }

    .admin-action-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }

    /* Container para ações administrativas em layout vertical */
    .admin-actions-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Cards grandes para ações administrativas */
    .admin-action-card-large {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 2rem;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
        cursor: pointer;
    }

    .admin-action-card-large:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
        transform: translateY(-2px);
        background: var(--gray-50);
    }

    .admin-action-card-large.collapsed:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }

    .admin-action-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-200);
    }

    .admin-action-header i {
        font-size: 1.25rem;
        width: 24px;
        text-align: center;
    }

    .admin-action-header h6 {
        margin: 0;
        font-weight: 600;
        color: var(--gray-800);
        font-size: 1.125rem;
        letter-spacing: 0.025em;
    }

    /* Estilos para sistema de colapso */
    .collapsible-header {
        cursor: pointer;
        user-select: none;
        transition: var(--transition);
        position: relative;
    }

    .collapsible-header:hover {
        background: var(--gray-100);
        border-radius: var(--radius-sm);
    }

    .collapsible-header::after {
        content: 'Clique para fechar';
        position: absolute;
        right: 2rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: var(--gray-500);
        opacity: 0;
        transition: var(--transition);
    }

    .collapsible-header:hover::after {
        opacity: 1;
    }

    .collapse-icon {
        transition: var(--transition);
        color: var(--gray-500);
        font-size: 0.875rem;
    }

    .collapsible-card.collapsed .collapse-icon {
        transform: rotate(-90deg);
    }

    .collapsible-content {
        overflow: hidden;
        transition: all 0.3s ease;
        max-height: 1000px;
        opacity: 1;
    }

    .collapsible-card.collapsed .collapsible-content {
        max-height: 0;
        opacity: 0;
        padding-top: 0;
        padding-bottom: 0;
        margin-top: 0;
        margin-bottom: 0;
    }

    /* Formulários modernos */
    .modern-select,
    .modern-textarea,
    .modern-input,
    .form-control,
    .form-select {
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-sm);
        padding: 0.875rem;
        font-size: 0.875rem;
        transition: var(--transition);
        background: white;
        box-shadow: var(--shadow-sm);
    }

    .modern-select:focus,
    .modern-textarea:focus,
    .modern-input:focus,
    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1), var(--shadow-sm);
        outline: none;
        transform: translateY(-1px);
    }

    .form-label {
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        letter-spacing: 0.025em;
    }

    /* Botões de ação modernos */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.875rem 1.75rem;
        border-radius: var(--radius-sm);
        font-weight: 500;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        min-height: 2.75rem;
        box-shadow: var(--shadow-sm);
    }

    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-action-primary {
        background: var(--primary-600);
        color: white;
    }

    .btn-action-primary:hover {
        background: var(--primary-700);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
        color: white;
    }

    .btn-action-success {
        background: #059669;
        color: white;
    }

    .btn-action-success:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
    }

    /* Descrições de ação */
    .action-description {
        background: var(--gray-50);
        border-radius: var(--radius-sm);
        padding: 1rem;
        border-left: 4px solid var(--gray-300);
        margin-bottom: 1.25rem;
        box-shadow: var(--shadow-sm);
    }

    .action-description small {
        color: var(--gray-600);
        font-size: 0.875rem;
        line-height: 1.5;
    }

    /* Estilos para processos finalizados */
    .finalized-card {
        background: #f8f9fa !important;
        border-color: #dee2e6 !important;
        opacity: 0.8;
    }

    .finalized-header {
        background: #e9ecef !important;
        border-color: #dee2e6 !important;
    }

    .finalized-body {
        background: #f8f9fa !important;
    }

    .finalized-card .admin-action-card {
        background: #f1f3f4 !important;
        border-color: #dee2e6 !important;
        opacity: 0.6;
        pointer-events: none;
    }

    .finalized-card .btn-action {
        background: #6c757d !important;
        border-color: #6c757d !important;
        cursor: not-allowed;
        pointer-events: none;
    }

    .finalized-card input,
    .finalized-card select,
    .finalized-card textarea {
        background: #e9ecef !important;
        border-color: #ced4da !important;
        color: #6c757d !important;
        pointer-events: none;
    }

    .tox-tinymce {
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
    
    /* Estilo para o backdrop com blur para o modal de segurança */
    .modal-backdrop.show {
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        opacity: 0.8 !important;
        background-color: rgba(0, 0, 0, 0.6) !important;
    }
    
    #modalVerificacaoSeguranca .modal-content {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        border: none;
    }

    /* Estilos para processos indeferidos */
    .indeferido-card {
        background: #fef2f2 !important;
        border-color: #fecaca !important;
        opacity: 0.8;
    }

    .indeferido-header {
        background: #fee2e2 !important;
        border-color: #fecaca !important;
    }

    .indeferido-body {
        background: #fef2f2 !important;
    }

    .indeferido-card .admin-action-card {
        background: #fee2e2 !important;
        border-color: #fecaca !important;
        opacity: 0.6;
        pointer-events: none;
    }

    .indeferido-card .btn-action {
        background: #6c757d !important;
        border-color: #6c757d !important;
        cursor: not-allowed;
        pointer-events: none;
    }

    .indeferido-card input,
    .indeferido-card select,
    .indeferido-card textarea {
        background: #fee2e2 !important;
        border-color: #fecaca !important;
        color: #6c757d !important;
        pointer-events: none;
    }

    /* Estilo para card principal quando finalizado */
    .finalized-main-card {
        background: linear-gradient(45deg, #f8f9fa, #e9ecef) !important;
        border-color: #dee2e6 !important;
        opacity: 0.9;
    }

    /* Estilo para card principal quando indeferido */
    .indeferido-main-card {
        background: linear-gradient(45deg, #fef2f2, #fee2e2) !important;
        border-color: #fecaca !important;
        opacity: 0.9;
    }

    .finalized-status-badge {
        background: #6c757d !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 10px;
    }

    .indeferido-status-badge {
        background: #dc2626 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 10px;
    }

    /* Botões de ação para indeferimento */
    .btn-action-danger {
        background: var(--red-600);
        color: white;
    }

    .btn-action-danger:hover {
        background: #b91c1c;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
    }

    /* Botões modernos */
    .btn-modern {
        border-radius: var(--radius-sm);
        padding: 10px 20px;
        font-weight: 500;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    /* Botão de reabertura */
    .btn-reopen {
        background: linear-gradient(45deg, #f59e0b, #d97706);
        border: none;
        color: white;
        border-radius: var(--radius-sm);
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .btn-reopen:hover {
        background: linear-gradient(45deg, #d97706, #b45309);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        color: white;
    }

    /* Estilo especial para processo finalizado com reabertura */
    .finalized-reopen-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px dashed #dee2e6;
        border-radius: var(--radius);
        padding: 2rem;
        margin: 1rem 0;
    }

    /* Navegação de abas moderna */
    .nav-tabs .nav-link {
        padding: 0.75rem 1.25rem;
        margin-right: 0.25rem;
        transition: var(--transition);
        border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        color: var(--gray-600);
        font-weight: 500;
        border: 1px solid transparent;
        background: transparent;
        cursor: pointer;
    }

    /* Realce ao passar o mouse nos blocos de documento clicáveis */
    .doc-row-clickable:hover {
        background-color: #f8fafc;
    }
    .doc-card-clickable[data-viewer-url]:not([data-viewer-url=""]):hover {
        border-color: #1c4b36 !important;
        box-shadow: 0 3px 10px rgba(28,75,54,.12) !important;
    }

    /* CSS para assinatura */
    .signature-pad-container {
        display: flex;
        justify-content: center;
    }

    #signature-canvas {
        background-color: #fff;
        touch-action: none;
    }

    #signature-preview {
        background-color: #f8f9fa;
    }

    .nav-tabs .nav-link:hover {
        background: var(--gray-50);
        color: var(--gray-800);
        border-color: var(--gray-200) var(--gray-200) transparent;
    }

    .nav-tabs .nav-link.active {
        background: white;
        color: var(--primary-600);
        border-color: var(--gray-200) var(--gray-200) white;
        font-weight: 600;
        position: relative;
    }

    .nav-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary-600);
    }

    .nav-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        margin-left: 5px;
        border-radius: 999px;
        background: var(--gray-100, #f1f5f2);
        color: var(--gray-600, #66756d);
        font-size: .68rem;
        font-weight: 700;
    }

    .nav-tabs .nav-link.active .nav-tab-count {
        background: var(--primary-soft, #e5f2ea);
        color: var(--primary-600, #14532d);
    }

    .nav-tab-count-alert {
        background: #fce7e7;
        color: #b13232;
    }

    .nav-tabs .nav-link.active .nav-tab-count-alert {
        background: #fce7e7;
        color: #b13232;
    }

    .detail-actions-toolbar {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .detail-actions-primary {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .detail-actions-note {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1rem;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.92rem;
        font-weight: 600;
    }

    .detail-actions-secondary {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-top: 1rem;
        border-top: 1px solid var(--gray-200);
    }

    .detail-actions-toolbar .btn {
        border-radius: 12px;
        min-height: 42px;
        font-weight: 600;
        box-shadow: none !important;
    }

    .detail-actions-primary .btn {
        padding-inline: 1rem;
    }

    .detail-actions-highlight {
        border-radius: 16px;
        padding: 1rem;
        background: linear-gradient(135deg, #f5f9ff 0%, #eef4ff 100%);
        border: 1px solid #dbe7ff;
    }

    .documents-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 0.75rem;
    }

    .documents-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .documents-empty {
        padding: 1.5rem;
        border: 1px dashed var(--gray-300);
        border-radius: 12px;
        text-align: center;
        color: var(--gray-500);
        background: #fafafa;
    }

    /* Estilos específicos para o modal de pré-visualização de email */
    #emailPreviewModal .modal-dialog {
        max-width: 800px;
    }

    #emailPreviewModal .modal-header {
        background: linear-gradient(135deg, var(--primary-50), var(--gray-50));
        border-bottom: 2px solid var(--primary-100);
    }

    #emailPreviewModal .modal-title {
        color: var(--primary-700);
        font-weight: 600;
    }

    .email-preview-info {
        background: var(--gray-50);
        border-radius: var(--radius-sm);
        padding: 1rem;
    }

    .email-preview-info small {
        color: var(--gray-500);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
    }

    .email-preview-info strong {
        color: var(--gray-900);
        font-weight: 600;
    }

    #email-preview-content {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-sm);
        background: #f8f9fa;
        min-height: 200px;
    }

    /* Animação suave para os botões de pré-visualização */
    .btn-outline-info {
        border-color: #0ea5e9;
        color: #0ea5e9;
        transition: var(--transition);
    }

    .btn-outline-info:hover {
        background: #0ea5e9;
        border-color: #0ea5e9;
        color: white;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    /* Estilo para o botão de copiar conteúdo */
    .btn-primary {
        background: var(--primary-600);
        border-color: var(--primary-600);
    }

    .btn-primary:hover {
        background: var(--primary-700);
        border-color: var(--primary-700);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    /* Feedback visual para quando o conteúdo é copiado */
    .btn-success {
        background: #10b981 !important;
        border-color: #10b981 !important;
    }

    /* Melhorar a responsividade do modal */
    @media (max-width: 768px) {
        #emailPreviewModal .modal-dialog {
            max-width: 95%;
            margin: 1rem;
        }

        #emailPreviewModal .modal-body {
            padding: 0.75rem;
        }

        .email-preview-info .row {
            flex-direction: column;
        }

        .email-preview-info .col-md-6 {
            margin-bottom: 0.75rem;
        }
    }

    /* Estilos melhorados para os botões dos modais */
    .modal-footer .btn {
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
        min-width: 120px;
        font-size: 0.875rem;
    }

    .modal-footer .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .modal-footer .btn-outline-secondary {
        border-color: var(--gray-400);
        color: var(--gray-600);
    }

    .modal-footer .btn-outline-secondary:hover {
        background-color: var(--gray-100);
        border-color: var(--gray-500);
        color: var(--gray-700);
    }

    .modal-footer .btn-outline-info {
        border-color: #0ea5e9;
        color: #0ea5e9;
    }

    .modal-footer .btn-outline-info:hover {
        background-color: #0ea5e9;
        border-color: #0ea5e9;
        color: white;
    }

    .modal-footer .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border-color: #10b981;
        color: white;
    }

    .modal-footer .btn-success:hover {
        background: linear-gradient(135deg, #059669, #047857);
        border-color: #059669;
        color: white;
    }

    .modal-footer .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border-color: #ef4444;
        color: white;
    }

    .modal-footer .btn-danger:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border-color: #dc2626;
        color: white;
    }

    .modal-footer .btn-primary {
        background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
        border-color: var(--primary-600);
        color: white;
    }

    .modal-footer .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-700), #047857);
        border-color: var(--primary-700);
        color: white;
    }

    /* Melhorar o layout dos botões */
    .modal-footer .d-flex.gap-2 {
        gap: 0.75rem !important;
    }

         /* Responsividade para os botões */
     @media (max-width: 576px) {
         .modal-footer {
             flex-direction: column;
             gap: 0.75rem;
         }

         .modal-footer .d-flex.gap-2 {
             width: 100%;
             justify-content: center;
         }

         .modal-footer .btn {
             min-width: auto;
             flex: 1;
         }

         .detail-actions-primary,
         .detail-actions-secondary {
             flex-direction: column;
         }
     }

     /* Estilos para a mensagem tutorial discreta */
     .tutorial-message {
         animation: slideInDown 0.3s ease-out;
         opacity: 0.85;
         transition: opacity 0.3s ease;
     }

     .tutorial-message:hover {
         opacity: 1;
     }

     .tutorial-message .alert {
         border-radius: var(--radius-sm);
         box-shadow: 0 1px 3px rgba(0,0,0,0.05);
         background-color: #fafbfc;
         border: 1px solid #e1e5e9;
         font-size: 0.75rem;
     }

     .tutorial-message .btn-sm {
         font-size: 0.65rem;
         padding: 0.2rem 0.4rem;
         border-radius: var(--radius-sm);
         transition: var(--transition);
         border-width: 1px;
         min-height: auto;
     }

     .tutorial-message .btn-sm:hover {
         transform: none;
         box-shadow: none;
         background-color: #f8f9fa;
     }

     .tutorial-message .btn-close-sm {
         font-size: 0.65rem;
         opacity: 0.5;
         transition: opacity 0.2s ease;
     }

     .tutorial-message .btn-close-sm:hover {
         opacity: 0.8;
     }

     @keyframes slideInDown {
         from {
             opacity: 0;
             transform: translateY(-10px);
         }
         to {
             opacity: 1;
             transform: translateY(0);
         }
     }

     /* Responsividade para a mensagem tutorial */
     @media (max-width: 768px) {
         .tutorial-message .d-flex {
             flex-direction: column;
             align-items: stretch;
         }

         .tutorial-message .flex-grow-1 {
             flex-direction: column !important;
             align-items: stretch !important;
         }

         .tutorial-message .btn-sm {
             width: 100%;
             margin-bottom: 0.5rem;
             font-size: 0.7rem;
             padding: 0.3rem 0.5rem;
         }

         .tutorial-message .gap-1 {
             gap: 0.5rem !important;
         }

         .tutorial-message .alert {
             font-size: 0.7rem;
             padding: 0.5rem 0.75rem;
         }

         .tutorial-message .ms-2 {
             margin-left: 0 !important;
             margin-top: 0.5rem;
         }
     }

     /* ── Botões de ação do processo (sólidos e coesos) ── */
     .act-btn {
         display:inline-flex; align-items:center; justify-content:center; gap:7px;
         padding:9px 15px; border-radius:10px; font-size:.84rem; font-weight:600;
         border:none; cursor:pointer; text-decoration:none; color:#fff;
         transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
     }
     .act-btn:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.16); filter:brightness(1.05); color:#fff; }
     .act-btn:active { transform:translateY(0); box-shadow:0 2px 6px rgba(0,0,0,.12); }
     .act-btn i { font-size:.9em; }
     .act-go      { background:#15803d; }
     .act-go2     { background:#0f766e; }
     .act-back    { background:#b45309; }
     .act-neutral { background:#475569; }
     .act-info    { background:#0369a1; }
     .act-danger  { background:#b3261e; }
     .act-warning { background:#a16207; }
     /* Ação primária — Gerar Documento (destaque, largura total) */
     .act-gerar {
         width:100%; padding:13px 18px; font-size:.96rem; font-weight:700;
         background:linear-gradient(135deg, #1c4b36, #0d7f5f); color:#fff;
         box-shadow:0 5px 18px rgba(13,127,95,.34);
     }
     .act-gerar:hover { box-shadow:0 8px 24px rgba(13,127,95,.46); color:#fff; }
     .act-revisar {
         width:100%; padding:12px 18px; font-size:.93rem; font-weight:700;
         background:#0f766e; color:#fff; box-shadow:0 4px 14px rgba(15,118,110,.3);
     }
     .act-revisar:hover { box-shadow:0 6px 20px rgba(15,118,110,.42); color:#fff; }
</style>

<?php
// Verificar se o processo está finalizado ou indeferido
// "Reprovado" conta como indeferido pra tudo que é UI: admin/estatisticas.php já
// trata os dois como a mesma categoria de "falha" nas métricas, mas aqui a tela
// só olhava pro literal 'indeferido' — um processo Reprovado continuava sendo
// tratado como ativo (dias em aberto contando, painel de ações ativas, etc.).
$isFinalized = (strtolower($requerimento['status']) === 'finalizado');
$isIndeferido = in_array(strtolower($requerimento['status']), ['indeferido', 'reprovado'], true);
$isBlocked = $isFinalized || $isIndeferido;
$activeTab = $_GET['tab'] ?? 'informacoes';
$tabsPermitidas = ['informacoes', 'documentos', 'historico', 'pendencias'];
if (!in_array($activeTab, $tabsPermitidas, true)) {
    $activeTab = 'informacoes';
}
?>

<?php
// ---------- Dados do setor para stepper e painel ----------
$setorAtual    = $requerimento['setor_atual']    ?? 'setor1';
$aguardandoAcao = $requerimento['aguardando_acao'] ?? 'triagem_setor1';
$setorOrdem    = ['setor1' => 0, 'setor2' => 1, 'setor3' => 2];
$setorAtualIdx = $setorOrdem[$setorAtual] ?? 0;
$isProcessoConcluido = ($aguardandoAcao === 'concluido');
$acaoLabelsMap = [
    'triagem_setor1'  => 'Triagem pendente',
    'boleto_pendente' => 'Aguardando boleto',
    'analise_setor2'  => 'Em análise — Setor 2',
    'revisao_setor3'  => 'Revisão final — Setor 3',
    'envio_cidadao'   => 'Pronto para envio ao cidadão',
    'concluido'       => 'Processo concluído',
];
$acaoAtualLabel = $acaoLabelsMap[$aguardandoAcao] ?? $aguardandoAcao;
// Variáveis de setor — $nivelAtual e $isAdmin já são definidos por header.php (linha ~62)
// Definimos aqui como fallback caso chamadas antes do include
if (!isset($nivelAtual)) { $nivelAtual = $_SESSION['admin_nivel'] ?? 'operador'; }
if (!isset($isAdmin))    { $isAdmin    = in_array($nivelAtual, ['admin', 'admin_geral'], true); }
$isSetor2  = ($nivelAtual === 'fiscal'    || $isAdmin);
$isSetor3  = ($nivelAtual === 'secretario' || $isAdmin);
$isSetor1  = ($nivelAtual === 'analista'  || $isAdmin);
// Fiscal puro e secretário puro (sem privilégio admin_geral)
$isFiscalPuro     = ($nivelAtual === 'fiscal');
$isSecretarioPuro = ($nivelAtual === 'secretario');

// Quem pode movimentar o processo: o role dono do setor onde ele está (ou admin).
// Os botões eram renderizados só por $setorAtual, então um fiscal abrindo um
// processo da Triagem via botões que o handler recusava com "sem permissão".
$rolePorSetor    = ['setor1' => 'analista', 'setor2' => 'fiscal', 'setor3' => 'secretario'];
$roleDoSetor     = $rolePorSetor[$setorAtual] ?? 'analista';
$podeAgirNoSetor = $isAdmin || ($nivelAtual === $roleDoSetor);
// A entrega ao cidadão é a exceção: Triagem (Setor 1) e Fiscalização (Setor 2)
// entregam documento independente de onde o processo esteja parado. Movimentar o
// fluxo continua restrito ao setor dono. O handler repete essa regra.
$podeEntregarDocFinal = $isAdmin || in_array($nivelAtual, ['analista', 'fiscal'], true);
$labelSetorAtual = [
    'setor1' => 'Triagem Ambiental',
    'setor2' => 'Fiscalização de Obras',
    'setor3' => 'Revisão do Secretário',
][$setorAtual] ?? $setorAtual;
if (isset($_GET['error']) && $_GET['error'] === 'motivo_obrigatorio') {
    $mensagem = 'O motivo da devolução é obrigatório.';
    $mensagemTipo = 'danger';
}
if (isset($_GET['error']) && $_GET['error'] === 'sem_permissao') {
    $mensagem = 'Você não tem permissão para executar essa ação neste setor.';
    $mensagemTipo = 'danger';
}
if (isset($_GET['error']) && $_GET['error'] === 'erro_fluxo') {
    // Mostra a causa real gravada pelo handler; sem isso a ação falhava sem
    // nenhum aviso na tela e o operador repetia o envio indefinidamente.
    $detalhe = trim((string) ($_SESSION['fluxo_erro_msg'] ?? ''));
    unset($_SESSION['fluxo_erro_msg']);
    $mensagem = '❌ A ação não foi concluída' . ($detalhe !== '' ? ': ' . $detalhe : '. Tente novamente ou contate o suporte.');
    $mensagemTipo = 'danger';
}
if (isset($_GET['error']) && $_GET['error'] === 'dados_invalidos') {
    $mensagem = 'Dados inválidos na requisição. A ação não foi executada.';
    $mensagemTipo = 'danger';
}
if (isset($_GET['error']) && $_GET['error'] === 'acao_invalida') {
    $mensagem = 'Ação desconhecida. Nada foi alterado no processo.';
    $mensagemTipo = 'danger';
}
if (isset($_GET['success']) && $_GET['success'] === 'fluxo_atualizado') {
    if (($_GET['aviso'] ?? '') === 'email_falhou') {
        // O fluxo em si foi concluído — só o e-mail ao cidadão falhou. Sem este aviso,
        // a tela mostrava "sucesso" genérico e o operador não tinha como saber que o
        // cidadão não recebeu a notificação.
        $mensagem = '⚠️ Fluxo atualizado, mas o e-mail com o documento não pôde ser enviado ao cidadão. '
            . 'O documento continua acessível pelo link seguro; você pode reenviar pelo histórico de e-mails abaixo.';
        $mensagemTipo = 'warning';
    } else {
        $mensagem = '✅ Fluxo atualizado com sucesso.';
        $mensagemTipo = 'success';
    }
}

// Co-assinaturas pendentes para o admin logado neste requerimento
$_adminIdLogado = (int) ($_SESSION['admin_id'] ?? 0);
$stmtCoPend = $pdo->prepare("
    SELECT sa.documento_id, sa.mensagem, sa.criado_em,
           s.nome AS solicitante_nome
    FROM solicitacoes_assinatura sa
    JOIN administradores s ON s.id = sa.solicitante_id
    WHERE sa.requerimento_id = ? AND sa.destinatario_id = ? AND sa.status = 'pendente'
    ORDER BY sa.criado_em DESC
");
$stmtCoPend->execute([$id, $_adminIdLogado]);
$_coPendsNesteProcesso = $stmtCoPend->fetchAll(PDO::FETCH_ASSOC);

// Temporário: setor 2 continua podendo gerar/tratar documentos normalmente
// mesmo em processos já Finalizados/Indeferidos vindos do Setor 1.
// Além disso, o role dono do setor onde o processo está (analista no Setor 1,
// secretário no Setor 3) continua vendo a barra de ações — assim quem concluiu
// o processo diretamente na Triagem ainda consegue enviar o documento final ao
// cidadão, botão que só aparece na barra ativa.
$tratarComoAtivoParaSetor2 = $podeEntregarDocFinal || ($nivelAtual === $roleDoSetor);
$mostrarPainelEncerrado = $isBlocked && !$tratarComoAtivoParaSetor2;

// Banner contextual por setor (dica de próximo passo, exibida na barra de comando)
$bannerInfo = [
    'setor1' => ['icon'=>'fa-inbox','color'=>'#3762d9','bg'=>'#e8effd','text'=>'Este processo está na triagem. Gere o documento de abertura, encaminhe para fiscalização quando necessário ou finalize diretamente se não houver pendências.'],
    'setor2' => ['icon'=>'fa-microscope','color'=>'#14532d','bg'=>'#e3f3e8','text'=>'Este processo está em análise técnica. Gere ou assine o parecer técnico, encaminhe para revisão final ou finalize com o documento definitivo.'],
    'setor3' => ['icon'=>'fa-shield-halved','color'=>'#7e22ce','bg'=>'#f3e8ff','text'=>'Este processo está em revisão final. Revise os documentos, aprove e assine ou devolva ao Setor 2 com justificativa.'],
];
$bi = $bannerInfo[$setorAtual] ?? $bannerInfo['setor1'];
?>
<style>
/* ---- Stepper de setor ---- */
.setor-stepper { display:flex; align-items:center; gap:0; background:#fff; border:1px solid #e3e8e4; border-radius:14px; padding:14px 22px; margin-bottom:14px; flex-wrap:wrap; gap:4px; }
.stepper-step { display:flex; align-items:center; gap:8px; font-size:.82rem; font-weight:600; color:#9ca3af; padding:4px 10px; border-radius:8px; }
.stepper-step.done { color:#14532d; }
.stepper-step.active { background:#e6f2ea; color:#0f4425; font-weight:800; }
.stepper-step.done .step-dot { background:#14532d; }
.stepper-step.active .step-dot { background:#0f4425; box-shadow:0 0 0 3px rgba(20,83,45,.2); }
.step-dot { width:10px; height:10px; border-radius:50%; background:#d1d5db; flex-shrink:0; }
.stepper-sep { width:24px; height:2px; background:#e3e8e4; border-radius:2px; }
.stepper-sep.done { background:#14532d; }
.stepper-acao { margin-left:auto; font-size:.78rem; font-weight:700; color:#14532d; background:#e6f2ea; padding:4px 12px; border-radius:999px; }
.stepper-acao.boleto { background:#fff3dc; color:#b7791f; }
.stepper-acao.revisao { background:#f3e8ff; color:#7e22ce; }
.stepper-acao.concluido { background:#f1f5f0; color:#666; }

/* ---- Painel de ações do setor ---- */
.setor-action-panel { background:#fff; border:1px solid #e3e8e4; border-radius:14px; padding:16px 20px; margin-bottom:14px; display:flex; flex-wrap:wrap; align-items:center; gap:14px; }
.action-panel-label { font-size:.8rem; font-weight:700; color:#66756d; text-transform:uppercase; letter-spacing:.06em; flex-basis:100%; }
.btn-acao-primary { display:inline-flex; align-items:center; gap:8px; padding:9px 20px; border-radius:12px; background:#14532d; color:#fff; border:none; font-size:.88rem; font-weight:700; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-acao-primary:hover { background:#0f4425; color:#fff; }
.btn-acao-secondary { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:10px; background:#fff; color:#102117; border:1px solid #e3e8e4; font-size:.83rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all .15s; }
.btn-acao-secondary:hover { border-color:#14532d; color:#14532d; }
.btn-acao-danger { border-color:#fca5a5; color:#8f2222; }
.btn-acao-danger:hover { background:#fce8e8; border-color:#8f2222; }
.btn-acao-warning { border-color:#fde68a; color:#b7791f; }
.action-panel-sep { width:1px; height:30px; background:#e3e8e4; }
.aviso-inline { font-size:.78rem; background:#fff3dc; color:#92400e; padding:5px 12px; border-radius:8px; border:1px solid #fde68a; display:inline-flex; align-items:center; gap:6px; }
/* Modais de confirmação inline */
.acao-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:9000; align-items:center; justify-content:center; }
.acao-modal-backdrop.open { display:flex; }
.acao-modal { background:#fff; border-radius:18px; padding:28px; max-width:420px; width:100%; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.15); }
.acao-modal h3 { margin:0 0 10px; font-size:1.1rem; font-weight:800; }
.acao-modal p { margin:0 0 16px; color:#66756d; font-size:.88rem; }
.acao-modal textarea { width:100%; padding:10px; border:1px solid #e3e8e4; border-radius:10px; font-size:.85rem; resize:vertical; margin-bottom:14px; }
.acao-modal .modal-actions { display:flex; gap:10px; justify-content:flex-end; }
</style>

<!-- CAMINHO DO PROCESSO -->
<div class="container-fluid px-4 pt-3">
<style>
.caminho-bar { display:flex; align-items:center; gap:6px; background:#fff; border:1px solid #e3e8e4; border-radius:12px; padding:10px 18px; margin-bottom:12px; flex-wrap:wrap; }
.caminho-step { display:inline-flex; align-items:center; gap:6px; font-size:.78rem; font-weight:700; color:#9ca3af; padding:4px 8px; border-radius:6px; }
.caminho-step.done { color:#14532d; }
.caminho-step.active { background:#e6f2ea; color:#0f4425; }
.caminho-step .cdot { width:8px; height:8px; border-radius:50%; background:currentColor; flex-shrink:0; }
.caminho-sep { font-size:.72rem; color:#d1d5db; }
.caminho-sep.opt { color:#d1d5db; font-style:italic; font-size:.68rem; }
.caminho-opt-tag { font-size:.65rem; font-weight:700; color:#9ca3af; background:#f3f4f6; padding:1px 6px; border-radius:999px; margin-left:2px; }
.caminho-estado { margin-left:auto; font-size:.74rem; font-weight:700; padding:3px 10px; border-radius:999px; }
.caminho-estado.triagem  { background:#e8effd; color:#3762d9; }
.caminho-estado.boleto   { background:#fff3dc; color:#b7791f; }
.caminho-estado.analise  { background:#e3f3e8; color:#14532d; }
.caminho-estado.revisao  { background:#f3e8ff; color:#7e22ce; }
.caminho-estado.envio    { background:#e0f2fe; color:#0369a1; }
.caminho-estado.concluido{ background:#f1f5f0; color:#6b7280; }
/* Modais inline */
@keyframes fmBackdropIn  { from { background:rgba(0,0,0,0); } to { background:rgba(0,0,0,.48); } }
@keyframes fmBoxIn       { from { opacity:0; transform:translateY(28px) scale(.94); } to { opacity:1; transform:translateY(0) scale(1); } }
@keyframes fmIconPop     { 0%{transform:scale(0)} 60%{transform:scale(1.18)} 100%{transform:scale(1)} }
.fm-backdrop {
    position:fixed; inset:0;
    background:rgba(0,0,0,0);
    z-index:9000;
    display:flex;
    align-items:center; justify-content:center;
    pointer-events:none;
    transition:background .2s ease;
}
.fm-backdrop.open {
    background:rgba(0,0,0,.48);
    pointer-events:auto;
    animation:fmBackdropIn .2s ease forwards;
}
.fm-box {
    background:#fff;
    border-radius:16px;
    padding:0;
    max-width:440px; width:100%; margin:16px;
    box-shadow:0 24px 64px rgba(0,0,0,.2);
    opacity:0;
    transform:translateY(28px) scale(.94);
    transition:opacity .25s ease, transform .28s cubic-bezier(.34,1.3,.64,1);
    overflow:hidden;
}
.fm-backdrop.open .fm-box {
    opacity:1;
    transform:translateY(0) scale(1);
    animation:fmBoxIn .28s cubic-bezier(.34,1.3,.64,1) forwards;
}
.fm-header {
    display:flex; align-items:center; gap:12px;
    padding:18px 20px 14px;
    border-bottom:1px solid #f0f2f0;
}
.fm-icon {
    width:40px; height:40px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:1rem;
}
.fm-icon.verde  { background:#e6f2ea; color:#14532d; }
.fm-icon.roxo   { background:#f3e8ff; color:#7e22ce; }
.fm-icon.amarelo{ background:#fef3c7; color:#b7791f; }
.fm-icon.vermelho{ background:#fef2f2; color:#8f2222; }
.fm-header h3 { margin:0; font-size:.97rem; font-weight:800; color:#102117; }
.fm-body { padding:16px 20px 20px; }
.fm-box .fm-sub { margin:0 0 12px; color:#66756d; font-size:.83rem; line-height:1.55; }
.fm-box .fm-impact { background:#f7f9f7; border:1px solid #e3e8e4; border-radius:8px; padding:8px 12px; font-size:.78rem; color:#374151; margin-bottom:14px; }
.fm-box textarea { width:100%; padding:9px; border:1px solid #e3e8e4; border-radius:8px; font-size:.83rem; resize:vertical; margin-bottom:12px; outline:none; transition:border-color .15s; }
.fm-box textarea:focus { border-color:#14532d; }
.fm-box .fm-check { display:flex; align-items:center; gap:8px; font-size:.82rem; color:#374151; margin-bottom:14px; cursor:pointer; }
.fm-box .fm-check input { width:16px; height:16px; cursor:pointer; }
.fm-box .fm-btns { display:flex; gap:8px; justify-content:flex-end; }
.fm-btn-cancel { padding:7px 14px; border:1px solid #e3e8e4; border-radius:8px; background:#fff; color:#374151; font-size:.82rem; font-weight:600; cursor:pointer; transition:background .15s, border-color .15s; }
.fm-btn-cancel:hover { background:#f7f9f7; border-color:#c4c9c5; }
.fm-btn-confirm { padding:7px 16px; border-radius:8px; background:#14532d; color:#fff; border:none; font-size:.82rem; font-weight:700; cursor:pointer; transition:background .15s, transform .1s; }
.fm-btn-confirm:hover { background:#0f4425; }
.fm-btn-confirm:active { transform:scale(.97); }
.fm-btn-warn { background:#b7791f; }
.fm-btn-warn:hover { background:#92400e; }
.fm-btn-danger { background:#8f2222; }
.fm-btn-danger:hover { background:#6b0f0f; }
/* Sky (boleto) */
:root { --sky-soft:#e0f2fe; --sky-mid:#7dd3fc; --sky-text:#0369a1; }
.btn-sky { background:#0369a1; color:#fff; border:none; border-radius:8px; padding:6px 14px; font-size:.83rem; font-weight:600; cursor:pointer; transition:background .15s; }
.btn-sky:hover { background:#0284c7; color:#fff; }
</style>

<?php
$estadoCls = match($aguardandoAcao) {
    'triagem_setor1'  => 'triagem',
    'boleto_pendente' => 'boleto',
    'analise_setor2'  => 'analise',
    'revisao_setor3'  => 'revisao',
    'envio_cidadao'   => 'envio',
    default           => 'concluido',
};
?>
<div class="caminho-bar">
    <?php
    // S1: sempre feito se S2 ou além
    $s1cls = ($setorAtualIdx >= 0) ? ($setorAtualIdx > 0 || $isProcessoConcluido ? 'done' : 'active') : '';
    $s2cls = $setorAtualIdx > 1 || $isProcessoConcluido ? 'done' : ($setorAtualIdx === 1 ? 'active' : '');
    $s3cls = $setorAtualIdx > 2 || $isProcessoConcluido ? 'done' : ($setorAtualIdx === 2 ? 'active' : '');
    $fimcls = $isProcessoConcluido ? 'done' : '';
    ?>
    <span class="caminho-step <?= $s1cls ?>"><span class="cdot"></span> Triagem Ambiental <small style="opacity:.65">Setor 1</small></span>
    <span class="caminho-sep">→</span>
    <span class="caminho-step <?= $s2cls ?>"><span class="cdot"></span> Fiscalização de Obras <small style="opacity:.65">Setor 2</small><span class="caminho-opt-tag">opcional</span></span>
    <span class="caminho-sep">→</span>
    <span class="caminho-step <?= $s3cls ?>"><span class="cdot"></span> Revisão do Secretário <small style="opacity:.65">Setor 3</small><span class="caminho-opt-tag">opcional</span></span>
    <span class="caminho-sep">→</span>
    <span class="caminho-step <?= $fimcls ?>"><span class="cdot"></span> Concluído</span>
    <span class="caminho-estado <?= $estadoCls ?>"><?= htmlspecialchars($acaoAtualLabel) ?></span>
</div>
</div><!-- /caminho -->

<!-- MODAIS DE FLUXO (Bootstrap-free, inline) -->
<div class="fm-backdrop" id="fm-setor2">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon verde"><i class="fas fa-helmet-safety"></i></div>
      <h3>Enviar para Fiscalização de Obras</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo passa para a <strong>Fiscalização de Obras</strong> (Setor 2). A equipe verá o processo na fila deles.</p>
      <div class="fm-impact">Destino: <strong>Fiscalização de Obras — Setor 2</strong> · Cidadão <em>não</em> é notificado · Pode ser revertido depois</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="enviar_setor2">
        <textarea name="motivo" rows="2" placeholder="Observação opcional..."></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-setor2')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm"><i class="fas fa-arrow-right me-1"></i>Confirmar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="fm-backdrop" id="fm-setor3">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon roxo"><i class="fas fa-shield-halved"></i></div>
      <h3>Enviar para Revisão do Secretário</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo passa para a <strong>Revisão do Secretário</strong> (Setor 3). O secretário pode assinar ou devolver com motivo.</p>
      <div class="fm-impact">Destino: <strong>Revisão do Secretário — Setor 3</strong> · Cidadão <em>não</em> é notificado · Após aprovação retorna à Fiscalização</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="enviar_setor3">
        <textarea name="motivo" rows="2" placeholder="Observação opcional..."></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-setor3')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm"><i class="fas fa-shield-halved me-1"></i>Confirmar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="fm-backdrop" id="fm-finalizar-s1">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon verde"><i class="fas fa-check"></i></div>
      <h3>Finalizar no Setor 1</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo é encerrado diretamente pelo Setor 1, sem passar pela fiscalização ou revisão final.</p>
      <div class="fm-impact">Destino: <strong>Concluído</strong> · Notificação ao cidadão é opcional · Irreversível sem intervenção manual</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="concluir_direto">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <textarea name="motivo" rows="2" placeholder="Observação opcional..."></textarea>
        <label class="fm-check"><input type="checkbox" name="notificar_cidadao" value="1"> Notificar o cidadão por e-mail sobre a conclusão</label>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-finalizar-s1')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm"><i class="fas fa-check me-1"></i>Finalizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="fm-backdrop" id="fm-finalizar-s2">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon verde"><i class="fas fa-check-double"></i></div>
      <h3>Finalizar no Setor 2</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo é encerrado pelo Setor 2. Use após enviar o documento final ao cidadão.</p>
      <div class="fm-impact">Destino: <strong>Concluído</strong> · Notificação ao cidadão é opcional · Irreversível sem intervenção manual</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="concluir_setor2">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <textarea name="motivo" rows="2" placeholder="Observação opcional..."></textarea>
        <label class="fm-check"><input type="checkbox" name="notificar_cidadao" value="1"> Notificar o cidadão por e-mail sobre a conclusão</label>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-finalizar-s2')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm"><i class="fas fa-check-double me-1"></i>Finalizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="fm-backdrop" id="fm-devolver-s1">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon amarelo"><i class="fas fa-rotate-left"></i></div>
      <h3>Devolver à Triagem Ambiental</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo retorna para a <strong>Triagem Ambiental</strong> (Setor 1). Informe o motivo.</p>
      <div class="fm-impact">Destino: <strong>Triagem Ambiental — Setor 1</strong> · Cidadão <em>não</em> é notificado</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="devolver_setor1">
        <textarea name="motivo" rows="3" placeholder="Motivo da devolução..." required></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-devolver-s1')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm fm-btn-warn"><i class="fas fa-rotate-left me-1"></i>Devolver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="fm-backdrop" id="fm-devolver-s2">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon vermelho"><i class="fas fa-rotate-left"></i></div>
      <h3>Devolver à Fiscalização de Obras</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo retorna para a <strong>Fiscalização de Obras</strong> (Setor 2) com motivo. Este campo é obrigatório e ficará visível no histórico.</p>
      <div class="fm-impact">Destino: <strong>Fiscalização de Obras — Setor 2</strong> · Cidadão <em>não</em> é notificado · Motivo aparece na fila deles</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="devolver_setor2">
        <textarea name="motivo" rows="3" placeholder="Motivo da devolução..." required></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-devolver-s2')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm fm-btn-danger"><i class="fas fa-rotate-left me-1"></i>Devolver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="fm-backdrop" id="fm-setor3-retornar">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon verde"><i class="fas fa-arrow-left"></i></div>
      <h3>Retornar ao Setor 2</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo retorna ao Setor 2 para envio final ao cidadão. Certifique-se de ter assinado pelo menos um documento antes de retornar.</p>
      <div class="fm-impact">Destino: <strong>Setor 2 — envio ao cidadão</strong> · Setor 2 será notificado · Irreversível sem devolução manual</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $id ?>">
        <input type="hidden" name="fluxo_acao" value="setor3_aprovado">
        <textarea name="motivo" rows="3" placeholder="Observações para o Setor 2 (opcional)..."></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-setor3-retornar')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm" style="background:#16a34a;"><i class="fas fa-arrow-left me-1"></i>Retornar ao Setor 2</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirFM(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    // foco no primeiro textarea para acessibilidade
    const ta = el.querySelector('textarea');
    if (ta) setTimeout(() => ta.focus(), 260);
}
function fecharFM(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const box = el.querySelector('.fm-box');
    if (box) {
        box.style.transition = 'opacity .18s ease, transform .18s ease';
        box.style.opacity = '0';
        box.style.transform = 'translateY(16px) scale(.96)';
    }
    el.style.transition = 'background .18s ease';
    el.style.background = 'rgba(0,0,0,0)';
    setTimeout(() => {
        el.classList.remove('open');
        if (box) { box.style.transition = ''; box.style.opacity = ''; box.style.transform = ''; }
        el.style.transition = ''; el.style.background = '';
    }, 190);
}
// compatibilidade retroativa
function abrirModal(id) { abrirFM(id); }
function fecharModal(id) { fecharFM(id); }
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.fm-backdrop').forEach(function(el) {
        el.addEventListener('click', function(e) { if (e.target === el) fecharFM(el.id); });
    });
    // ESC fecha modal aberto
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        const open = document.querySelector('.fm-backdrop.open');
        if (open) fecharFM(open.id);
    });
});
</script>

<?php /* O vocabulário desta tela mora em includes/processo-ui.css, carregado pelo header.php — ver o cabeçalho daquele arquivo. */ ?>

<div class="container-fluid px-4">
    <!-- CABEÇALHO COMPACTO DO PROCESSO -->
    <?php
    $procStatusCor = getStatusDotColor($requerimento['status']);
    $procHeaderCls = $isFinalized ? 'finalizado' : ($isIndeferido ? 'indeferido' : '');
    $nomeAlvaraProc = $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']));
    ?>
    <?php
    // Banner de devolução pelo Secretário (Setor 3 → Setor 2)
    $foiDevolvidoSecretario = false;
    $devolutorInfo = null;
    if (
        !empty($requerimento['motivo_devolucao']) &&
        ($requerimento['setor_atual'] ?? '') === 'setor2' &&
        in_array($requerimento['aguardando_acao'] ?? '', ['retorno_recusado', 'analise_setor2'], true)
    ) {
        if (!empty($requerimento['devolvido_por'])) {
            $stmtDev = $pdo->prepare("SELECT id, nome, nome_completo, nivel, cargo FROM administradores WHERE id = ? LIMIT 1");
            $stmtDev->execute([$requerimento['devolvido_por']]);
            $devolutorInfo = $stmtDev->fetch();
            if ($devolutorInfo && $devolutorInfo['nivel'] === 'secretario') {
                $foiDevolvidoSecretario = true;
            }
        } elseif (($requerimento['aguardando_acao'] ?? '') === 'retorno_recusado') {
            // Retorno via novo fluxo — sem devolvido_por obrigatório, exibe o banner mesmo assim
            $foiDevolvidoSecretario = true;
            $devolutorInfo = null;
        }
    }
    ?>
    <?php if ($foiDevolvidoSecretario): ?>
        <div class="banner-devolucao">
            <div class="banner-devolucao-icon"><i class="fas fa-rotate-left"></i></div>
            <div class="banner-devolucao-body">
                <div class="banner-devolucao-title">
                    Processo devolvido pelo Secretário
                    <?php if ($devolutorInfo): ?>
                    <span class="banner-devolucao-meta">
                        — <?= htmlspecialchars($devolutorInfo['nome_completo'] ?: $devolutorInfo['nome']) ?>
                        <?php if (!empty($requerimento['devolvido_em'])): ?>
                            · <?= date('d/m/Y \à\s H:i', strtotime($requerimento['devolvido_em'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="banner-devolucao-motivo">
                    <strong>Motivo:</strong>
                    <?= nl2br(htmlspecialchars($requerimento['motivo_devolucao'])) ?>
                </div>
            </div>
        </div>
        <style>
        .banner-devolucao {
            display:flex; gap:14px; align-items:flex-start;
            background:linear-gradient(90deg,#fffbeb 0%,#fef3c7 100%);
            border:1px solid #fcd34d;
            border-left:4px solid #b7791f;
            border-radius:12px;
            padding:14px 18px;
            margin-bottom:12px;
            box-shadow:0 2px 8px rgba(183,121,31,.08);
        }
        .banner-devolucao-icon {
            width:38px; height:38px; flex-shrink:0;
            background:#fef3c7; border-radius:9px;
            display:flex; align-items:center; justify-content:center;
            color:#92400e; font-size:1.05rem;
        }
        .banner-devolucao-body { flex:1; min-width:0; }
        .banner-devolucao-title {
            font-size:.9rem; font-weight:800;
            color:#78350f; margin-bottom:4px;
        }
        .banner-devolucao-meta {
            font-weight:600; color:#92400e; font-size:.78rem;
        }
        .banner-devolucao-motivo {
            font-size:.85rem; color:#3f2c0c; line-height:1.5;
        }
        .banner-devolucao-motivo strong { color:#78350f; }
        </style>
    <?php endif; ?>
    <?php if (($requerimento['aguardando_acao'] ?? '') === 'retorno_aprovado'): ?>
        <div class="banner-aprovado">
            <div class="banner-aprovado-icon"><i class="fas fa-circle-check"></i></div>
            <div class="banner-aprovado-body">
                <div class="banner-aprovado-title">Secretário aprovou — aguardando envio ao cidadão</div>
                <div class="banner-aprovado-sub">O documento foi revisado e assinado pelo Secretário. Envie o documento final ao cidadão para concluir o processo.</div>
            </div>
        </div>
        <style>
        .banner-aprovado {
            display:flex; gap:14px; align-items:flex-start;
            background:#f7faf8;
            border:1px solid #c4d8cc;
            border-left:3px solid #3d7a56;
            border-radius:10px;
            padding:14px 18px;
            margin-bottom:12px;
        }
        .banner-aprovado-icon {
            width:36px; height:36px; flex-shrink:0;
            background:#eaf3ee; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            color:#3d7a56; font-size:.95rem;
        }
        .banner-aprovado-body { flex:1; min-width:0; }
        .banner-aprovado-title { font-size:.88rem; font-weight:700; color:#1e3a28; margin-bottom:3px; }
        .banner-aprovado-sub   { font-size:.82rem; color:#3d5c46; line-height:1.5; }
        </style>
    <?php endif; ?>

    <div class="proc-crumb">
        <a href="requerimentos.php"><i class="fas fa-arrow-left" style="font-size:.72rem"></i> Requerimentos</a>
        <span class="proc-crumb-sep">/</span>
        <span class="proc-crumb-proto">#<?= htmlspecialchars($requerimento['protocolo']) ?></span>
    </div>

    <?php $diasEmAberto = $isBlocked ? null : (int) floor((time() - strtotime($requerimento['data_envio'])) / 86400); ?>
    <div class="proc-header <?= $procHeaderCls ?>">
        <div class="proc-header-main">
            <div class="proc-protocol-row">
                <span class="proc-protocol"><?= htmlspecialchars($requerimento['protocolo']) ?></span>
                <button type="button" class="proc-copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($requerimento['protocolo']) ?>',this)" title="Copiar protocolo"><i class="fas fa-copy"></i></button>
                <span class="proc-status"><span class="dot" style="color:<?= $procStatusCor ?>"></span><?= htmlspecialchars($requerimento['status']) ?></span>
            </div>
            <div class="proc-name"><?= htmlspecialchars($requerimento['requerente_nome']) ?></div>
            <div class="proc-meta">
                <span class="proc-tipo"><?= htmlspecialchars($nomeAlvaraProc) ?></span>
                <span>·</span>
                <span>Aberto em <?= date('d/m/Y', strtotime($requerimento['data_envio'])) ?></span>
                <?php if ($diasEmAberto !== null && $diasEmAberto > 0): ?>
                <span>·</span>
                <span class="proc-dias-aberto"><?= $diasEmAberto ?> dia<?= $diasEmAberto > 1 ? 's' : '' ?> em aberto</span>
                <?php endif; ?>
            </div>
            <?php if ($isIndeferido && !empty($requerimento['observacoes'])): ?>
                <?php
                // A observação vem como "PROCESSO INDEFERIDO\n\nMotivo: X\n\nOrientações: Y" —
                // aqui é só o motivo que importa pra quem abre o processo, sem ter que ir até
                // o histórico pra descobrir por quê.
                $motivoTexto = preg_replace('/^PROCESSO INDEFERIDO\s*/i', '', trim((string) $requerimento['observacoes']));
                $motivoTexto = trim($motivoTexto);
                ?>
                <?php if ($motivoTexto !== ''): ?>
                <div class="proc-motivo">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= nl2br(htmlspecialchars($motivoTexto)) ?></span>
                    <a href="?id=<?= $id ?>&tab=historico#historico">Ver histórico <i class="fas fa-arrow-right" style="font-size:.68rem;"></i></a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="proc-actions">
            <?php if (!$isBlocked): ?>
                <form method="post" action="" style="display:inline">
                    <button type="submit" name="marcar_nao_lido" class="btn-nao-visto"
                        onclick="return confirm('Marcar como não visto?')">
                        <i class="fas fa-eye-slash"></i> Não visto
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                showToast(<?= json_encode($mensagem, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($mensagemTipo) ?>);
            });
        </script>
    <?php endif; ?>

    <?php if ($isSecretarioPuro && $setorAtual === 'setor3'): ?>
    <?php
    $stmtDocsSec = $pdo->prepare("
        SELECT ad.id, ad.nome_arquivo, ad.assinante_nome, ad.timestamp_assinatura
        FROM assinaturas_digitais ad
        WHERE ad.requerimento_id = ?
        ORDER BY ad.timestamp_assinatura DESC
    ");
    $stmtDocsSec->execute([$id]);
    $docsParaRevisar = $stmtDocsSec->fetchAll();
    ?>
    <?php if (!empty($docsParaRevisar)): ?>
    <div class="alert" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px 20px;margin-bottom:20px;">
        <div style="font-weight:700;color:#1d4ed8;margin-bottom:10px;font-size:.9rem;">
            <i class="fas fa-file-signature me-2"></i>Documentos enviados para sua revisão
        </div>
        <?php foreach ($docsParaRevisar as $docSec): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #dbeafe;">
            <div>
                <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($docSec['nome_arquivo']) ?></div>
                <div style="font-size:.75rem;color:#6b7280;">Gerado por <?= htmlspecialchars($docSec['assinante_nome']) ?> em <?= date('d/m/Y', strtotime($docSec['timestamp_assinatura'])) ?></div>
            </div>
            <a href="visualizar_documento.php?requerimento_id=<?= $id ?>" class="btn btn-sm btn-primary" style="font-size:.8rem;">
                <i class="fas fa-eye me-1"></i>Revisar Documentos
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Barra de comando: ações do processo -->
    <?php if (!$mostrarPainelEncerrado): ?>
    <?php if ($isBlocked && !$isFiscalPuro): ?>
    <!-- Processo finalizado/indeferido, mas a barra de comando apareceu porque
         este perfil pode entregar o doc. final (ver $tratarComoAtivoParaSetor2
         acima). Sem isto, quem cai aqui não tinha como reabrir o processo. -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:12px 14px;border-radius:10px;background:#fef9c3;border:1px solid #eab30855;margin-bottom:16px;">
        <span style="font-size:.83rem;color:#854d0e;">
            <i class="fas fa-lock me-1"></i>
            Este processo já está <strong><?= $isFinalized ? 'finalizado' : htmlspecialchars(mb_strtolower($requerimento['status'], 'UTF-8')) ?></strong>. Para alterar o status é preciso reabri-lo primeiro.
        </span>
        <button type="button" class="btn btn-outline-secondary btn-sm fw-medium flex-shrink-0" onclick="showReopenModal()">
            <i class="fas fa-unlock me-1"></i>Reabrir
        </button>
    </div>
    <?php endif; ?>
    <div class="cmd-tip" style="background:<?= $bi['bg'] ?>;border:1px solid <?= $bi['color'] ?>22;">
        <i class="fas <?= $bi['icon'] ?>" style="color:<?= $bi['color'] ?>;margin-top:2px;flex-shrink:0;"></i>
        <span style="font-size:.83rem;color:<?= $bi['color'] ?>;line-height:1.5;"><?= $bi['text'] ?></span>
    </div>

    <div class="cmd-bar">
        <a href="documentos/selecionar.php?requerimento_id=<?= $id ?>" class="cmd-btn-primary" style="order:1;"><i class="fas fa-file-pen"></i>Gerar Documento</a>

        <?php if ($isSetor3): ?>
        <a href="visualizar_documento.php?requerimento_id=<?= $id ?>" class="cmd-btn tt" style="order:4;"
            data-bs-toggle="tooltip" data-bs-placement="top"
            data-bs-title="Abre os documentos gerados para revisão e assinatura final do Secretário.">
            <i class="fas fa-file-circle-check cmd-ic"></i>Revisar Documentos
        </a>
        <?php endif; ?>

        <?php if (!$isSecretarioPuro): ?>
        <button type="button" class="cmd-btn" style="order:6;" onclick="document.getElementById('atualizarStatusModal') && new bootstrap.Modal(document.getElementById('atualizarStatusModal')).show()">
            <i class="fas fa-pen-to-square cmd-ic"></i>Atualizar status
        </button>
        <?php endif; ?>

        <div class="dropdown" style="order:5;">
            <button type="button" class="cmd-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-share-nodes cmd-ic"></i>Encaminhar
            </button>
            <ul class="dropdown-menu">
                <?php if (!$podeAgirNoSetor): ?>
                <li><span class="dropdown-item-text" style="font-size:.78rem;color:#8fa399;padding:6px 10px;">
                    <i class="fas fa-lock me-1"></i>Este processo está em <strong><?= htmlspecialchars($labelSetorAtual) ?></strong>. Só a equipe daquele setor pode movimentá-lo.
                </span></li>
                <?php else: ?>
                    <?php if ($setorAtual === 'setor1'): ?>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-setor2')">
                        <i class="fas fa-helmet-safety" style="color:#0d5433"></i>
                        <span><span style="display:block">Enviar à Fiscalização de Obras</span><span class="cmd-item-desc">Setor 2 · vistoria técnica</span></span>
                    </button></li>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-finalizar-s1')">
                        <i class="fas fa-check" style="color:#475569"></i>
                        <span><span style="display:block">Marcar como concluído</span><span class="cmd-item-desc">Encerra na Triagem, sem enviar adiante</span></span>
                    </button></li>
                    <?php elseif ($setorAtual === 'setor2'): ?>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-setor3')">
                        <i class="fas fa-shield-halved" style="color:#7e22ce"></i>
                        <span><span style="display:block">Enviar ao Secretário</span><span class="cmd-item-desc">Setor 3 · revisão e assinatura final</span></span>
                    </button></li>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-finalizar-s2')">
                        <i class="fas fa-check" style="color:#475569"></i>
                        <span><span style="display:block">Marcar como concluído</span><span class="cmd-item-desc">Encerra na Fiscalização</span></span>
                    </button></li>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-devolver-s1')">
                        <i class="fas fa-rotate-left" style="color:#b45309"></i>
                        <span><span style="display:block">Devolver à Triagem</span><span class="cmd-item-desc">Setor 1 · exige motivo</span></span>
                    </button></li>
                    <?php elseif ($setorAtual === 'setor3'): ?>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-setor3-retornar')">
                        <i class="fas fa-arrow-left" style="color:#0d5433"></i>
                        <span><span style="display:block">Retornar ao Setor 2</span><span class="cmd-item-desc">Só após assinar pelo menos um documento</span></span>
                    </button></li>
                    <li><button type="button" class="dropdown-item" onclick="abrirFM('fm-devolver-s2')">
                        <i class="fas fa-rotate-left" style="color:#b45309"></i>
                        <span><span style="display:block">Devolver à Fiscalização</span><span class="cmd-item-desc">Setor 2 · exige justificativa</span></span>
                    </button></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($podeEntregarDocFinal): ?>
        <button type="button" class="cmd-btn tt" style="order:3;" data-bs-toggle="modal" data-bs-target="#docFinalModal"
            data-bs-placement="top" data-bs-title="Envia os documentos assinados ao requerente por link seguro e finaliza o processo.">
            <i class="fas fa-file-circle-check cmd-ic"></i>Enviar doc. final
        </button>
        <?php endif; ?>

        <?php if (!$isFiscalPuro && !$isSecretarioPuro): ?>
        <button type="button" class="cmd-btn tt" style="order:2;" data-bs-toggle="tooltip" data-bs-placement="top"
            data-bs-title="Envia o número do protocolo oficial ao requerente sem finalizar o processo."
            onclick="abrirFinalizacaoModal()">
            <i class="fas fa-stamp cmd-ic"></i>Enviar protocolo
        </button>
        <?php endif; ?>

        <?php if (!$isFiscalPuro && !$isSecretarioPuro): ?>
        <span class="cmd-sep" style="order:7;"></span>
        <button type="button" class="cmd-btn-danger" style="order:7;" onclick="document.getElementById('indeferirInputModal') && new bootstrap.Modal(document.getElementById('indeferirInputModal')).show()">
            <i class="fas fa-circle-xmark"></i>Indeferir processo
        </button>
        <?php endif; ?>

        <?php if (!$isSecretarioPuro): ?>
        <div class="dropdown" style="order:8;<?= ($isFiscalPuro || $isSecretarioPuro) ? '' : 'margin-left:auto;' ?>">
            <button type="button" class="cmd-more-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="<?= ($isFiscalPuro || $isSecretarioPuro) ? 'margin-left:auto;' : '' ?>">
                <i class="fas fa-ellipsis"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:240px;">
                <?php if (!$isFiscalPuro && !$isSecretarioPuro): ?>
                <li><button type="button" class="dropdown-item" onclick="document.getElementById('boletoModal') && new bootstrap.Modal(document.getElementById('boletoModal')).show()">
                    <i class="fas fa-file-invoice" style="color:#0369a1"></i>Enviar boleto
                </button></li>
                <?php endif; ?>
                <li><button type="button" class="dropdown-item" onclick="document.getElementById('complementacaoModal') && new bootstrap.Modal(document.getElementById('complementacaoModal')).show()">
                    <i class="fas fa-folder-open" style="color:#b7791f"></i>Solicitar complementação
                </button></li>
                <?php if (!$isFiscalPuro): ?>
                <li><button type="button" class="dropdown-item" onclick="showArquivarModal()">
                    <i class="fas fa-box-archive" style="color:#66756d"></i>Arquivar processo
                </button></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Co-assinaturas pendentes para este admin neste processo -->
    <?php if (!empty($_coPendsNesteProcesso)): ?>
    <div id="co-pends-card" style="margin-bottom:14px;background:#fef9f0;border:1px solid #fcd34d;border-left:4px solid #f59e0b;border-radius:12px;padding:14px 16px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <i class="fas fa-file-signature" style="color:#b45309;font-size:1rem;"></i>
            <strong style="color:#78350f;font-size:.88rem;">Sua assinatura é aguardada neste processo</strong>
            <span style="background:#b45309;color:#fff;font-size:.7rem;font-weight:700;border-radius:20px;padding:1px 8px;margin-left:auto;"><?= count($_coPendsNesteProcesso) ?></span>
        </div>
        <?php foreach ($_coPendsNesteProcesso as $_cp): ?>
        <a href="coassinar_documento.php?documento_id=<?= urlencode($_cp['documento_id']) ?>"
           style="display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px solid #fcd34d;border-radius:9px;margin-bottom:7px;text-decoration:none;color:inherit;background:#fff;transition:background .12s;"
           onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='#fff'">
            <i class="fas fa-pen-nib" style="color:#b45309;font-size:.9rem;flex-shrink:0;"></i>
            <div style="flex-grow:1;min-width:0;">
                <div style="font-weight:700;color:#1e293b;font-size:.83rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($_cp['documento_id'] ? substr($_cp['documento_id'], 0, 14) . '…' : 'Documento') ?>
                </div>
                <div style="font-size:.74rem;color:#78350f;">
                    Solicitado por <?= htmlspecialchars($_cp['solicitante_nome']) ?>
                    · <?= date('d/m/Y H:i', strtotime($_cp['criado_em'])) ?>
                </div>
            </div>
            <span style="font-size:.77rem;color:#b45309;font-weight:700;flex-shrink:0;">Assinar <i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <!-- Pareceres já gerados -->
    <div class="info-card info-card-full mb-3" style="overflow:visible;">
        <div class="info-card-head"><i class="fas fa-file-signature"></i><span>Pareceres já gerados</span></div>
        <div id="pareceres-existentes-list" style="padding:4px 0;overflow-x:auto;"></div>
    </div>
    <?php endif; ?>

    <!-- ABAS DE INFORMAÇÕES -->
    <ul class="nav nav-tabs mb-3" id="requerimentoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'informacoes' ? 'active' : '' ?>" id="informacoes-tab" data-bs-toggle="tab" data-bs-target="#informacoes" type="button" role="tab" aria-controls="informacoes" aria-selected="<?= $activeTab === 'informacoes' ? 'true' : 'false' ?>">
                <i class="fas fa-table-list me-1"></i>Dados do processo
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'documentos' ? 'active' : '' ?>" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab" aria-controls="documentos" aria-selected="<?= $activeTab === 'documentos' ? 'true' : 'false' ?>">
                <i class="fas fa-folder-open me-1"></i>Documentos
                <?php if ($documentos): ?><span class="nav-tab-count"><?= count($documentos) ?></span><?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'historico' ? 'active' : '' ?>" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico" type="button" role="tab" aria-controls="historico" aria-selected="<?= $activeTab === 'historico' ? 'true' : 'false' ?>">
                <i class="fas fa-clock-rotate-left me-1"></i>Histórico
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'pendencias' ? 'active' : '' ?>" id="pendencias-tab" data-bs-toggle="tab" data-bs-target="#pendencias" type="button" role="tab" aria-controls="pendencias" aria-selected="<?= $activeTab === 'pendencias' ? 'true' : 'false' ?>">
                <i class="fas fa-list-check me-1"></i>Pendências e cobrança
                <?php if ($acoesAtivasCount > 0): ?><span class="nav-tab-count nav-tab-count-alert"><?= $acoesAtivasCount ?></span><?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="requerimentoTabsContent">
        <!-- Aba: Informações Completas -->
        <div class="tab-pane fade <?= $activeTab === 'informacoes' ? 'show active' : '' ?>" id="informacoes" role="tabpanel">
            <?php
            $tiposComCamposTecnicos = ['construcao', 'habite_se', 'habite_se_simples', 'desmembramento', 'licenca_previa_obras'];
            $tipoAtual = $requerimento['tipo_alvara'] ?? '';
            $exibirTecnicos = in_array($tipoAtual, $tiposComCamposTecnicos);
            $ni = '<span class="text-muted fst-italic" style="font-size:.82rem">—</span>';
            ?>
            <style>
            .info-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
            @media(max-width:640px){ .info-grid { grid-template-columns:1fr; } }
            .info-card { background:#fff; border:1px solid #dde8e2; border-radius:12px; overflow:hidden; }
            .info-card-full { grid-column:1/-1; }
            .info-card-head { display:flex; align-items:center; gap:7px; padding:8px 14px; border-bottom:1px solid #dde8e2; }
            .info-card-head i { color:#5a8a6a; font-size:.78rem; }
            .info-card-head span { font-size:.7rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#5a8a6a; }
            .rt-perfil-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px;
                background:#eaf5ef; color:#1c4b36; font-size:.7rem; font-weight:700; text-transform:none; letter-spacing:0;
                text-decoration:none; transition:.14s ease; }
            .rt-perfil-chip i { color:inherit; font-size:.62rem; transition:.14s ease; }
            .rt-perfil-chip:hover { background:#1c4b36; color:#fff; transform:translateX(1px); }
            .info-kv { display:grid; grid-template-columns:auto 1fr; gap:0 12px; padding:10px 14px; }
            .info-k { font-size:.73rem; font-weight:600; color:#8fa399; white-space:nowrap; padding:4px 0; border-bottom:1px solid #f2f6f4; }
            .info-v { font-size:.82rem; color:#1a2e1e; padding:4px 0; word-break:break-word; border-bottom:1px solid #f2f6f4; }
            .info-kv .info-k:last-of-type, .info-kv .info-v:last-of-type { border-bottom:none; }
            .info-v a { color:#14532d; text-decoration:none; }
            .info-v a:hover { text-decoration:underline; }
            </style>

            <div class="info-grid">
                <!-- Processo -->
                <div class="info-card">
                    <div class="info-card-head" style="justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:7px;"><i class="fas fa-file-alt"></i><span>Processo</span></div>
                        <span class="quick-edit-hint"><i class="fas fa-pen me-1"></i>Passe o mouse em um valor para editar</span>
                    </div>
                    <?php if ($temEdicoesProcesso): ?>
                    <div class="px-3 pt-2"><div style="display:flex;gap:7px;align-items:flex-start;padding:8px 10px;border:1px solid #dbeafe;border-radius:8px;background:#eff6ff;color:#285b91;font-size:.72rem;line-height:1.4;"><i class="fas fa-clock-rotate-left mt-1"></i><span>Há dados ajustados pela equipe. O valor original permanece disponível no botão <strong>Editar dados</strong>.</span></div></div>
                    <?php endif; ?>
                    <div class="info-kv">
                        <span class="info-k">Protocolo</span>
                        <span class="info-v quick-editable" data-quick-field="protocolo" data-quick-value="<?= htmlspecialchars($requerimento['protocolo'], ENT_QUOTES) ?>" style="font-weight:800;"><span class="quick-value"><?= htmlspecialchars($requerimento['protocolo']) ?></span> <button class="copy-btn" onclick="copyToClipboard('<?= $requerimento['protocolo'] ?>',this)" style="margin-left:4px"><i class="fas fa-copy"></i></button></span>
                        <span class="info-k">Status</span>
                        <span class="info-v"><span class="rounded-circle me-1" style="display:inline-block;width:8px;height:8px;background:<?= getStatusDotColor($requerimento['status']) ?>"></span><?= htmlspecialchars($requerimento['status']) ?></span>
                        <span class="info-k">Tipo</span>
                        <span class="info-v quick-editable" data-quick-field="tipo_alvara" data-quick-value="<?= htmlspecialchars($requerimento['tipo_alvara'], ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']))) ?></span></span>
                        <span class="info-k">Enviado em</span>
                        <span class="info-v"><?= formataData($requerimento['data_envio']) ?></span>
                        <?php if (!empty($requerimento['endereco_objetivo'])): ?>
                            <?php
                            $endFormatado = preg_replace('/,\s*(?:,\s*)+/', ', ', (string) $requerimento['endereco_objetivo']);
                            $endFormatado = trim($endFormatado, ', ');
                            ?>
                        <span class="info-k">Endereço</span>
                        <span class="info-v quick-editable" data-quick-field="endereco_objetivo" data-quick-value="<?= htmlspecialchars($requerimento['endereco_objetivo'], ENT_QUOTES) ?>"><span class="quick-value"><?= nl2br(htmlspecialchars($endFormatado)) ?></span></span>
                        <?php endif; ?>
                        <?php if (!empty($requerimento['localizacao_google_maps'])): ?>
                            <?php $mapsVal = $requerimento['localizacao_google_maps']; $mapsIsUrl = filter_var($mapsVal, FILTER_VALIDATE_URL) !== false; ?>
                        <span class="info-k">Mapa</span>
                        <span class="info-v"><?php if($mapsIsUrl): ?><a href="<?= htmlspecialchars($mapsVal) ?>" target="_blank" rel="noopener"><i class="fas fa-map-marker-alt me-1"></i>Google Maps</a><?php else: ?><?= htmlspecialchars($mapsVal) ?><?php endif; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Requerente -->
                <div class="info-card">
                    <div class="info-card-head">
                        <i class="fas fa-user"></i><span>Requerente</span>
                        <?php if (!empty($requerimento['requerente_cpf_cnpj'])): ?>
                            <a href="requerente_perfil.php?cpf=<?= urlencode($requerimento['requerente_cpf_cnpj']) ?>" class="rt-perfil-chip ms-auto">
                                Ver perfil completo<i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="info-kv">
                        <span class="info-k">Nome</span>
                        <span class="info-v quick-editable" data-quick-field="requerente_nome" data-quick-value="<?= htmlspecialchars($requerimento['requerente_nome'] ?? '', ENT_QUOTES) ?>" style="font-weight:700;"><span class="quick-value"><?= htmlspecialchars($requerimento['requerente_nome'] ?? '') ?></span></span>
                        <span class="info-k">E-mail</span>
                        <span class="info-v quick-editable" data-quick-field="requerente_email" data-quick-value="<?= htmlspecialchars($requerimento['requerente_email'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><a href="mailto:<?= htmlspecialchars($requerimento['requerente_email'] ?? '') ?>"><?= htmlspecialchars($requerimento['requerente_email'] ?? '') ?></a></span></span>
                        <span class="info-k">CPF/CNPJ</span>
                        <span class="info-v quick-editable" data-quick-field="requerente_cpf_cnpj" data-quick-value="<?= htmlspecialchars($requerimento['requerente_cpf_cnpj'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['requerente_cpf_cnpj'] ?? '') ?></span></span>
                        <span class="info-k">Telefone</span>
                        <span class="info-v quick-editable" data-quick-field="requerente_telefone" data-quick-value="<?= htmlspecialchars($requerimento['requerente_telefone'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><a href="tel:<?= htmlspecialchars($requerimento['requerente_telefone'] ?? '') ?>"><?= htmlspecialchars($requerimento['requerente_telefone'] ?? '') ?></a></span></span>
                        <?php if (!empty($requerimento['proprietario_id'])): ?>
                        <span class="info-k" style="padding-top:10px;border-top:1px solid var(--req-line,#e5e8e6);">Proprietário</span>
                        <span class="info-v quick-editable" data-quick-field="proprietario_nome" data-quick-value="<?= htmlspecialchars($requerimento['proprietario_nome'] ?? '', ENT_QUOTES) ?>" style="padding-top:10px;border-top:1px solid var(--req-line,#e5e8e6);font-weight:700;"><span class="quick-value"><?= htmlspecialchars($requerimento['proprietario_nome'] ?? '') ?></span></span>
                        <?php if (!empty($requerimento['proprietario_cpf_cnpj'])): ?>
                        <span class="info-k">CPF/CNPJ</span>
                        <span class="info-v quick-editable" data-quick-field="proprietario_cpf_cnpj" data-quick-value="<?= htmlspecialchars($requerimento['proprietario_cpf_cnpj'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['proprietario_cpf_cnpj'] ?? '') ?></span></span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Imóvel / Objeto -->
                <?php
                $isDesmembramento = ($tipoAtual === 'desmembramento');
                $isConstrucao = in_array($tipoAtual, ['construcao', 'construcao_obras_publicas'], true);
                $isHabiteSe = in_array($tipoAtual, ['habite_se', 'habite_se_simples', 'habite_se_obras_publicas'], true);
                ?>
                <div class="info-card">
                    <div class="info-card-head"><i class="fas fa-building"></i><span>Imóvel / Objeto</span></div>
                    <div class="info-kv">
                        <?php if ($isDesmembramento): ?>
                            <?php if (!empty($requerimento['matricula_imovel'])): ?>
                                <span class="info-k">Cadastro Imobiliário (imóvel original)</span>
                                <span class="info-v quick-editable" data-quick-field="matricula_imovel" data-quick-value="<?= htmlspecialchars($requerimento['matricula_imovel'] ?? '', ENT_QUOTES) ?>"><span class="quick-value" style="font-weight:700;color:#14532d;"><?= htmlspecialchars($requerimento['matricula_imovel']) ?></span></span>
                            <?php endif; ?>
                            <?php
                            $desmJsonPreview = json_decode((string) ($requerimento['desmembramento_lotes_json'] ?? ''), true);
                            $areaTotalDesconhecidaMotivo = trim((string) ($desmJsonPreview['area_total_desconhecida_motivo'] ?? ''));
                            ?>
                            <span class="info-k">Área Total do Terreno</span>
                            <span class="info-v quick-editable" data-quick-field="area_total_terreno" data-quick-value="<?= htmlspecialchars($requerimento['area_total_terreno'] ?? '', ENT_QUOTES) ?>">
                                <span class="quick-value"><?= !empty($requerimento['area_total_terreno']) ? htmlspecialchars(DocumentoRegras::formatarArea($requerimento['area_total_terreno'])) . ' m²' : $ni ?></span>
                                <?php if ($areaTotalDesconhecidaMotivo !== ''): ?>
                                    <span style="display:block;font-size:.74rem;color:#a15c00;background:#fff7e6;border:1px solid #ffe3ac;border-radius:6px;padding:5px 8px;margin-top:4px;">
                                        <i class="fas fa-triangle-exclamation" style="margin-right:4px;"></i>Requerente não informou a área — motivo: <?= htmlspecialchars($areaTotalDesconhecidaMotivo) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="info-k">Área Desmembrada</span>
                            <span class="info-v"><span class="quick-value" style="font-weight:700;"><?= htmlspecialchars(DocumentoRegras::somaLotesDesmembramento($requerimento)) ?> m²</span></span>
                            <span class="info-k">Área Remanescente</span>
                            <span class="info-v quick-editable" data-quick-field="area_remanescente" data-quick-value="<?= htmlspecialchars($requerimento['area_remanescente'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= !empty($requerimento['area_remanescente']) ? htmlspecialchars(DocumentoRegras::formatarArea($requerimento['area_remanescente'])) . ' m²' : $ni ?></span></span>
                            <?php if (!empty($requerimento['cadastro_imobiliario'])): ?>
                                <span class="info-k">Cadastro Imobiliário</span>
                                <span class="info-v quick-editable" data-quick-field="cadastro_imobiliario" data-quick-value="<?= htmlspecialchars($requerimento['cadastro_imobiliario'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['cadastro_imobiliario']) ?></span></span>
                            <?php endif; ?>

                            <?php
                            $desmJson = json_decode((string) ($requerimento['desmembramento_lotes_json'] ?? ''), true);
                            $lotesLista = is_array($desmJson['lotes'] ?? null) ? $desmJson['lotes'] : [];
                            ?>
                            <?php if (!empty($lotesLista)): ?>
                                <span class="info-k" style="grid-column:1/-1;background:#f4f8f5;padding:6px 10px;margin-top:6px;border-radius:6px;font-weight:700;color:#1e3d29;display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-layer-group"></i> Lotes do Desmembramento (<?= count($lotesLista) ?>)
                                </span>
                                <div style="grid-column:1/-1;display:flex;flex-direction:column;gap:6px;padding:4px 0 8px 0;">
                                    <?php foreach ($lotesLista as $idx => $lote): ?>
                                        <div style="background:#fdfefe;border:1px solid #e0ece5;border-radius:6px;padding:8px 10px;font-size:.78rem;line-height:1.45;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                                <strong style="color:#14532d;font-size:.82rem;">Lote <?= htmlspecialchars((string) ($lote['ordem'] ?? ($idx + 1))) ?>: <?= htmlspecialchars(DocumentoRegras::formatarArea($lote['area'] ?? '')) ?> m²</strong>
                                                <?php if (!empty($lote['cadastro_imobiliario'])): ?>
                                                    <span class="badge bg-light text-dark border">Cad.: <?= htmlspecialchars((string) $lote['cadastro_imobiliario']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (($lote['geometria'] ?? 'regular') === 'irregular'): ?>
                                                <div style="font-size:.74rem;color:#496154;">
                                                    <strong>Lote irregular:</strong> <?= nl2br(htmlspecialchars((string) ($lote['descricao_irregular'] ?? ''))) ?>
                                                </div>
                                            <?php elseif (!empty($lote['confrontacoes'])): ?>
                                                <div style="font-size:.74rem;color:#496154;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:2px 8px;">
                                                    <?php foreach (['norte'=>'Norte','oeste'=>'Oeste','leste'=>'Leste','sul'=>'Sul'] as $rKey => $rLabel): ?>
                                                        <?php if (!empty($lote['confrontacoes'][$rKey]['metragem']) || !empty($lote['confrontacoes'][$rKey]['descricao'])): ?>
                                                            <div><strong><?= $rLabel ?>:</strong> <?= htmlspecialchars((string) ($lote['confrontacoes'][$rKey]['metragem'] ?? '—')) ?> m c/ <?= htmlspecialchars((string) ($lote['confrontacoes'][$rKey]['descricao'] ?? '—')) ?></div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif (!empty($requerimento['especificacao'])): ?>
                                <span class="info-k">Composição</span>
                                <span class="info-v quick-editable" data-quick-field="especificacao" data-quick-value="<?= htmlspecialchars($requerimento['especificacao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= nl2br(htmlspecialchars($requerimento['especificacao'])) ?></span></span>
                            <?php endif; ?>

                        <?php elseif ($isConstrucao): ?>
                            <?php if (!empty($requerimento['tipo_edificacao'])): ?>
                                <span class="info-k">Tipo de Edificação</span>
                                <span class="info-v quick-editable" data-quick-field="tipo_edificacao" data-quick-value="<?= htmlspecialchars($requerimento['tipo_edificacao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['tipo_edificacao']) ?></span></span>
                            <?php endif; ?>
                            <span class="info-k">Área a Construir</span>
                            <span class="info-v quick-editable" data-quick-field="area_construcao" data-quick-value="<?= htmlspecialchars($requerimento['area_construcao'] ?? $requerimento['area_construida'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?php $a = $requerimento['area_construcao'] ?? $requerimento['area_construida'] ?? ''; echo !empty($a) ? htmlspecialchars(DocumentoRegras::formatarArea($a)).' m²' : $ni; ?></span></span>
                            <span class="info-k">Pavimentos</span>
                            <span class="info-v quick-editable" data-quick-field="numero_pavimentos" data-quick-value="<?= htmlspecialchars($requerimento['numero_pavimentos'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= !empty($requerimento['numero_pavimentos']) ? htmlspecialchars($requerimento['numero_pavimentos']) : $ni ?></span></span>
                            <?php if (!empty($requerimento['cadastro_imobiliario'])): ?>
                                <span class="info-k">Cadastro Imobiliário</span>
                                <span class="info-v quick-editable" data-quick-field="cadastro_imobiliario" data-quick-value="<?= htmlspecialchars($requerimento['cadastro_imobiliario'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['cadastro_imobiliario']) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['inicio_obra'])): ?>
                                <span class="info-k">Início Previsto</span>
                                <span class="info-v quick-editable" data-quick-field="inicio_obra" data-quick-value="<?= htmlspecialchars($requerimento['inicio_obra'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= date('d/m/Y', strtotime($requerimento['inicio_obra'])) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['termino_obra'])): ?>
                                <span class="info-k">Previsão Término</span>
                                <span class="info-v quick-editable" data-quick-field="termino_obra" data-quick-value="<?= htmlspecialchars($requerimento['termino_obra'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= date('d/m/Y', strtotime($requerimento['termino_obra'])) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['especificacao'])): ?>
                                <span class="info-k">Especificação</span>
                                <span class="info-v quick-editable" data-quick-field="especificacao" data-quick-value="<?= htmlspecialchars($requerimento['especificacao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= nl2br(htmlspecialchars($requerimento['especificacao'])) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['padrao_popular'])): ?>
                                <span class="info-k">Padrão Popular (&lt;70m²)</span>
                                <span class="info-v"><?= $requerimento['padrao_popular'] === 'sim' ? 'Sim' : 'Não' ?></span>
                            <?php endif; ?>

                        <?php elseif ($isHabiteSe): ?>
                            <?php if (!empty($requerimento['alvara_construcao_numero'])): ?>
                                <span class="info-k">Alvará Anterior Nº</span>
                                <span class="info-v quick-editable" data-quick-field="alvara_construcao_numero" data-quick-value="<?= htmlspecialchars($requerimento['alvara_construcao_numero'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['alvara_construcao_numero']) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['cadastro_imobiliario'])): ?>
                                <span class="info-k">Cadastro Imobiliário</span>
                                <span class="info-v quick-editable" data-quick-field="cadastro_imobiliario" data-quick-value="<?= htmlspecialchars($requerimento['cadastro_imobiliario'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['cadastro_imobiliario']) ?></span></span>
                            <?php endif; ?>
                            <span class="info-k">Área Construída</span>
                            <span class="info-v quick-editable" data-quick-field="area_construida" data-quick-value="<?= htmlspecialchars($requerimento['area_construida'] ?? $requerimento['area_construcao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?php $a = $requerimento['area_construida'] ?? $requerimento['area_construcao'] ?? ''; echo !empty($a) ? htmlspecialchars(DocumentoRegras::formatarArea($a)).' m²' : $ni; ?></span></span>
                            <?php if (!empty($requerimento['habite_uso']) || !empty($requerimento['habite_pavimento'])): ?>
                                <span class="info-k">Uso / Pavimento</span>
                                <span class="info-v"><?= htmlspecialchars((string) ($requerimento['habite_uso'] ?? '—')) ?> / <?= htmlspecialchars((string) ($requerimento['habite_pavimento'] ?? '—')) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['habite_tipo_construcao'])): ?>
                                <span class="info-k">Tipo de construção</span>
                                <span class="info-v quick-editable" data-quick-field="habite_tipo_construcao" data-quick-value="<?= htmlspecialchars($requerimento['habite_tipo_construcao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['habite_tipo_construcao']) ?></span></span>
                            <?php endif; ?>
                            <span class="info-k">Padrão construtivo</span>
                            <span class="info-v quick-editable" data-quick-field="habite_padrao" data-quick-value="<?= htmlspecialchars($requerimento['habite_padrao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= !empty($requerimento['habite_padrao']) ? htmlspecialchars($requerimento['habite_padrao']) : $ni ?></span></span>
                            <?php if (!empty($requerimento['inicio_obra']) || !empty($requerimento['termino_obra'])): ?>
                                <span class="info-k">Período da Obra</span>
                                <span class="info-v"><?= (!empty($requerimento['inicio_obra']) ? date('d/m/Y', strtotime($requerimento['inicio_obra'])) : '—') . ' a ' . (!empty($requerimento['termino_obra']) ? date('d/m/Y', strtotime($requerimento['termino_obra'])) : '—') ?></span>
                            <?php endif; ?>
                            <?php
                            $materiaisHabite = array_filter([
                                'Estrutura' => $requerimento['habite_estrutura'] ?? null,
                                'Paredes' => $requerimento['habite_paredes'] ?? null,
                                'Cobertura' => $requerimento['habite_cobertura'] ?? null,
                                'Forro' => $requerimento['habite_forro'] ?? null,
                                'Piso' => $requerimento['habite_piso'] ?? null,
                                'Portas' => $requerimento['habite_portas'] ?? null,
                                'Janelas' => $requerimento['habite_janelas'] ?? null,
                            ]);
                            ?>
                            <?php if (!empty($materiaisHabite)): ?>
                                <span class="info-k">Materiais</span>
                                <span class="info-v" style="font-size:.78rem;">
                                    <?= implode(' • ', array_map(fn($k, $v) => "<strong>{$k}:</strong> " . htmlspecialchars($v), array_keys($materiaisHabite), $materiaisHabite)) ?>
                                </span>
                            <?php endif; ?>
                            <?php
                            $ambientesJson = json_decode((string) ($requerimento['habite_ambientes_json'] ?? ''), true);
                            ?>
                            <?php if (!empty($ambientesJson) && is_array($ambientesJson)): ?>
                                <?php
                                $resumoAmbientes = [];
                                if (!empty($ambientesJson['total_dormitorios'])) {
                                    $txtDorm = $ambientesJson['total_dormitorios'] . ' quarto(s)';
                                    if (!empty($ambientesJson['suites'])) $txtDorm .= ' (' . $ambientesJson['suites'] . ' suíte(s))';
                                    $resumoAmbientes[] = $txtDorm;
                                }
                                if (!empty($ambientesJson['banheiros_sociais'])) $resumoAmbientes[] = $ambientesJson['banheiros_sociais'] . ' banheiro(s) social(is)';
                                if (!empty($ambientesJson['salas'])) $resumoAmbientes[] = $ambientesJson['salas'] . ' sala(s)';
                                if (!empty($ambientesJson['cozinhas'])) $resumoAmbientes[] = $ambientesJson['cozinhas'] . ' cozinha(s)';
                                if (!empty($ambientesJson['extras']) && is_array($ambientesJson['extras'])) {
                                    foreach ($ambientesJson['extras'] as $ex) {
                                        if (!empty($ex['quantidade']) && !empty($ex['nome'])) {
                                            $resumoAmbientes[] = $ex['quantidade'] . ' ' . htmlspecialchars($ex['nome']);
                                        }
                                    }
                                }
                                ?>
                                <?php if (!empty($resumoAmbientes)): ?>
                                    <span class="info-k">Ambientes</span>
                                    <span class="info-v" style="font-size:.78rem;"><?= implode(', ', $resumoAmbientes) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['especificacao'])): ?>
                                <span class="info-k">Características</span>
                                <span class="info-v quick-editable" data-quick-field="especificacao" data-quick-value="<?= htmlspecialchars($requerimento['especificacao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= nl2br(htmlspecialchars($requerimento['especificacao'])) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['padrao_popular'])): ?>
                                <span class="info-k">Padrão Popular (&lt;70m²)</span>
                                <span class="info-v"><?= $requerimento['padrao_popular'] === 'sim' ? 'Sim' : 'Não' ?></span>
                            <?php endif; ?>
                            <?php if ($requerimento['bombeiro_possui'] !== null): ?>
                                <span class="info-k">Corpo de Bombeiros</span>
                                <span class="info-v"><?= $requerimento['bombeiro_possui'] ? ('Possui — ' . htmlspecialchars($requerimento['bombeiro_numero'] ?? '')) : 'Não possui' ?></span>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php if (!empty($requerimento['area_construida']) || !empty($requerimento['area_construcao'])): ?>
                                <span class="info-k">Área</span>
                                <span class="info-v quick-editable" data-quick-field="area_construida" data-quick-value="<?= htmlspecialchars($requerimento['area_construida'] ?? $requerimento['area_construcao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?php $a = $requerimento['area_construida'] ?? $requerimento['area_construcao'] ?? ''; echo !empty($a) ? htmlspecialchars(DocumentoRegras::formatarArea($a)).' m²' : $ni; ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['numero_pavimentos'])): ?>
                                <span class="info-k">Pavimentos</span>
                                <span class="info-v quick-editable" data-quick-field="numero_pavimentos" data-quick-value="<?= htmlspecialchars($requerimento['numero_pavimentos'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['numero_pavimentos']) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($requerimento['especificacao'])): ?>
                                <span class="info-k">Atividade / Objeto</span>
                                <span class="info-v quick-editable" data-quick-field="especificacao" data-quick-value="<?= htmlspecialchars($requerimento['especificacao'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= nl2br(htmlspecialchars($requerimento['especificacao'])) ?></span></span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($requerimento['enquadramento_atividade'])): ?>
                            <?php
                            include_once __DIR__ . '/../enquadramento_conema.php';
                            $nomeAtividade = $requerimento['enquadramento_atividade'];
                            if (isset($enquadramento_conema)) {
                                foreach ($enquadramento_conema as $cat) {
                                    if (isset($cat['atividades'][$nomeAtividade])) {
                                        $nomeAtividade = $cat['atividades'][$nomeAtividade]['nome'] . ' (' . $cat['atividades'][$nomeAtividade]['potencial'] . ')';
                                        break;
                                    }
                                }
                            }
                            ?>
                            <span class="info-k">CONEMA</span>
                            <span class="info-v"><?= htmlspecialchars($nomeAtividade) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($requerimento['ctf_numero'])): ?>
                            <span class="info-k">CTF</span>
                            <span class="info-v quick-editable" data-quick-field="ctf_numero" data-quick-value="<?= htmlspecialchars($requerimento['ctf_numero'], ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['ctf_numero']) ?></span></span>
                        <?php endif; ?>
                        <?php if (!empty($requerimento['licenca_anterior_numero'])): ?>
                            <span class="info-k">Lic. anterior</span>
                            <span class="info-v quick-editable" data-quick-field="licenca_anterior_numero" data-quick-value="<?= htmlspecialchars($requerimento['licenca_anterior_numero'], ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['licenca_anterior_numero']) ?></span></span>
                        <?php endif; ?>
                        <?php if (!empty($requerimento['publicacao_diario_oficial'])): ?>
                            <span class="info-k">Diário Oficial</span>
                            <span class="info-v quick-editable" data-quick-field="publicacao_diario_oficial" data-quick-value="<?= htmlspecialchars($requerimento['publicacao_diario_oficial'], ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['publicacao_diario_oficial']) ?></span></span>
                        <?php endif; ?>
                        <?php if ($requerimento['possui_estudo_ambiental'] !== null): ?>
                            <span class="info-k">Estudo ambiental</span>
                            <span class="info-v quick-editable" data-quick-field="possui_estudo_ambiental" data-quick-value="<?= htmlspecialchars((string) $requerimento['possui_estudo_ambiental'], ENT_QUOTES) ?>"><span class="quick-value"><?= $requerimento['possui_estudo_ambiental'] ? 'Sim' : 'Não' ?></span></span>
                        <?php endif; ?>
                        <?php if (!empty($requerimento['tipo_estudo_ambiental'])): ?>
                            <span class="info-k">Tipo de Estudo</span>
                            <span class="info-v quick-editable" data-quick-field="tipo_estudo_ambiental" data-quick-value="<?= htmlspecialchars($requerimento['tipo_estudo_ambiental'], ENT_QUOTES) ?>"><span class="quick-value"><?= htmlspecialchars($requerimento['tipo_estudo_ambiental']) ?></span></span>
                        <?php endif; ?>
                        <?php if ($requerimento['notificado_fiscal_obras'] !== null): ?>
                            <span class="info-k">Notificado pelo Fiscal de Obras</span>
                            <span class="info-v quick-editable" data-quick-field="notificado_fiscal_obras" data-quick-value="<?= htmlspecialchars((string) $requerimento['notificado_fiscal_obras'], ENT_QUOTES) ?>"><span class="quick-value"><?= $requerimento['notificado_fiscal_obras'] ? 'Sim' : 'Não' ?></span></span>
                        <?php endif; ?>
                    </div>
                </div>


                <!-- Responsável Técnico -->
                <?php if (!empty($requerimento['responsavel_tecnico_nome'])): ?>
                <?php
                $rtPerfilId = null;
                if (!empty($requerimento['responsavel_tecnico_registro'])) {
                    $stRtPerfil = $pdo->prepare('SELECT id FROM responsaveis_tecnicos WHERE registro = ? LIMIT 1');
                    $stRtPerfil->execute([$requerimento['responsavel_tecnico_registro']]);
                    $rtPerfilId = $stRtPerfil->fetchColumn() ?: null;
                }
                ?>
                <div class="info-card">
                    <div class="info-card-head">
                        <i class="fas fa-hard-hat"></i><span>Responsável Técnico</span>
                        <?php if ($rtPerfilId): ?>
                            <a href="responsaveis_tecnicos.php?id=<?= (int) $rtPerfilId ?>" class="rt-perfil-chip ms-auto">
                                Ver perfil completo<i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="info-kv">
                        <span class="info-k">Nome</span>
                        <span class="info-v quick-editable" data-quick-field="responsavel_tecnico_nome" data-quick-value="<?= htmlspecialchars($requerimento['responsavel_tecnico_nome'] ?? '', ENT_QUOTES) ?>" style="font-weight:700;"><span class="quick-value"><?= htmlspecialchars($requerimento['responsavel_tecnico_nome']) ?></span></span>
                        <span class="info-k">Registro</span>
                        <span class="info-v quick-editable" data-quick-field="responsavel_tecnico_registro" data-quick-value="<?= htmlspecialchars($requerimento['responsavel_tecnico_registro'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= !empty($requerimento['responsavel_tecnico_registro']) ? htmlspecialchars($requerimento['responsavel_tecnico_registro']) : $ni ?></span></span>
                        <span class="info-k"><?= htmlspecialchars($requerimento['responsavel_tecnico_tipo_documento'] ?? 'ART/RRT') ?></span>
                        <span class="info-v quick-editable" data-quick-field="responsavel_tecnico_numero" data-quick-value="<?= htmlspecialchars($requerimento['responsavel_tecnico_numero'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><?= !empty($requerimento['responsavel_tecnico_numero']) ? htmlspecialchars($requerimento['responsavel_tecnico_numero']) : $ni ?></span></span>
                        <?php if (!empty($requerimento['responsavel_tecnico_email'])): ?>
                            <span class="info-k">E-mail</span>
                            <span class="info-v quick-editable" data-quick-field="responsavel_tecnico_email" data-quick-value="<?= htmlspecialchars($requerimento['responsavel_tecnico_email'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><a href="mailto:<?= htmlspecialchars($requerimento['responsavel_tecnico_email']) ?>"><?= htmlspecialchars($requerimento['responsavel_tecnico_email']) ?></a></span></span>
                        <?php endif; ?>
                        <?php if (!empty($requerimento['responsavel_tecnico_telefone'])): ?>
                            <span class="info-k">Telefone</span>
                            <span class="info-v quick-editable" data-quick-field="responsavel_tecnico_telefone" data-quick-value="<?= htmlspecialchars($requerimento['responsavel_tecnico_telefone'] ?? '', ENT_QUOTES) ?>"><span class="quick-value"><a href="tel:<?= htmlspecialchars($requerimento['responsavel_tecnico_telefone']) ?>"><?= htmlspecialchars($requerimento['responsavel_tecnico_telefone']) ?></a></span></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'documentos' ? 'show active' : '' ?>" id="documentos" role="tabpanel">
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-folder-open icon"></i>
                    <h6>Enviado pelo requerente</h6>
                </div>
                <div class="card-body">
                    <div class="documents-toolbar">
                        <div class="text-muted small">
                            <?php echo count($documentos); ?> documento(s) anexado(s) neste processo.
                        </div>
                        <div class="documents-toolbar-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="window.open('protocolo-capa.php?id=<?php echo $id; ?>', '_blank')" title="Baixar capa do processo">
                                <i class="fas fa-file-alt me-1"></i>Baixar Capa
                            </button>
                            <?php if (count($documentos) > 0): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadAllFiles()" title="Baixar todos os documentos">
                                    <i class="fas fa-download me-1"></i>Baixar Todos
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (count($documentos) > 0): ?>
                        <?php foreach ($documentos as $doc): ?>
                            <?php
                            $iconClass = "fas fa-file";
                            $iconColor = "#6b7280";
                            $tituloDocumento = $doc['nome_original'];

                            if ($doc['campo_formulario'] === 'boleto_pagamento_admin') {
                                $tituloDocumento = 'Boleto enviado pela equipe';
                            } elseif ($doc['campo_formulario'] === 'comprovante_pagamento_boleto') {
                                $tituloDocumento = 'Comprovante de pagamento do requerente';
                            }

                            if (strpos($doc['tipo_arquivo'], 'pdf') !== false) {
                                $iconClass = "fas fa-file-pdf";
                                $iconColor = "#dc2626";
                            } elseif (strpos($doc['tipo_arquivo'], 'image') !== false) {
                                $iconClass = "fas fa-image";
                                $iconColor = "#059669";
                            } elseif (strpos($doc['tipo_arquivo'], 'word') !== false || strpos($doc['tipo_arquivo'], 'document') !== false) {
                                $iconClass = "fas fa-file-word";
                                $iconColor = "#2563eb";
                            } elseif (strpos($doc['tipo_arquivo'], 'excel') !== false || strpos($doc['tipo_arquivo'], 'spreadsheet') !== false) {
                                $iconClass = "fas fa-file-excel";
                                $iconColor = "#16a34a";
                            }
                            ?>
                            <div class="data-row">
                                <div class="data-label" style="min-width: 40px;">
                                    <i class="<?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>; font-size: 20px;"></i>
                                </div>
                                <div class="data-value">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($tituloDocumento); ?></div>
                                    <?php if ($tituloDocumento !== $doc['nome_original']): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($doc['nome_original']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted small"><?php echo number_format($doc['tamanho'] / 1024, 2) . ' KB'; ?></div>
                                </div>
                                <div class="data-actions">
                                    <a href="<?php echo htmlspecialchars('../' . urlArquivo($doc['caminho'])); ?>"
                                        class="copy-btn me-1"
                                        target="_blank"
                                        title="Visualizar arquivo">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars('../' . urlArquivo($doc['caminho']) . '&download=1'); ?>"
                                        class="copy-btn"
                                        download
                                        title="Baixar arquivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="documents-empty">
                            <i class="fas fa-info-circle me-2"></i>
                            Nenhum documento anexado a este requerimento.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modern-card mb-3" id="secao-docs-assinados" style="display:none">
                <div class="modern-card-header">
                    <i class="fas fa-file-signature icon"></i>
                    <h6>Gerado pela equipe</h6>
                    <span class="badge ms-auto" id="badge-docs-count"
                          style="background:#f0fdf4;color:#1c4b36;border:1px solid #bbf7d0;font-size:.75rem"></span>
                </div>
                <div class="card-body p-0">
                    <div id="docs-assinados-grid" class="p-3" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;"></div>
                </div>
            </div>
        </div>

        <!-- Aba: Histórico -->
        <div class="tab-pane fade <?= $activeTab === 'historico' ? 'show active' : '' ?>" id="historico" role="tabpanel">

            <!-- Tempo por Etapa -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-stopwatch icon"></i>
                    <h6>Tempo por Etapa</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <!-- As etapas só aparecem se o processo realmente passou por elas — um
                             processo indeferido direto na triagem, por exemplo, nunca chega a
                             ter tempo de fiscalização, e mostrar "N/A" ali sugeria um caminho
                             fixo que nem todo processo percorre. -->
                        <?php if ($tempoAteVisualizacao !== null): ?>
                        <!-- Etapa 1: Envio → 1ª Visualização -->
                        <div class="col-6 col-md">
                            <div class="p-3 rounded bg-light">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas fa-eye text-muted" style="font-size:.8rem;width:14px"></i>
                                    <span class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em">Resposta</span>
                                </div>
                                <div class="fw-semibold text-dark"><?php echo formatarTempoEstatisticas($tempoAteVisualizacao); ?></div>
                                <div class="text-muted" style="font-size:.75rem">envio → visualização</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($tempoAnalisePendente !== null): ?>
                        <!-- Etapa 2: Em análise → Pendente -->
                        <div class="col-6 col-md">
                            <div class="p-3 rounded bg-light">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas fa-inbox text-muted" style="font-size:.8rem;width:14px"></i>
                                    <span class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em">Triagem</span>
                                </div>
                                <div class="fw-semibold text-dark"><?php echo formatarTempoEstatisticas($tempoAnalisePendente); ?></div>
                                <div class="text-muted" style="font-size:.75rem">em análise → pendente</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($tempoAnaliseFiscalizacao !== null): ?>
                        <!-- Etapa 3: Pendente → Fiscalização -->
                        <div class="col-6 col-md">
                            <div class="p-3 rounded bg-light">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas fa-hard-hat text-muted" style="font-size:.8rem;width:14px"></i>
                                    <span class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em">Análise</span>
                                </div>
                                <div class="fw-semibold text-dark"><?php echo formatarTempoEstatisticas($tempoAnaliseFiscalizacao); ?></div>
                                <div class="text-muted" style="font-size:.75rem">pendente → fiscalização</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($tempoFiscalizacaoSecretario !== null): ?>
                        <!-- Etapa 4: Fiscalização → Secretário -->
                        <div class="col-6 col-md">
                            <div class="p-3 rounded bg-light">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas fa-file-signature text-muted" style="font-size:.8rem;width:14px"></i>
                                    <span class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em">Fiscalização</span>
                                </div>
                                <div class="fw-semibold text-dark"><?php echo formatarTempoEstatisticas($tempoFiscalizacaoSecretario); ?></div>
                                <div class="text-muted" style="font-size:.75rem">fiscal → secretário</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Tempo Total / Em Aberto -->
                        <div class="col-6 col-md">
                            <div class="p-3 rounded bg-light">
                                <?php if ($tempoTotalProcesso !== null): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-flag-checkered text-muted" style="font-size:.8rem;width:14px"></i>
                                        <span class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em">Tempo Total</span>
                                    </div>
                                    <div class="fw-semibold text-dark"><?php echo formatarTempoEstatisticas($tempoTotalProcesso); ?></div>
                                    <div class="text-muted" style="font-size:.75rem">envio → conclusão</div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-hourglass-half text-muted" style="font-size:.8rem;width:14px"></i>
                                        <span class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em">Em Aberto</span>
                                    </div>
                                    <div class="fw-semibold text-dark"><?php echo formatarTempoEstatisticas($tempoEmAberto); ?></div>
                                    <div class="text-muted" style="font-size:.75rem">desde o envio</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($tVisualizacao === null && $tFiscalizacao === null && $tConclusao === null): ?>
                        <div class="text-muted small mt-3">
                            <i class="fas fa-info-circle me-1"></i>
                            As etapas serão calculadas conforme o processo avança.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (false): // Histórico de ações e e-mails ficam disponíveis nas telas de auditoria e logs. ?>
            <div class="modern-card mb-3">
                <div class="modern-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-history icon"></i>
                        <h6 class="mb-0">Histórico de Ações</h6>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (count($historico) > 0): ?>
                        <span class="badge bg-secondary" id="historico-total-badge"><?php echo count($historico); ?> registro(s)</span>
                        <?php endif; ?>
                        <a href="logs_email.php<?= !empty($requerimento['requerente_email']) ? '?email=' . urlencode($requerimento['requerente_email']) : '' ?>"
                           target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;"
                           title="Abrir histórico completo de emails do sistema">
                            <i class="fas fa-arrow-up-right-from-square me-1"></i>Ver todos os emails
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (count($historico) > 0): ?>
                        <?php
                        // Para secretário puro, limitar histórico a 3 itens mais recentes
                        $limitHistorico = $isSecretarioPuro ? 3 : PHP_INT_MAX;
                        $historicoExibido = $isSecretarioPuro ? array_slice($historico, 0, $limitHistorico) : $historico;
                        ?>
                        <?php foreach ($historicoExibido as $idx => $h): ?>
                            <div class="data-row" data-historico-item="<?php echo $idx; ?>">
                                <div class="data-label" style="min-width: 140px;">
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($h['admin_nome'] ?? 'Sistema'); ?></div>
                                    <div class="text-muted small"><?php echo formataData($h['data_acao']); ?></div>
                                </div>
                                <div class="data-value">
                                    <?php echo htmlspecialchars($h['acao']); ?>
                                </div>
                                <div class="data-actions">
                                    <button class="copy-btn" onclick="copyToClipboard('<?php echo addslashes(htmlspecialchars($h['acao'])); ?>', this)" title="Copiar ação">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($isSecretarioPuro && count($historico) > $limitHistorico): ?>
                        <div class="px-3 py-2 border-top text-center">
                            <span class="text-muted small">Exibindo os <?= $limitHistorico ?> registros mais recentes de <?= count($historico) ?> total.</span>
                        </div>
                        <?php endif; ?>

                        <!-- Paginação do Histórico (apenas para não-secretário) -->
                        <?php if (!$isSecretarioPuro && count($historico) > 10): ?>
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top bg-light" id="historico-pagination">
                            <button class="btn btn-sm btn-outline-secondary" id="historico-prev" onclick="historicoChangePage(-1)" disabled>
                                <i class="fas fa-chevron-left me-1"></i>Anterior
                            </button>
                            <span class="text-muted small" id="historico-page-info">Página 1 de <?php echo ceil(count($historico)/10); ?></span>
                            <button class="btn btn-sm btn-outline-secondary" id="historico-next" onclick="historicoChangePage(1)">
                                Próximo<i class="fas fa-chevron-right ms-1"></i>
                            </button>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="card-body">
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Nenhuma ação registrada.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Emails do processo: lista única e compacta, todos os tipos e status -->
            <?php if (count($emailsProcesso) > 0): ?>
            <div class="info-card info-card-full mb-3">
                <div class="info-card-head" style="justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:7px;">
                        <i class="fas fa-envelope"></i><span>E-mails do processo</span>
                    </div>
                    <span class="text-muted" style="font-size:.7rem;font-weight:600;"><?= count($emailsProcesso) ?> envio(s)</span>
                </div>
                <div>
                    <?php foreach ($emailsProcesso as $em): $emSucesso = $em['status'] === 'SUCESSO'; ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:7px 14px;border-bottom:1px solid #f2f6f4;font-size:.8rem;">
                            <span class="rounded-circle" style="flex:none;width:8px;height:8px;background:<?= $emSucesso ? '#22c55e' : '#dc2626' ?>;" title="<?= $emSucesso ? 'Enviado' : 'Falhou' ?>"></span>
                            <span class="text-muted" style="flex:none;white-space:nowrap;"><?= formataData($em['data_envio']) ?></span>
                            <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;<?= $emSucesso ? '' : 'color:#b91c1c;' ?>"
                                  title="<?= htmlspecialchars($em['assunto']) ?> — para <?= htmlspecialchars($em['email_destino']) ?><?= !$emSucesso && !empty($em['erro']) ? ' — ' . htmlspecialchars($em['erro']) : '' ?>">
                                <?= htmlspecialchars($em['assunto']) ?>
                                <span class="text-muted">· <?= htmlspecialchars($em['email_destino']) ?></span>
                            </span>
                            <a href="preview_email.php?id=<?= (int) $em['id'] ?>" target="_blank" class="copy-btn" style="flex:none;" title="Ver o email">
                                <i class="fas fa-envelope-open-text"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div>

        <!-- Aba: Pendências e cobrança -->
        <div class="tab-pane fade <?= $activeTab === 'pendencias' ? 'show active' : '' ?>" id="pendencias" role="tabpanel">
            <p class="text-muted mb-3" style="font-size:.84rem;max-width:70ch;">
                Serviços que só entram em ação quando o processo precisa: pedir algo que faltou ou veio errado, cobrar a taxa e registrar notas internas da equipe.
            </p>

            <!-- Complementações solicitadas ao requerente -->
            <div class="info-card info-card-full mb-3">
                <div class="info-card-head" style="justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:7px;">
                        <i class="fas fa-folder-open"></i><span>Complementações</span>
                    </div>
                    <?php if (!$isSecretarioPuro): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#complementacaoModal">
                        <i class="fas fa-plus me-1"></i>Solicitar
                    </button>
                    <?php endif; ?>
                </div>
                <?php if (!$pendencias): ?>
                    <div class="card-body text-center text-muted py-4">
                        <i class="fas fa-circle-check d-block mb-2" style="font-size:1.4rem;color:#cfdad3;"></i>
                        Nada pendente com o requerente.
                    </div>
                <?php else: ?>
                    <div style="padding:4px 0;">
                        <?php foreach ($pendencias as $p): ?>
                            <?php
                            $anexosP = listarAnexosPendencia($pdo, $id, (int) $p['id']);
                            $pStatusCor = ['aberta' => '#f59e0b', 'respondida' => '#3049a6', 'aceita' => '#059669', 'cancelada' => '#9ca3af'][$p['status']] ?? '#f59e0b';
                            $pStatusBg  = ['aberta' => '#fffbeb', 'respondida' => '#eef4ff', 'aceita' => '#ecfdf5', 'cancelada' => '#f4f7f5'][$p['status']] ?? '#fffbeb';
                            $pStatusLabel = ['aberta' => 'Aguardando o requerente', 'respondida' => 'Respondida', 'aceita' => 'Concluída', 'cancelada' => 'Cancelada'][$p['status']] ?? $p['status'];
                            ?>
                            <div style="border-left:3px solid <?= $pStatusCor ?>;padding:8px 0 8px 12px;margin-bottom:14px;">
                                <div style="font-weight:600;color:#0f172a;font-size:.9rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <?= htmlspecialchars($p['titulo']) ?>
                                    <span style="font-weight:700;font-size:.68rem;padding:2px 8px;border-radius:99px;background:<?= $pStatusBg ?>;color:<?= $pStatusCor ?>;">
                                        <?= $pStatusLabel ?>
                                    </span>
                                </div>
                                <div style="font-size:.84rem;color:var(--req-muted);margin:4px 0;">
                                    <?= nl2br(htmlspecialchars($p['descricao'])) ?>
                                </div>
                                <div style="font-size:.75rem;color:var(--req-muted);">
                                    <?php if ($p['status'] === 'aceita'): ?>
                                        Concluída <?= !empty($p['decidido_em']) ? 'em ' . formataData($p['decidido_em']) : '' ?><?= $p['resolvido_manualmente'] ? ' · registro manual' : '' ?>
                                    <?php else: ?>
                                        Solicitado por <?= htmlspecialchars($p['admin_nome'] ?? 'sistema') ?> em <?= formataData($p['criado_em']) ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($p['status'] === 'aberta'): ?>
                                    <div style="margin-top:8px;display:flex;gap:6px;align-items:center;">
                                        <input type="text" readonly class="form-control form-control-sm" style="font-size:.75rem;max-width:520px;"
                                               value="<?= htmlspecialchars(gerarUrlPendencia((int) $p['id'], $requerimento['protocolo'])) ?>"
                                               onclick="this.select()">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.innerHTML='<i class=\'fas fa-check\'></i>';">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($p['resposta'])): ?>
                                    <div style="background:#f0fdf4;border-radius:6px;padding:8px 10px;margin-top:8px;font-size:.85rem;color:#065f46;">
                                        <strong>Resposta do requerente:</strong><br>
                                        <?= nl2br(htmlspecialchars($p['resposta'])) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($anexosP): ?>
                                    <div style="margin-top:8px;">
                                        <?php foreach ($anexosP as $anexo): ?>
                                            <div class="data-row">
                                                <div class="data-label" style="min-width: 32px;">
                                                    <i class="fas fa-file-pdf" style="color:#dc2626;font-size:18px;"></i>
                                                </div>
                                                <div class="data-value">
                                                    <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($anexo['nome_original']) ?></div>
                                                    <div class="text-muted small"><?= number_format($anexo['tamanho'] / 1024, 2) ?> KB</div>
                                                </div>
                                                <div class="data-actions">
                                                    <a href="../uploads/<?= ltrim($anexo['caminho'], '/\\') ?>" class="copy-btn" target="_blank" rel="noopener" title="Visualizar arquivo">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($p['status'] === 'respondida' && !empty($p['respondido_em'])): ?>
                                    <div style="font-size:.75rem;color:var(--req-muted);margin-top:4px;">
                                        Respondido em <?= formataData($p['respondido_em']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (in_array($p['status'], ['aberta', 'respondida'], true) && !$isSecretarioPuro): ?>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;">
                                        <?php if ($p['status'] === 'respondida'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="pendencia_id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" name="aceitar_pendencia" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check me-1"></i>Aceitar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reabrirPendenciaModal<?= (int) $p['id'] ?>">
                                            <i class="fas fa-rotate-left me-1"></i>Pedir novamente
                                        </button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="pendencia_id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" name="resolver_pendencia_manual" class="btn btn-sm btn-link text-muted" style="text-decoration:none;">
                                                Resolver manualmente
                                            </button>
                                        </form>
                                    </div>

                                    <div class="modal fade" id="reabrirPendenciaModal<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content clean-action-modal">
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title mb-0">Pedir novamente: <?= htmlspecialchars($p['titulo']) ?></h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="pendencia_id" value="<?= (int) $p['id'] ?>">
                                                        <label class="form-label" style="font-size:.82rem;">O que ainda falta?</label>
                                                        <textarea name="descricao_pendencia" class="form-control" rows="3" required placeholder="Descreva o que precisa ser reenviado ou corrigido."></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" name="reabrir_pendencia" class="btn btn-warning">Enviar novo pedido</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cobrança da taxa (boleto) -->
            <div class="info-card info-card-full mb-3">
                <div class="info-card-head" style="justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:7px;">
                        <i class="fas fa-receipt"></i><span>Cobrança da taxa</span>
                    </div>
                    <?php if (!$isFiscalPuro && !$isSecretarioPuro): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#boletoModal">
                        <i class="fas fa-paper-plane me-1"></i><?= $pagamento ? 'Reenviar' : 'Emitir e enviar' ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php if (!$pagamento): ?>
                    <div class="card-body text-center text-muted py-4">
                        <i class="fas fa-file-invoice d-block mb-2" style="font-size:1.4rem;color:#cfdad3;"></i>
                        Nenhum boleto emitido para este processo.
                    </div>
                <?php else: ?>
                    <div class="info-kv">
                        <span class="info-k">Situação</span>
                        <span class="info-v"><?= !empty($documentoComprovanteBoleto) ? '<span style="color:#059669;font-weight:700">Comprovante recebido</span>' : 'Aguardando pagamento' ?></span>
                        <span class="info-k">Enviado em</span>
                        <span class="info-v"><?= !empty($pagamento['enviado_em']) ? formataData($pagamento['enviado_em']) : $ni ?></span>
                        <?php if ($documentoBoleto): ?>
                        <span class="info-k">Boleto (PDF)</span>
                        <span class="info-v"><a href="<?= htmlspecialchars('../' . urlArquivo($documentoBoleto['caminho'])) ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i><?= htmlspecialchars($documentoBoleto['nome_original']) ?></a></span>
                        <?php endif; ?>
                        <?php if ($documentoComprovanteBoleto): ?>
                        <span class="info-k">Comprovante</span>
                        <span class="info-v"><a href="<?= htmlspecialchars('../' . urlArquivo($documentoComprovanteBoleto['caminho'])) ?>" target="_blank" rel="noopener" style="color:#059669"><i class="fas fa-file-check me-1"></i><?= htmlspecialchars($documentoComprovanteBoleto['nome_original']) ?></a></span>
                        <?php endif; ?>
                        <?php if (!empty($pagamento['instrucoes'])): ?>
                        <span class="info-k">Obs.</span>
                        <span class="info-v"><?= nl2br(htmlspecialchars($pagamento['instrucoes'])) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Observações internas: só a equipe vê (feed/chat colaborativo) -->
            <div class="info-card info-card-full mb-3" id="card-observacoes-internas">
                <div class="info-card-head" style="justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-comments" style="color:#059669;font-size:.85rem;"></i>
                        <span>Observações internas</span>
                        <?php if (!empty($notasInternas)): ?>
                            <span class="badge rounded-pill bg-light text-secondary border" style="font-size:0.68rem; font-weight:600;"><?= count($notasInternas) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-muted d-flex align-items-center gap-1" style="font-size:.7rem;">
                        <i class="fas fa-lock" style="font-size:.65rem;color:#059669;"></i> Só a equipe vê — não vai para o requerente
                    </span>
                </div>

                <div class="card-body p-0">
                    <!-- Stream de mensagens estilo chat -->
                    <div id="notasInternasFeed" style="max-height: 380px; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; background: #fbfcfb; border-bottom: 1px solid #eef2f0;">
                        <?php if (empty($notasInternas)): ?>
                            <div class="text-center py-4 text-muted" id="emptyNotasMsg">
                                <div style="width:40px;height:40px;border-radius:50%;background:#ecfdf5;color:#059669;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:8px;">
                                    <i class="fas fa-comment-dots"></i>
                                </div>
                                <div style="font-size:.85rem;font-weight:600;color:#374151;">Nenhuma observação interna ainda</div>
                                <div style="font-size:.75rem;color:#6b7280;margin-top:2px;">Adicione anotações ou orientações para a equipe sobre este processo.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notasInternas as $nota):
                                $ehMeu = ((int)$nota['admin_id'] === (int)($_SESSION['admin_id'] ?? 0));
                                $isAdminGeral = in_array($_SESSION['admin_nivel'] ?? '', ['admin', 'admin_geral'], true);
                                $podeExcluir = ($ehMeu || $isAdminGeral);

                                $nomeAutor = trim($nota['admin_nome'] ?? 'Equipe');
                                $partesNome = preg_split('/\s+/', $nomeAutor);
                                $iniciais = strtoupper(mb_substr($partesNome[0] ?? 'E', 0, 1) . mb_substr($partesNome[1] ?? '', 0, 1));
                                if ($iniciais === '') { $iniciais = 'EQ'; }
                                $cargoNivel = trim($nota['admin_cargo'] ?? '') ?: ucfirst($nota['admin_nivel'] ?? 'Equipe');
                            ?>
                                <div class="nota-chat-item" style="display:flex; gap:10px; align-items:flex-start;">
                                    <!-- Avatar com Iniciais -->
                                    <div style="width:34px; height:34px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.74rem; font-weight:700; background:<?= $ehMeu ? '#059669' : '#475569' ?>; color:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.06);" title="<?= htmlspecialchars($nomeAutor) ?>">
                                        <?= htmlspecialchars($iniciais) ?>
                                    </div>

                                    <!-- Conteúdo da Mensagem -->
                                    <div style="flex:1; min-width:0;">
                                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:wrap;">
                                            <span style="font-size:.8rem; font-weight:700; color:#1f2937;">
                                                <?= htmlspecialchars($nomeAutor) ?>
                                            </span>
                                            <?php if ($ehMeu): ?>
                                                <span class="badge" style="font-size:.62rem; padding:1px 5px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; font-weight:600;">Você</span>
                                            <?php endif; ?>
                                            <span style="font-size:.72rem; color:#6b7280;">· <?= htmlspecialchars($cargoNivel) ?></span>
                                            <span class="ms-auto" style="font-size:.7rem; color:#9ca3af;" title="<?= formataData($nota['criado_em']) ?>">
                                                <?= formataData($nota['criado_em']) ?>
                                            </span>
                                            <?php if ($podeExcluir): ?>
                                                <form method="post" class="d-inline ms-1" onsubmit="return confirm('Deseja remover esta observação interna?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                                                    <input type="hidden" name="nota_id" value="<?= (int)$nota['id'] ?>">
                                                    <button type="submit" name="excluir_nota_interna" class="btn btn-link p-0 text-muted" style="font-size:.72rem; line-height:1; border:none; background:transparent;" title="Excluir anotação">
                                                        <i class="fas fa-trash-alt text-danger-hover" style="font-size:.75rem;"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <div style="background:<?= $ehMeu ? '#f0fdf4' : '#ffffff' ?>; border:1px solid <?= $ehMeu ? '#bbf7d0' : '#e5e7eb' ?>; border-radius:8px; padding:9px 13px; font-size:.84rem; line-height:1.5; color:#1f2937; white-space:pre-wrap; word-break:break-word; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                                            <?= nl2br(htmlspecialchars($nota['texto'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Composer / Caixa de envio -->
                    <div style="padding:12px 16px; background:#ffffff;">
                        <form method="post" id="formNovaNotaInterna">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                            <input type="hidden" name="adicionar_nota_interna" value="1">
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <textarea name="nota_interna_texto" id="notaInternaTexto" class="form-control" rows="2"
                                          style="font-size:.84rem; resize:vertical; border-radius:8px; border-color:#d1d5db;"
                                          placeholder="Escreva uma anotação interna para a equipe... (Enter envia, Shift+Enter pula linha)" required></textarea>
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                                    <span style="font-size:.72rem; color:#6b7280;">
                                        <i class="fas fa-info-circle text-success me-1"></i>Pressione <strong>Enter</strong> para enviar ou clique no botão
                                    </span>
                                    <button type="submit" id="btnEnviarNotaInterna" name="adicionar_nota_interna" class="btn btn-sm" style="background:#14532d; color:#fff; font-weight:600; border-radius:6px; padding:6px 16px;">
                                        <i class="fas fa-paper-plane me-1"></i>Adicionar observação
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const feed = document.getElementById('notasInternasFeed');
                const scrollFeedToBottom = function() {
                    if (feed) {
                        feed.scrollTop = feed.scrollHeight;
                    }
                };
                scrollFeedToBottom();

                // Caso a aba de pendências seja aberta após o carregamento inicial
                document.getElementById('pendencias-tab')?.addEventListener('shown.bs.tab', function() {
                    setTimeout(scrollFeedToBottom, 50);
                });

                const textarea = document.getElementById('notaInternaTexto');
                const form = document.getElementById('formNovaNotaInterna');
                const btn = document.getElementById('btnEnviarNotaInterna');
                if (textarea && form) {
                    textarea.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            if (textarea.value.trim() !== '') {
                                if (btn) {
                                    btn.click();
                                } else if (typeof form.requestSubmit === 'function') {
                                    form.requestSubmit();
                                } else {
                                    form.submit();
                                }
                            }
                        }
                    });
                }
            })();
            </script>
        </div>
    </div>

    <!-- Seção de Ações Administrativas: painel informativo de processo encerrado -->
    <?php if ($mostrarPainelEncerrado): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="modern-card <?php echo $isFinalized ? 'finalized-card' : 'indeferido-card'; ?>">
                <div class="modern-card-header <?php echo $isFinalized ? 'finalized-header' : 'indeferido-header'; ?>">
                    <i class="fas fa-cog icon text-muted"></i>
                    <h6 class="text-muted">Ações Administrativas</h6>
                    <?php if ($isFinalized): ?>
                        <div class="ms-auto">
                            <span class="badge bg-secondary">
                                <i class="fas fa-check-circle me-1"></i>Finalizado
                            </span>
                        </div>
                    <?php elseif ($isIndeferido): ?>
                        <div class="ms-auto">
                            <span class="badge bg-danger">
                                <i class="fas fa-times-circle me-1"></i><?= htmlspecialchars($requerimento['status']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body <?php echo $isFinalized ? 'finalized-body' : 'indeferido-body'; ?>">
                    <?php if ($isFinalized): ?>
                        <!-- Processo Finalizado — painel informativo -->
                        <?php
                        // Quem finalizou e quando
                        $hFinalizacao = null;
                        foreach (array_reverse($historico) as $h) {
                            if (stripos($h['acao'],'Finaliz') !== false || stripos($h['acao'],'Concluiu') !== false
                                || stripos($h['acao'],'protocolo oficial') !== false) {
                                $hFinalizacao = $h;
                                break;
                            }
                        }
                        if (!$hFinalizacao && !empty($historico)) $hFinalizacao = $historico[0];

                        // Documentos assinados gerados para este processo
                        // Assinaturas de teste da conta Kellyson (e variações) ficam ocultas
                        // para os demais usuários — precaução até a limpeza definitiva desses dados.
                        $souContaKellyson = stripos($_SESSION['admin_email'] ?? '', 'kellyson') !== false;
                        $filtroKellyson = $souContaKellyson ? "" : "AND assinante_nome NOT LIKE '%kellyson%'";
                        $stmtDocsF = $pdo->prepare("
                            SELECT MIN(timestamp_assinatura) AS primeira_assinatura,
                                   tipo_documento, documento_id,
                                   GROUP_CONCAT(DISTINCT assinante_nome ORDER BY timestamp_assinatura ASC SEPARATOR ', ') AS assinantes,
                                   nivel_assinatura
                            FROM assinaturas_digitais
                            WHERE requerimento_id = ? AND tipo_assinatura != 'sem_assinatura' $filtroKellyson
                            GROUP BY documento_id
                            ORDER BY primeira_assinatura ASC
                        ");
                        $stmtDocsF->execute([$id]);
                        $docsAssinadosF = $stmtDocsF->fetchAll(PDO::FETCH_ASSOC);

                        // Tempo total formatado
                        function formatarTempoCurto(int $seg): string {
                            if ($seg < 60) return $seg . 's';
                            if ($seg < 3600) return round($seg/60) . 'min';
                            if ($seg < 86400) return round($seg/3600, 1) . 'h';
                            $d = floor($seg/86400); $h = round(($seg%86400)/3600);
                            return $d . 'd' . ($h > 0 ? ' '.$h.'h' : '');
                        }
                        ?>
                        <div style="padding:20px 22px;">
                            <!-- Cabeçalho -->
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #e2ede8;">
                                <span style="flex-shrink:0;width:40px;height:40px;border-radius:50%;background:#e8f5e9;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-check-circle" style="color:#2e7d32;font-size:1.2rem;"></i>
                                </span>
                                <div>
                                    <div style="font-weight:800;font-size:.95rem;color:#1a2e1e;">Processo encerrado com sucesso</div>
                                    <?php if ($hFinalizacao): ?>
                                        <div style="font-size:.78rem;color:#64748b;">
                                            <?php if (!empty($hFinalizacao['admin_nome'])): ?>
                                                Encerrado por <strong><?= htmlspecialchars($hFinalizacao['admin_nome']) ?></strong>
                                                em <?= date('d/m/Y \à\s H:i', strtotime($hFinalizacao['data_acao'])) ?>
                                            <?php else: ?>
                                                <?= date('d/m/Y \à\s H:i', strtotime($hFinalizacao['data_acao'])) ?>
                                            <?php endif; ?>
                                            <?php if ($tempoTotalProcesso): ?>
                                                · Duração total: <strong><?= formatarTempoCurto($tempoTotalProcesso) ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Documentos gerados -->
                            <?php if (!empty($docsAssinadosF)): ?>
                                <div style="margin-bottom:16px;">
                                    <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px;">
                                        <i class="fas fa-file-signature me-1"></i>Documentos gerados (<?= count($docsAssinadosF) ?>)
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <?php foreach ($docsAssinadosF as $dF):
                                            $nomeDocF = htmlspecialchars(ucfirst(str_replace('_',' ', $dF['tipo_documento'])));
                                            $isAvancado = ($dF['nivel_assinatura'] === 'avancada');
                                        ?>
                                            <div style="display:flex;align-items:center;gap:10px;padding:8px 11px;background:#f8fafc;border:1px solid #e8edf2;border-radius:8px;">
                                                <i class="fas fa-file-pdf" style="color:#dc2626;font-size:.85rem;flex-shrink:0;"></i>
                                                <div style="flex:1;min-width:0;">
                                                    <div style="font-size:.8rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $nomeDocF ?></div>
                                                    <div style="font-size:.7rem;color:#94a3b8;">
                                                        <?= htmlspecialchars($dF['assinantes']) ?>
                                                        · <?= date('d/m/Y', strtotime($dF['primeira_assinatura'])) ?>
                                                    </div>
                                                </div>
                                                <?php if ($isAvancado): ?>
                                                    <span style="font-size:.6rem;padding:2px 6px;border-radius:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:700;flex-shrink:0;">AVANÇADA</span>
                                                <?php else: ?>
                                                    <span style="font-size:.6rem;padding:2px 6px;border-radius:4px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;font-weight:700;flex-shrink:0;">ELETRÔNICA</span>
                                                <?php endif; ?>
                                                <a href="assinatura/redownload_pdf.php?id=<?= urlencode($dF['documento_id']) ?>&inline=1"
                                                   target="_blank" title="Visualizar"
                                                   style="color:#2563eb;font-size:.8rem;flex-shrink:0;text-decoration:none;"><i class="fas fa-eye"></i></a>
                                                <a href="assinatura/redownload_pdf.php?id=<?= urlencode($dF['documento_id']) ?>"
                                                   title="Baixar"
                                                   style="color:#64748b;font-size:.8rem;flex-shrink:0;text-decoration:none;"><i class="fas fa-download"></i></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Docs enviados pelo cidadão -->
                            <?php if (!empty($documentos)): ?>
                                <div style="margin-bottom:16px;">
                                    <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px;">
                                        <i class="fas fa-folder-open me-1"></i>Documentação do requerente (<?= count($documentos) ?> arquivo<?= count($documentos)>1?'s':'' ?>)
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <?php foreach ($documentos as $docReq):
                                            $tituloDocReq = $docReq['nome_original'];
                                            if ($docReq['campo_formulario'] === 'boleto_pagamento_admin') {
                                                $tituloDocReq = 'Boleto enviado pela equipe';
                                            } elseif ($docReq['campo_formulario'] === 'comprovante_pagamento_boleto') {
                                                $tituloDocReq = 'Comprovante de pagamento do requerente';
                                            }
                                            $urlDocReq = '../' . urlArquivo($docReq['caminho']);
                                        ?>
                                            <div style="display:flex;align-items:center;gap:10px;padding:8px 11px;background:#f8fafc;border:1px solid #e8edf2;border-radius:8px;">
                                                <i class="fas fa-file-pdf" style="color:#dc2626;font-size:.85rem;flex-shrink:0;"></i>
                                                <div style="flex:1;min-width:0;">
                                                    <div style="font-size:.8rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($tituloDocReq) ?></div>
                                                    <div style="font-size:.7rem;color:#94a3b8;"><?= number_format($docReq['tamanho'] / 1024, 2) ?> KB</div>
                                                </div>
                                                <a href="<?= htmlspecialchars($urlDocReq) ?>" target="_blank"
                                                   title="Visualizar" style="color:#2563eb;font-size:.8rem;flex-shrink:0;text-decoration:none;"><i class="fas fa-eye"></i></a>
                                                <a href="<?= htmlspecialchars($urlDocReq) ?>" download
                                                   title="Baixar" style="color:#64748b;font-size:.8rem;flex-shrink:0;text-decoration:none;"><i class="fas fa-download"></i></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Ações -->
                            <?php if (!$isFiscalPuro): ?>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:4px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm fw-medium" onclick="showReopenModal()">
                                    <i class="fas fa-unlock me-1"></i>Reabrir
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm fw-medium" onclick="showArquivarModal()">
                                    <i class="fas fa-archive me-1"></i>Arquivar
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Processo Indeferido/Reprovado — painel compacto -->
                        <?php
                        // A ação mais recente é que decide o texto — pode ter sido o "Indeferir
                        // processo" formal (que dispara e-mail) ou uma troca manual de status
                        // pra Reprovado/Indeferido via "Atualizar status" (que não dispara nada).
                        // Afirmar "notificado por e-mail" sem checar isso já enganou a equipe
                        // achando que um e-mail tinha saído quando na verdade não saiu.
                        $ultimaAcaoEnc = !empty($historico) ? end($historico)['acao'] : '';
                        $emailFoiEnviadoNestaAcao = $ultimaAcaoEnc !== '' && (stripos($ultimaAcaoEnc, 'email') !== false || stripos($ultimaAcaoEnc, 'e-mail') !== false);
                        $statusAtualLabel = htmlspecialchars($requerimento['status']);
                        ?>
                        <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;">
                            <span style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-ban" style="color:#b91c1c;font-size:1.1rem;"></i>
                            </span>
                            <div style="flex:1;min-width:0;">
                                <p style="margin:0 0 2px;font-weight:800;font-size:.9rem;color:#1a2e1e;">Processo <?= mb_strtolower($statusAtualLabel, 'UTF-8') ?></p>
                                <p style="margin:0 0 10px;font-size:.8rem;color:var(--req-muted,#888);">
                                    <?= $emailFoiEnviadoNestaAcao ? 'O requerente foi notificado por e-mail' : 'Status alterado sem notificação automática por e-mail' ?>
                                    <?php if ($ultimaAcaoEnc): ?> · <?= htmlspecialchars(mb_strimwidth($ultimaAcaoEnc, 0, 90, '…')) ?><?php endif; ?>
                                </p>
                                <?php if (!$isFiscalPuro): ?>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm fw-medium" onclick="showReopenModal()">
                                        <i class="fas fa-unlock me-1"></i>Reabrir
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm fw-medium" onclick="showArquivarModal()">
                                        <i class="fas fa-archive me-1"></i>Arquivar
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════════════
         RESUMO LATERAL — Comunicação e movimentações
         Nada de dado novo aqui: são o histórico de e-mails
         (email_logs) e o de ações (historico_acoes) que já
         vivem na aba "Histórico", promovidos para o fim da
         página como resumo, conforme o redesenho. Quem quer
         a lista inteira continua indo na aba.
    ══════════════════════════════════════════════════ -->
    <div class="proc-resumos">
        <div class="proc-resumo-card">
            <div class="proc-resumo-head">
                <span class="proc-resumo-titulo">Comunicação com o cidadão</span>
                <a href="?id=<?= (int) $id ?>&tab=historico" class="proc-resumo-link">Ver todos</a>
            </div>
            <?php if (empty($emailsProcesso)): ?>
                <div class="proc-resumo-vazio">Nenhum e-mail enviado neste processo.</div>
            <?php else: ?>
                <?php foreach (array_slice($emailsProcesso, 0, 3) as $em): $emOk = $em['status'] === 'SUCESSO'; ?>
                    <a href="preview_email.php?id=<?= (int) $em['id'] ?>" target="_blank" class="proc-resumo-item">
                        <i class="fas <?= $emOk ? 'fa-envelope-circle-check' : 'fa-envelope-open-text' ?> proc-resumo-ic"
                           style="color:<?= $emOk ? '#3d7a56' : '#b13232' ?>"></i>
                        <span class="proc-resumo-corpo">
                            <span class="proc-resumo-assunto"><?= htmlspecialchars($em['assunto']) ?></span>
                            <span class="proc-resumo-meta">
                                <?= formataData($em['data_envio']) ?> · <?= htmlspecialchars($em['email_destino']) ?><?= $emOk ? '' : ' · falhou' ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="proc-resumo-card">
            <div class="proc-resumo-head">
                <span class="proc-resumo-titulo">Últimas movimentações</span>
                <a href="?id=<?= (int) $id ?>&tab=historico" class="proc-resumo-link">Ver todas</a>
            </div>
            <?php if (empty($historico)): ?>
                <div class="proc-resumo-vazio">Nenhuma movimentação registrada.</div>
            <?php else: ?>
                <div class="proc-timeline">
                    <?php $ultimos = array_slice($historico, 0, 4); $totalUlt = count($ultimos); ?>
                    <?php foreach ($ultimos as $ti => $h): ?>
                        <div class="proc-timeline-linha">
                            <div class="proc-timeline-marca">
                                <span class="proc-timeline-dot"></span>
                                <?php if ($ti < $totalUlt - 1): ?><span class="proc-timeline-fio"></span><?php endif; ?>
                            </div>
                            <div class="proc-timeline-conteudo">
                                <span class="proc-resumo-assunto"><?= htmlspecialchars($h['acao']) ?></span>
                                <span class="proc-resumo-meta">
                                    <?= htmlspecialchars($h['admin_nome'] ?? 'Sistema') ?> · <?= formataData($h['data_acao']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.edit-process-modal { border:0; border-radius:16px; overflow:hidden; box-shadow:0 24px 70px rgba(16,33,23,.2); }
.edit-process-head { display:flex; align-items:flex-start; gap:13px; padding:20px 24px; background:#0a6b34; color:#fff; }
.edit-process-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex:none; background:rgba(255,255,255,.15); color:#fff; }
.edit-process-kicker { color:rgba(255,255,255,.68); font-size:.62rem; font-weight:800; letter-spacing:.1em; margin-bottom:3px; }
.edit-process-head h5 { margin:0; font-size:1.05rem; color:#fff; }
.edit-process-head p { margin:4px 0 0; color:rgba(255,255,255,.75); font-size:.77rem; }
.edit-process-body { padding:20px 24px 8px; max-height:min(70vh,680px); overflow-y:auto; }
.edit-process-notice { display:flex; gap:9px; padding:11px 13px; margin-bottom:18px; border:1px solid #cfe3d7; border-radius:10px; background:#f5fbf7; color:#285d40; font-size:.76rem; line-height:1.45; }
.edit-process-notice i { color:#21834e; margin-top:2px; }
.edit-process-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; }
.edit-process-field-wide { grid-column:1/-1; }
.edit-process-field label { display:block; margin-bottom:6px; color:#334155; font-size:.75rem; font-weight:800; }
.edit-process-field .form-control { min-height:42px; border-color:#dbe5df; border-radius:9px; font-size:.82rem; }
.edit-process-field textarea.form-control { min-height:82px; resize:vertical; }
.edit-process-field .form-control:focus { border-color:#0a6b34; box-shadow:0 0 0 3px rgba(10,107,52,.1); }
.edit-process-original { display:flex; gap:6px; margin-top:6px; color:#7b8794; font-size:.68rem; line-height:1.35; }
.edit-process-original i { color:#8aa094; margin-top:2px; }
.edit-process-foot { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:14px 24px 18px; border-top:1px solid #eef1f5; background:#fff; }
.edit-process-foot .btn { min-height:40px; border-radius:8px; font-size:.8rem; font-weight:700; }
.edit-process-cancel { color:#64748b; border:0; background:transparent; }
.edit-process-cancel:hover { background:#f1f5f9; }
.edit-process-save { color:#fff; background:#0a6b34; border-color:#0a6b34; padding:0 17px; }
.edit-process-save:hover { color:#fff; background:#08582b; border-color:#08582b; }
@media(max-width:640px) { .edit-process-grid { grid-template-columns:1fr; } .edit-process-field-wide { grid-column:auto; } .edit-process-body { padding:18px 16px 8px; } .edit-process-foot { padding:12px 16px 16px; } .edit-process-foot .btn { flex:1; } }
</style>

<!-- Modal: Editar dados do processo -->
<div class="modal fade" id="editarDadosProcessoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content edit-process-modal">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="edit-process-head">
                    <div class="edit-process-icon"><i class="fas fa-pen-to-square"></i></div>
                    <div class="flex-grow-1">
                        <div class="edit-process-kicker">DADOS DO PROCESSO</div>
                        <h5>Editar informações técnicas</h5>
                        <p>Altere os dados necessários sem perder o registro do preenchimento original.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="edit-process-body">
                    <div class="edit-process-notice"><i class="fas fa-shield-halved"></i><span>Os valores atuais serão salvos no processo. Quando um campo já tiver sido alterado, o valor original aparecerá logo abaixo dele.</span></div>
                    <div class="edit-process-grid">
                        <?php
                        $camposEdicaoVisual = [
                            'endereco_objetivo' => ['Endereço do imóvel', 'textarea', 'Rua, número, bairro e município'],
                            'tipo_edificacao' => ['Tipo de edificação', 'text', 'Ex.: Residencial Unifamiliar, Comercial'],
                            'area_construcao' => ['Área de construção (m²)', 'text', ''],
                            'area_construida' => ['Área construída (m²)', 'text', ''],
                            'numero_pavimentos' => ['Número de pavimentos', 'text', ''],
                            'area_lote' => ['Área do lote (m²)', 'text', ''],
                            'area_total_terreno' => ['Área total do terreno (m²)', 'text', ''],
                            'area_remanescente' => ['Área remanescente (m²)', 'text', ''],
                            'cadastro_imobiliario' => ['Cadastro imobiliário', 'text', ''],
                            'matricula_imovel' => ['Nº da Matrícula no Cartório (RGI)', 'text', 'Ex.: 12.345 - Livro 2'],
                            'alvara_construcao_numero' => ['Alvará de construção anterior', 'text', ''],
                            'inicio_obra' => ['Início da obra', 'date', ''],
                            'termino_obra' => ['Término ou previsão da obra', 'date', ''],
                            'habite_uso' => ['Uso do imóvel (Habite-se)', 'text', 'Ex.: Residencial, Comercial'],
                            'habite_pavimento' => ['Pavimentos (Habite-se)', 'text', 'Ex.: Térreo, Dois pavimentos'],
                            'habite_tipo_construcao' => ['Tipo de construção (Habite-se)', 'text', 'Ex.: Alvenaria'],
                            'habite_padrao' => ['Padrão construtivo (Habite-se)', 'text', 'Ex.: Baixo, Médio, Alto'],
                            'responsavel_tecnico_nome' => ['Responsável técnico', 'text', 'Nome completo'],
                            'responsavel_tecnico_registro' => ['Registro profissional', 'text', 'CREA, CAU ou equivalente'],
                            'responsavel_tecnico_tipo_documento' => ['Tipo do documento técnico', 'text', 'ART/RRT'],
                            'responsavel_tecnico_numero' => ['Número do documento técnico', 'text', ''],
                            'responsavel_tecnico_email' => ['E-mail do responsável técnico', 'text', ''],
                            'responsavel_tecnico_telefone' => ['Telefone do responsável técnico', 'text', ''],
                            'especificacao' => ['Especificação', 'textarea', 'Descrição ou observações técnicas'],
                            'ctf_numero' => ['Cadastro Técnico Federal (CTF)', 'text', ''],
                            'licenca_anterior_numero' => ['Licença anterior', 'text', ''],
                            'publicacao_diario_oficial' => ['Publicação em Diário Oficial', 'text', ''],
                            'eng_fiscal_nome' => ['Engenheiro fiscal', 'text', ''],
                            'eng_fiscal_registro' => ['Registro do engenheiro fiscal', 'text', ''],
                            'tipo_estudo_ambiental' => ['Tipo de estudo ambiental', 'text', ''],
                            'possui_estudo_ambiental' => ['Possui estudo ambiental', 'select', ''],
                            'notificado_fiscal_obras' => ['Notificado pelo Fiscal de Obras', 'select', ''],
                            'observacoes' => ['Observações internas do processo', 'textarea', 'Não aparece para o cidadão'],
                        ];
                        foreach ($camposEdicaoVisual as $campo => [$rotulo, $tipoCampo, $placeholder]):
                            $valorAtual = $requerimento[$campo] ?? '';
                            $temOriginal = array_key_exists($campo, $valoresOriginaisProcesso);
                            $valorOriginal = $temOriginal ? $valoresOriginaisProcesso[$campo] : '';
                        ?>
                        <div class="edit-process-field <?= in_array($tipoCampo, ['textarea'], true) ? 'edit-process-field-wide' : '' ?>">
                            <label for="editar_<?= htmlspecialchars($campo) ?>"><?= htmlspecialchars($rotulo) ?></label>
                            <?php if ($tipoCampo === 'textarea'): ?>
                                <textarea class="form-control" id="editar_<?= htmlspecialchars($campo) ?>" name="<?= htmlspecialchars($campo) ?>" rows="3" placeholder="<?= htmlspecialchars($placeholder) ?>"><?= htmlspecialchars($valorAtual ?? '') ?></textarea>
                            <?php elseif ($tipoCampo === 'select'): ?>
                                <select class="form-select form-control" id="editar_<?= htmlspecialchars($campo) ?>" name="<?= htmlspecialchars($campo) ?>">
                                    <option value="">Não informado</option>
                                    <option value="1" <?= (string) $valorAtual === '1' ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= (string) $valorAtual === '0' && $valorAtual !== null && $valorAtual !== '' ? 'selected' : '' ?>>Não</option>
                                </select>
                            <?php else: ?>
                                <input class="form-control" type="<?= htmlspecialchars($tipoCampo) ?>" id="editar_<?= htmlspecialchars($campo) ?>" name="<?= htmlspecialchars($campo) ?>" value="<?= htmlspecialchars($valorAtual ?? '') ?>" placeholder="<?= htmlspecialchars($placeholder) ?>">
                            <?php endif; ?>
                            <?php if ($temOriginal): ?><div class="edit-process-original"><i class="fas fa-history"></i><span>Original: <?= nl2br(htmlspecialchars($valorOriginal !== '' ? $valorOriginal : 'Não informado')) ?></span></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="edit-process-foot">
                    <button type="button" class="btn edit-process-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="salvar_dados_processo" class="btn edit-process-save"><i class="fas fa-save me-2"></i>Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAIS DE AÇÕES ADMINISTRATIVAS
══════════════════════════════════════════════════ -->

<style>
/* Linguagem compartilhada dos modais de ação, alinhada ao modal de entrega final. */
#complementacaoModal .modal-dialog,
#boletoModal .modal-dialog,
#atualizarStatusModal .modal-dialog,
#indeferirInputModal .modal-dialog,
#reopenProcessModal .modal-dialog,
#arquivarModal .modal-dialog { max-width:560px; }
#complementacaoModal .modal-content,
#boletoModal .modal-content,
#atualizarStatusModal .modal-content,
#indeferirInputModal .modal-content,
#reopenProcessModal .modal-content,
#arquivarModal .modal-content {
    border:0; border-radius:14px; overflow:hidden;
    box-shadow:0 18px 48px rgba(16,33,23,.16) !important;
}
#complementacaoModal .modal-header,
#boletoModal .modal-header,
#atualizarStatusModal .modal-header,
#indeferirInputModal .modal-header,
#reopenProcessModal .modal-header,
#arquivarModal .modal-header {
    background:#0a6b34; color:#fff; border:0; padding:18px 24px 16px;
}
#complementacaoModal .modal-header .modal-title,
#boletoModal .modal-header .modal-title,
#atualizarStatusModal .modal-header .modal-title,
#indeferirInputModal .modal-header .modal-title,
#reopenProcessModal .modal-header .modal-title,
#arquivarModal .modal-header .modal-title { color:#fff !important; font-size:1.02rem; }
.action-modal-eyebrow { color:rgba(255,255,255,.68); font-size:.62rem; font-weight:800; letter-spacing:.09em; margin-bottom:3px; }
.action-modal-sub { color:rgba(255,255,255,.74); font-size:.75rem; margin:3px 0 0; line-height:1.35; }
#complementacaoModal .modal-header .btn-close,
#boletoModal .modal-header .btn-close,
#atualizarStatusModal .modal-header .btn-close,
#indeferirInputModal .modal-header .btn-close,
#reopenProcessModal .modal-header .btn-close,
#arquivarModal .modal-header .btn-close { filter:brightness(0) invert(1); opacity:.78; }
#complementacaoModal .modal-header > div,
#boletoModal .modal-header > div { width:100%; }
#complementacaoModal .modal-header > div > span,
#boletoModal .modal-header > div > span {
    width:36px !important; height:36px !important; flex:none;
    background:rgba(255,255,255,.15) !important; border:1px solid rgba(255,255,255,.2) !important;
    color:#fff !important;
}
#complementacaoModal .modal-body,
#boletoModal .modal-body,
#atualizarStatusModal .modal-body,
#indeferirInputModal .modal-body,
#reopenProcessModal .modal-body,
#arquivarModal .modal-body { padding:20px 24px 10px !important; }
#complementacaoModal .modal-body > p,
#boletoModal .modal-body > p { color:#64748b !important; font-size:.8rem !important; line-height:1.55; margin-bottom:18px !important; }
#complementacaoModal .form-label,
#boletoModal .form-label,
#atualizarStatusModal .form-label,
#indeferirInputModal .form-label,
#reopenProcessModal .form-label,
#arquivarModal .form-label { color:#334155; font-size:.78rem; font-weight:700 !important; margin-bottom:7px; }
#complementacaoModal .form-control,
#boletoModal .form-control,
#atualizarStatusModal .form-control,
#atualizarStatusModal .form-select,
#indeferirInputModal .form-control,
#reopenProcessModal .form-control,
#reopenProcessModal .form-select,
#arquivarModal .form-control,
#arquivarModal .form-select { border-color:#dfe7e2; border-radius:9px; font-size:.84rem; }
#complementacaoModal .form-control:focus,
#boletoModal .form-control:focus,
#atualizarStatusModal .form-control:focus,
#atualizarStatusModal .form-select:focus,
#indeferirInputModal .form-control:focus,
#reopenProcessModal .form-control:focus,
#reopenProcessModal .form-select:focus,
#arquivarModal .form-control:focus,
#arquivarModal .form-select:focus { border-color:#0a6b34; box-shadow:0 0 0 3px rgba(10,107,52,.1); }
#complementacaoModal .form-text,
#boletoModal .form-text { color:#94a3b8; font-size:.7rem; margin-top:6px; }
#complementacaoModal .modal-footer,
#boletoModal .modal-footer,
#atualizarStatusModal .modal-footer,
#indeferirInputModal .modal-footer,
#reopenProcessModal .modal-footer,
#arquivarModal .modal-footer {
    border-top:1px solid #eef1f5; padding:14px 24px 18px; background:#fff; gap:8px;
}
#complementacaoModal .modal-footer .btn,
#boletoModal .modal-footer .btn,
#atualizarStatusModal .modal-footer .btn,
#indeferirInputModal .modal-footer .btn,
#reopenProcessModal .modal-footer .btn,
#arquivarModal .modal-footer .btn { min-height:40px; border-radius:8px; font-size:.8rem; font-weight:700; }
#complementacaoModal .btn-warning { background:#c98212; border-color:#c98212; color:#fff; }
#complementacaoModal .btn-warning:hover { background:#aa6b0d; border-color:#aa6b0d; }
#boletoModal .btn-sky { background:#0a6b8a; border-color:#0a6b8a; }
#boletoModal .btn-sky:hover { background:#075b75; border-color:#075b75; }
@media (max-width:560px) {
    #complementacaoModal .modal-body, #boletoModal .modal-body,
    #atualizarStatusModal .modal-body, #indeferirInputModal .modal-body,
    #reopenProcessModal .modal-body, #arquivarModal .modal-body { padding:18px 16px 8px !important; }
    #complementacaoModal .modal-footer, #boletoModal .modal-footer,
    #atualizarStatusModal .modal-footer, #indeferirInputModal .modal-footer,
    #reopenProcessModal .modal-footer, #arquivarModal .modal-footer { padding:12px 16px 16px; flex-wrap:wrap; }
    #complementacaoModal .modal-footer .btn, #boletoModal .modal-footer .btn { flex:1; }
}

/* Composição final dos modais: uma coluna de conteúdo e um grupo único de ações. */
#complementacaoModal .clean-action-modal,
#boletoModal .clean-action-modal,
#atualizarStatusModal .clean-action-modal,
#indeferirInputModal .clean-action-modal,
#reopenProcessModal .clean-action-modal,
#arquivarModal .clean-action-modal { border-radius:16px; }
#complementacaoModal .clean-action-modal .modal-header,
#boletoModal .clean-action-modal .modal-header,
#atualizarStatusModal .clean-action-modal .modal-header,
#indeferirInputModal .clean-action-modal .modal-header,
#reopenProcessModal .clean-action-modal .modal-header,
#arquivarModal .clean-action-modal .modal-header { display:flex; align-items:flex-start; gap:14px; padding:20px 24px; }
#complementacaoModal .clean-action-modal .modal-header > .d-flex,
#boletoModal .clean-action-modal .modal-header > .d-flex { flex:1; min-width:0; }
.clean-action-heading { min-width:0; }
.clean-action-heading .modal-title { line-height:1.25; }
.clean-action-heading .action-modal-sub { max-width:360px; }
.clean-action-modal .modal-body { min-height:0; }
.clean-action-description { display:flex; gap:10px; padding:12px 14px; margin:0 0 20px; border:1px solid #dcece1; border-radius:10px; background:#f6fbf7; color:#496052; font-size:.78rem; line-height:1.5; }
.clean-action-description i { color:#21834e; margin-top:2px; flex:none; }
.clean-field { margin-bottom:18px; }
.clean-field:last-child { margin-bottom:4px; }
.clean-field .form-label { display:block; }
.clean-field .form-control { min-height:44px; }
.clean-field textarea.form-control { min-height:96px; }
.clean-field-note { color:#94a3b8; font-size:.7rem; margin-top:6px; }
.clean-action-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.clean-action-buttons { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
.clean-action-buttons .btn { white-space:nowrap; }
.clean-btn-cancel { color:#64748b; background:transparent; border-color:transparent; }
.clean-btn-cancel:hover { background:#f1f5f9; color:#334155; }
.clean-btn-preview { background:#fff; color:#286143; border:1px solid #c8dbce; }
.clean-btn-preview:hover { background:#f1faf4; color:#174c2d; border-color:#9fc1aa; }
.clean-btn-primary { background:#0a6b34; border-color:#0a6b34; color:#fff; }
.clean-btn-primary:hover { background:#08582b; border-color:#08582b; color:#fff; }
@media(max-width:560px) {
    .clean-action-footer { align-items:stretch; flex-direction:column; }
    .clean-action-buttons { display:grid; grid-template-columns:1fr 1fr; width:100%; }
    .clean-action-buttons .btn { width:100%; }
    .clean-action-buttons .clean-btn-primary { grid-column:1 / -1; }
}
</style>

<!-- Modal: Solicitar Complementação -->
<div class="modal fade" id="complementacaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content clean-action-modal">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:36px;height:36px;border-radius:10px;background:#fef3c7;border:1px solid #fde68a;display:inline-flex;align-items:center;justify-content:center;color:#b45309;">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        <div class="clean-action-heading">
                            <div class="action-modal-eyebrow">PROTOCOLO #<?= htmlspecialchars($requerimento['protocolo']) ?></div>
                            <h5 class="modal-title mb-0">Solicitar Complementação</h5>
                            <p class="action-modal-sub">Peça apenas o que falta para o processo avançar.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body px-4 pt-3">
                    <div class="clean-action-description"><i class="fas fa-circle-info"></i><span>
                        O requerente receberá por e-mail um link para complementar o processo, sem precisar reenviar o formulário inteiro. O mesmo link também fica disponível aqui na tela, no card "Complementações", caso seja preciso repassá-lo manualmente (WhatsApp, telefone etc.).
                    </span></div>

                    <div class="clean-field">
                        <label for="titulo_pendencia" class="form-label fw-semibold">O que está faltando</label>
                        <input type="text" class="form-control" id="titulo_pendencia" name="titulo_pendencia" maxlength="255" required
                               placeholder="Ex.: Falta a cópia do RG do proprietário">
                        <div class="clean-field-note">Aparece como título na página do requerente.</div>
                    </div>

                    <div class="clean-field">
                        <label for="descricao_pendencia" class="form-label fw-semibold">Detalhamento</label>
                        <textarea class="form-control" id="descricao_pendencia" name="descricao_pendencia" rows="4" required
                                  placeholder="Explique o que o requerente precisa enviar ou corrigir."></textarea>
                    </div>
                </div>
                <div class="modal-footer clean-action-footer">
                    <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <div class="clean-action-buttons">
                    <button type="button" class="btn clean-btn-preview" onclick="previewPendenciaEmail()">
                        <i class="fas fa-eye me-1"></i>Pré-visualizar e-mail
                    </button>
                    <button type="submit" name="solicitar_complementacao" class="btn clean-btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Enviar solicitação
                    </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Correções finais de composição para os modais que não possuem cabeçalho composto. */
#atualizarStatusModal .clean-action-modal .modal-header,
#indeferirInputModal .clean-action-modal .modal-header,
#reopenProcessModal .clean-action-modal .modal-header,
#arquivarModal .clean-action-modal .modal-header,
#indeferimentoModal .clean-action-modal .modal-header,
[id^="reabrirPendenciaModal"] .clean-action-modal .modal-header { display:flex !important; align-items:center; gap:12px; padding:18px 24px !important; }
#atualizarStatusModal .clean-action-modal .modal-header .modal-title,
#indeferirInputModal .clean-action-modal .modal-header .modal-title,
#reopenProcessModal .clean-action-modal .modal-header .modal-title,
#arquivarModal .clean-action-modal .modal-header .modal-title,
#indeferimentoModal .clean-action-modal .modal-header .modal-title,
[id^="reabrirPendenciaModal"] .clean-action-modal .modal-header .modal-title { color:#fff !important; font-size:1rem; }
#atualizarStatusModal .clean-action-modal .modal-body,
#indeferirInputModal .clean-action-modal .modal-body,
#reopenProcessModal .clean-action-modal .modal-body,
#arquivarModal .clean-action-modal .modal-body,
#indeferimentoModal .clean-action-modal .modal-body,
[id^="reabrirPendenciaModal"] .clean-action-modal .modal-body { padding:20px 24px 10px !important; }
#atualizarStatusModal .clean-action-modal .modal-footer,
#indeferirInputModal .clean-action-modal .modal-footer,
#reopenProcessModal .clean-action-modal .modal-footer,
#arquivarModal .clean-action-modal .modal-footer,
#indeferimentoModal .clean-action-modal .modal-footer,
[id^="reabrirPendenciaModal"] .clean-action-modal .modal-footer { padding:14px 24px 18px !important; }
#atualizarStatusModal .clean-action-modal .modal-footer form,
#indeferirInputModal .clean-action-modal .modal-footer form,
#reopenProcessModal .clean-action-modal .modal-footer form,
#arquivarModal .clean-action-modal .modal-footer form,
#indeferimentoModal .clean-action-modal .modal-footer form,
[id^="reabrirPendenciaModal"] .clean-action-modal .modal-footer form { display:contents; }

/* Os .alert do Bootstrap nesses modais ficavam com opacity:0 (herdado de algum
   lugar fora do nosso controle — não é regra nossa, nem do Bootstrap puro),
   deixando um vão em branco no lugar do aviso amarelo. Força visível. */
.clean-action-body .alert { opacity:1 !important; animation:none !important; }
</style>

<!-- Modal: Enviar Boleto -->
<div class="modal fade" id="boletoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content clean-action-modal">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-2">
                    <span style="width:36px;height:36px;border-radius:10px;background:var(--sky-soft);border:1px solid var(--sky-mid);display:inline-flex;align-items:center;justify-content:center;color:var(--sky-text);">
                        <i class="fas fa-file-invoice"></i>
                    </span>
                        <div class="clean-action-heading">
                        <div class="action-modal-eyebrow">PROTOCOLO #<?= htmlspecialchars($requerimento['protocolo']) ?></div>
                        <h5 class="modal-title fw-bold mb-0">Enviar Boleto</h5>
                        <p class="action-modal-sub">Disponibilize a cobrança com instruções claras ao cidadão.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="clean-action-description"><i class="fas fa-circle-info"></i><span>
                        O requerente receberá um e-mail com um link seguro para acessar a página de pagamento e baixar a versão mais recente do boleto.
                    </span></div>

                    <div class="clean-field">
                        <label for="boleto_pdf" class="form-label fw-semibold" style="font-size:.875rem;">
                            Arquivo PDF do boleto <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="boleto_pdf" name="boleto_pdf"
                               accept="application/pdf,.pdf" required>
                        <?php if ($documentoBoleto): ?>
                            <div class="mt-2 d-flex align-items-center gap-2 small" style="color:var(--teal-text);">
                                <i class="fas fa-file-pdf"></i>
                                Atual: <a href="<?php echo htmlspecialchars('../' . urlArquivo($documentoBoleto['caminho'])); ?>"
                                          target="_blank" rel="noopener" style="color:inherit;">
                                    <?php echo htmlspecialchars($documentoBoleto['nome_original']); ?>
                                </a>
                                <span class="text-muted">(uma nova versão será registrada ao reenviar)</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="clean-field">
                        <label for="instrucoes_boleto" class="form-label fw-semibold" style="font-size:.875rem;">
                            Observações <span class="text-muted fw-normal">(opcional)</span>
                        </label>
                        <textarea class="form-control" id="instrucoes_boleto" name="instrucoes_boleto" rows="3"
                                  style="font-size:.875rem;resize:none;"
                                  placeholder="Prazo de vencimento, orientações complementares..."><?= htmlspecialchars($pagamento['instrucoes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer clean-action-footer">
                    <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <div class="clean-action-buttons">
                    <button type="button" class="btn clean-btn-preview" onclick="previewBoletoEmail()">
                        <i class="fas fa-eye me-1"></i>Prévia
                    </button>
                    <button type="submit" name="enviar_boleto_pagamento" class="btn clean-btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Enviar boleto
                    </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Documentos disponíveis para envio final (usados no docFinalModal)
$stmtDocsFinais = $pdo->prepare("
    SELECT ad.id, ad.nome_arquivo, ad.documento_id, ad.assinante_nome, ad.assinante_cargo, ad.timestamp_assinatura,
           COALESCE(ad.group_id, ad.documento_id) as grupo,
           EXISTS(
               SELECT 1 FROM assinaturas_digitais ad2
               WHERE ad2.requerimento_id = ad.requerimento_id
                 AND COALESCE(ad2.group_id, ad2.documento_id) = COALESCE(ad.group_id, ad.documento_id)
                 AND ad2.assinante_id IN (SELECT id FROM administradores WHERE nivel = 'secretario')
           ) as tem_assinatura_secretario
    FROM assinaturas_digitais ad
    WHERE ad.requerimento_id = ?
    ORDER BY ad.timestamp_assinatura DESC
");
$stmtDocsFinais->execute([$id]);
$docsDisponiveis = $stmtDocsFinais->fetchAll();
// Agrupar por grupo, pegar só a mais recente por grupo
$docsGrouped = [];
foreach ($docsDisponiveis as $docRow) {
    $g = $docRow['grupo'];
    if (!isset($docsGrouped[$g])) {
        $docsGrouped[$g] = $docRow;
    }
}

// Verificar se já houve entrega anterior (para mostrar aviso de reenvio)
$stmtEntregaAnterior = $pdo->prepare("
    SELECT enviado_em, visualizado_em, revogado_em,
           (SELECT nome FROM administradores WHERE id = df.admin_envio_id LIMIT 1) as enviado_por
    FROM documentos_finais df
    WHERE df.requerimento_id = ?
    ORDER BY df.enviado_em DESC
    LIMIT 1
");
$stmtEntregaAnterior->execute([$id]);
$entregaAnterior = $stmtEntregaAnterior->fetch();
$jaFoiEntregue = !empty($entregaAnterior);

$emailDestinatario = $requerimento['requerente_email'] ?? '';
$nomeDestinatario  = $requerimento['requerente_nome'] ?? '';
$tipoAlvaraNome    = $tipos_alvara[$requerimento['tipo_alvara']]['nome']
                        ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']));
?>

<!-- Modal: Enviar Documento Final ao Cidadão -->
<style>
/* Escopo fechado no modal de entrega: fora dele nada muda. */
#docFinalModal .modal-content { border:0; border-radius:14px; overflow:hidden; }

#docFinalModal .dfm-head { background:#0a6b34; color:#fff; padding:18px 24px 16px; }
#docFinalModal .dfm-eyebrow {
    font-size:.68rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase;
    color:rgba(255,255,255,.72); margin-bottom:3px;
}
#docFinalModal .dfm-title { font-size:1.02rem; font-weight:700; margin:0; color:#fff; }
#docFinalModal .dfm-sub { font-size:.76rem; color:rgba(255,255,255,.75); margin:2px 0 0; }

#docFinalModal .dfm-body { padding:20px 24px 8px; }
#docFinalModal .dfm-linha {
    font-size:.84rem; color:#334155; padding-bottom:14px;
    border-bottom:1px solid #eef1f5; margin-bottom:16px;
}
#docFinalModal .dfm-rotulo { color:#8a94a3; }
#docFinalModal .dfm-alerta-inline { color:#b42318; font-weight:600; }

/* Reenvio: informação de contexto, não alarme — barra lateral em vez de caixa. */
#docFinalModal .dfm-reenvio {
    border-left:3px solid #d99b16; padding:2px 0 2px 12px; margin-bottom:16px;
    font-size:.79rem; color:#6b5320; line-height:1.5;
}
#docFinalModal .dfm-reenvio strong { color:#4d3c14; }

#docFinalModal .dfm-secao {
    font-size:.73rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
    color:#6b7280; margin:0;
}
#docFinalModal .dfm-todos { font-size:.76rem; color:#64748b; cursor:pointer; }

/* Lista de seleção: divisórias, sem cartões empilhados. */
#docFinalModal .dfm-lista { border-top:1px solid #eef1f5; max-height:264px; overflow-y:auto; }
#docFinalModal .doc-final-check-item {
    display:flex; align-items:flex-start; gap:12px; cursor:pointer; margin:0;
    padding:11px 6px; border-bottom:1px solid #eef1f5; transition:background .12s;
}
#docFinalModal .doc-final-check-item:hover { background:#f8fafc; }
#docFinalModal .doc-final-check-item.is-sel { background:#f4faf6; }
#docFinalModal .dfm-doc-nome { font-size:.86rem; font-weight:600; color:#1e293b; }
#docFinalModal .dfm-doc-meta { font-size:.75rem; color:#7c8697; margin-top:2px; }
/* Só a exceção ganha destaque: o caso correto não precisa de selo. */
#docFinalModal .dfm-flag {
    display:inline-block; margin-top:5px; font-size:.7rem; font-weight:600;
    color:#8a5a06; background:#fdf5e6; border:1px solid #f0d9a8;
    border-radius:5px; padding:1px 7px;
}
#docFinalModal .doc-final-cb { width:17px; height:17px; margin-top:2px; flex-shrink:0; }
#docFinalModal .doc-final-cb:checked { background-color:#0a6b34; border-color:#0a6b34; }

#docFinalModal .dfm-label {
    font-size:.73rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
    color:#6b7280; margin-bottom:6px; display:block;
}
#docFinalModal .dfm-label span { text-transform:none; letter-spacing:0; font-weight:400; color:#98a1af; }
#docFinalModal textarea.form-control { font-size:.85rem; resize:none; border-radius:8px; border-color:#dfe3e9; }
#docFinalModal textarea.form-control:focus { border-color:#0a6b34; box-shadow:0 0 0 3px rgba(10,107,52,.1); }

#docFinalModal .dfm-foot {
    padding:14px 24px 18px; display:flex; align-items:center;
    justify-content:space-between; gap:10px; border-top:1px solid #eef1f5;
}
#docFinalModal .dfm-btn {
    font-size:.85rem; font-weight:600; border-radius:8px; padding:9px 16px;
    border:1px solid transparent; transition:background .12s,border-color .12s;
}
#docFinalModal .dfm-btn-link { background:none; border:0; color:#7c8697; font-weight:500; padding:9px 4px; }
#docFinalModal .dfm-btn-link:hover { color:#475569; text-decoration:underline; }
#docFinalModal .dfm-btn-sec { background:#fff; border-color:#d5dae2; color:#3f4a5a; }
#docFinalModal .dfm-btn-sec:hover { background:#f6f8fa; border-color:#b9c1cc; }
#docFinalModal .dfm-btn-pri { background:#0a6b34; color:#fff; }
#docFinalModal .dfm-btn-pri:hover { background:#08582b; color:#fff; }
#docFinalModal .dfm-btn-pri:disabled { background:#c3ccd6; color:#fff; cursor:not-allowed; }
</style>

<div class="modal fade" id="docFinalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">

            <!-- Cabeçalho: identifica o processo, não só a ação -->
            <div class="dfm-head d-flex align-items-start justify-content-between gap-3">
                <div style="min-width:0;">
                    <div class="dfm-eyebrow">
                        Protocolo #<?= htmlspecialchars($requerimento['protocolo']) ?>
                        <?php if ($tipoAlvaraNome !== ''): ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars(tituloAmigavel($tipoAlvaraNome)) ?>
                        <?php endif; ?>
                    </div>
                    <h5 class="dfm-title">Entregar documentos ao cidadão</h5>
                    <p class="dfm-sub">O processo será finalizado após o envio</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="opacity:.7;"></button>
            </div>
            <form method="post" action="fluxo_setor_handler.php" id="formDocFinal">
                <input type="hidden" name="requerimento_id" value="<?= $id ?>">
                <input type="hidden" name="fluxo_acao" value="doc_final_envio">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="dfm-body">

                    <!-- Destinatário: uma linha basta -->
                    <div class="dfm-linha">
                        <span class="dfm-rotulo">Para</span>
                        <strong><?= htmlspecialchars($nomeDestinatario) ?></strong>
                        <?php if (!empty($emailDestinatario)): ?>
                            <span class="dfm-rotulo">&nbsp;·&nbsp;</span><?= htmlspecialchars($emailDestinatario) ?>
                        <?php else: ?>
                            <span class="dfm-rotulo">&nbsp;·&nbsp;</span>
                            <span class="dfm-alerta-inline">sem e-mail cadastrado — o cidadão não será notificado</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($jaFoiEntregue): ?>
                        <div class="dfm-reenvio">
                            <strong>Reenvio.</strong> Entrega anterior em
                            <?= date('d/m/Y \à\s H:i', strtotime($entregaAnterior['enviado_em'])) ?><?php
                            if (!empty($entregaAnterior['enviado_por'])):
                                ?> por <?= htmlspecialchars($entregaAnterior['enviado_por']) ?><?php
                            endif; ?>.
                            <?php if (empty($entregaAnterior['revogado_em'])): ?>
                                O link anterior será revogado após este envio.
                            <?php else: ?>
                                O link anterior já foi revogado.
                            <?php endif; ?>
                            <?php if (!empty($entregaAnterior['visualizado_em'])): ?>
                                <br><span style="color:#17663a;font-weight:600;">
                                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                                    Acesso do cidadão confirmado em <?= date('d/m/Y \à\s H:i', strtotime($entregaAnterior['visualizado_em'])) ?>.
                                </span>
                            <?php else: ?>
                                <br><span style="color:#7c8697;">O link ainda não foi acessado pelo cidadão.</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($docsGrouped)): ?>
                        <div class="dfm-reenvio" style="border-left-color:#b42318;color:#7a3b34;">
                            <strong>Nenhum documento assinado neste processo.</strong>
                            Gere e assine um documento antes de enviar ao cidadão.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <p class="dfm-secao">Documentos a enviar</p>
                                <?php if (count($docsGrouped) > 1): ?>
                                    <label class="dfm-todos d-flex align-items-center gap-2 mb-0">
                                        <input type="checkbox" class="form-check-input mt-0" id="docFinalSelectAll" style="width:14px;height:14px;">
                                        Selecionar todos
                                    </label>
                                <?php endif; ?>
                            </div>
                            <div id="docFinalCheckList" class="dfm-lista">
                                <?php foreach ($docsGrouped as $grupo => $doc):
                                    $rotulo = rotuloDocumento($doc['nome_arquivo']);
                                ?>
                                    <label class="doc-final-check-item" data-tem-sec="<?= $doc['tem_assinatura_secretario'] ? '1' : '0' ?>">
                                        <input type="checkbox" name="documento_ids[]" value="<?= (int)$doc['id'] ?>"
                                               class="form-check-input doc-final-cb">
                                        <span class="flex-grow-1" style="min-width:0;">
                                            <span class="dfm-doc-nome d-block text-truncate">
                                                <?= htmlspecialchars($rotulo !== '' ? $rotulo : $doc['nome_arquivo']) ?>
                                            </span>
                                            <span class="dfm-doc-meta d-block">
                                                Assinado por <?= htmlspecialchars($doc['assinante_nome']) ?>
                                                <?php if (!empty($doc['assinante_cargo'])): ?>
                                                    · <?= htmlspecialchars($doc['assinante_cargo']) ?>
                                                <?php endif; ?>
                                                · <?= date('d/m/Y', strtotime($doc['timestamp_assinatura'])) ?>
                                            </span>
                                            <?php if (!$doc['tem_assinatura_secretario']): ?>
                                                <span class="dfm-flag">Sem assinatura do Secretário</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-2">
                        <label for="instrucoes_doc_final" class="dfm-label">
                            Observações ao cidadão <span>(opcional)</span>
                        </label>
                        <textarea class="form-control" id="instrucoes_doc_final" name="instrucoes_doc_final" rows="2"
                                  placeholder="Prazo de validade, condicionantes, orientações ao requerente..."></textarea>
                    </div>

                    <!-- Resumo da seleção (atualizado por JS) -->
                    <div id="docFinalResumo" style="display:none;border-left:3px solid #0a6b34;padding:2px 0 2px 12px;font-size:.79rem;color:#245c3a;line-height:1.5;margin-top:12px;">
                    </div>

                </div>

                <div class="dfm-foot">
                    <button type="button" class="dfm-btn dfm-btn-link" data-bs-dismiss="modal">Cancelar</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="dfm-btn dfm-btn-sec" onclick="previewEmailDocFinal()">Ver prévia do e-mail</button>
                        <button type="submit" class="dfm-btn dfm-btn-pri" id="btnEnviarDocFinal" <?= (empty($docsGrouped) || empty($emailDestinatario)) ? 'disabled' : '' ?>>
                            <span id="btnEnviarDocFinalLabel"><?php
                                if (empty($emailDestinatario)) echo 'Cadastre o e-mail para enviar';
                                else echo $jaFoiEntregue ? 'Reenviar ao cidadão' : 'Enviar ao cidadão';
                            ?></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Atualizar Status -->
<div class="modal fade" id="atualizarStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content clean-action-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit text-primary me-2"></i>Atualizar Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body clean-action-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Status atual:</small>
                        <span class="badge px-3 py-2 fw-semibold"
                              style="background:#f0fdf4; color:var(--primary-600); border:1px solid #bbf7d0;">
                            <?= htmlspecialchars($requerimento['status']) ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <label for="modal_status" class="form-label fw-semibold">Novo Status</label>
                        <?php
                        // Status disponíveis por role
                        if ($isFiscalPuro) {
                            $statusModalOpcoes = ['Em análise', 'Pendente', 'Finalizado'];
                        } else {
                            $statusModalOpcoes = ['Em análise','Aprovado','Reprovado','Pendente','Aguardando boleto','Boleto pago','Cancelado','Finalizado','Indeferido'];
                        }
                        ?>
                        <select class="form-select" id="modal_status" name="status" required>
                            <?php foreach ($statusModalOpcoes as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= $requerimento['status'] === $opt ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="modal_obs" class="form-label fw-semibold">
                            Observação <small class="fw-normal text-muted">(opcional)</small>
                        </label>
                        <textarea class="form-control" id="modal_obs" name="observacoes" rows="3"
                            placeholder="Justificativa ou feedback para o requerente..."><?= htmlspecialchars($requerimento['observacoes']??'') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer clean-action-footer">
                    <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn clean-btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Indeferir (coleta de dados) -->
<div class="modal fade" id="indeferirInputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content clean-action-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fas fa-times-circle me-2"></i>Indeferir Processo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body clean-action-body">
                <div class="alert alert-warning d-flex gap-2 mb-3">
                    <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
                    <div>
                        O requerente <strong><?= htmlspecialchars($requerimento['requerente_nome']) ?></strong>
                        será notificado por e-mail sobre o indeferimento do processo
                        <strong>#<?= $requerimento['protocolo'] ?></strong>.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="motivo_indeferimento" class="form-label fw-semibold">
                        Motivo <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="motivo_indeferimento" rows="4"
                        placeholder="Descreva os motivos..." required></textarea>
                </div>
                <div>
                    <label for="orientacoes_adicionais" class="form-label fw-semibold">
                        Orientações Adicionais <small class="fw-normal text-muted">(opcional)</small>
                    </label>
                    <textarea class="form-control" id="orientacoes_adicionais" rows="3"
                        placeholder="Orientações para correção ou reenvio..."></textarea>
                </div>
            </div>
            <div class="modal-footer clean-action-footer">
                <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                <div class="clean-action-buttons">
                    <button type="button" class="btn clean-btn-preview" onclick="previewIndeferimentoEmail()">
                        <i class="fas fa-eye me-1"></i>Pré-visualizar e-mail
                    </button>
                    <button type="button" class="btn btn-danger fw-semibold" onclick="showIndeferimentoModal()">
                        <i class="fas fa-times me-2"></i>Indeferir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.protocol-modal { overflow:hidden; }
.protocol-dialog { max-width:560px; }
.protocol-modal { border-radius:20px !important; background:#fff; box-shadow:0 24px 70px rgba(19,54,35,.22) !important; }
.protocol-modal-header { display:flex; align-items:center; gap:13px; padding:21px 24px; background:linear-gradient(135deg,#153e2c,#26714d); color:#fff; }
.protocol-modal-header { padding:24px 26px 22px; }
.protocol-modal-header.compact { padding:18px 24px; }
.protocol-modal-icon { width:42px; height:42px; flex:none; border-radius:13px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.15); color:#d9f4e3; }
.protocol-modal-header > div:nth-child(2) { min-width:0; flex:1; }
.protocol-modal-kicker { font-size:.62rem; font-weight:800; letter-spacing:.13em; color:#bce4ca; margin-bottom:3px; }
.protocol-modal-header h5 { margin:0; font-size:1.05rem; color:#fff; }
.protocol-modal-header h5 { font-size:1.12rem; letter-spacing:-.01em; }
.protocol-modal-header p { margin:5px 0 0; font-size:.8rem; color:#d8e9dd; }
.protocol-modal-header .btn-close { filter:brightness(0) invert(1); opacity:.8; align-self:flex-start; }
.protocol-modal-alert { display:flex; gap:10px; align-items:flex-start; padding:12px 13px; border:1px solid #cfe3d7; border-radius:11px; background:#f5fbf7; color:#285d40; font-size:.8rem; line-height:1.45; }
.protocol-modal-alert i { color:#2f8a58; margin-top:2px; }
.protocol-modal-alert strong { color:#174c2d; }
.protocol-step-label { display:flex; align-items:center; gap:8px; margin:20px 0 8px; color:#14532d; font-size:.76rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
.protocol-step-label { margin:22px 0 10px; }
.protocol-step-label::first-letter { background:#e7f4eb; }
.protocol-step-label .step-number { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:7px; background:#e5f3e9; color:#21623c; font-size:.72rem; }
.protocol-field { padding:16px; border:1px solid #e2ebe5; border-radius:14px; background:#fcfefd; }
.protocol-field label { display:block; margin-bottom:8px; color:#273b2d; font-size:.8rem; font-weight:800; }
.protocol-step-label:first-letter { display:inline-flex; }
.protocol-input-wrap { position:relative; }
.protocol-input-wrap > i { position:absolute; left:13px; top:14px; z-index:2; color:#7b9384; }
.protocol-input-wrap .form-control { padding-left:36px; border-color:#d6e3da; border-radius:10px; }
.protocol-input-wrap .form-control:focus { border-color:#26714d; box-shadow:0 0 0 3px rgba(38,113,77,.12); }
.protocol-input-wrap .form-control { height:48px; background:#fff; font-size:1rem; font-weight:600; letter-spacing:.01em; }
.protocol-help { margin-top:7px; color:#829188; font-size:.72rem; }
.protocol-modal-footer { border-top:1px solid #edf2ee; padding:16px 26px 20px; background:#fbfdfb; }
.protocol-modal-footer .btn { min-height:42px; border-radius:10px; font-size:.82rem; font-weight:700; padding:0 15px; }
.protocol-action-group { display:flex; align-items:center; gap:9px; }
.protocol-action-group form { display:contents !important; }
.protocol-btn-preview { border-color:#c7d9ce; color:#286143; background:#fff; }
.protocol-btn-preview:hover { border-color:#8fbaa0; color:#174c2d; background:#f2faf4; }
.protocol-btn-primary { border:0; color:#fff; background:#21834e; box-shadow:0 5px 12px rgba(33,131,78,.2); }
.protocol-btn-primary:hover { color:#fff; background:#176c3f; }
.protocol-confirm-card { display:grid; gap:11px; padding:14px; border:1px solid #e0e9e3; border-radius:12px; background:#fbfdfb; }
.protocol-confirm-card div { display:flex; justify-content:space-between; gap:14px; font-size:.8rem; }
.protocol-confirm-card span { color:#829188; }
.protocol-confirm-card strong { color:#1a2e1e; text-align:right; overflow:hidden; text-overflow:ellipsis; }

/* O fluxo de protocolo segue a mesma linguagem do modal de entrega final. */
#finalizacaoModal .protocol-modal,
#protocolConfirmModal .protocol-modal { border-radius:14px !important; }
#finalizacaoModal .protocol-modal-header,
#protocolConfirmModal .protocol-modal-header { background:#0a6b34; padding:18px 24px 16px; }
#finalizacaoModal .protocol-modal-icon,
#protocolConfirmModal .protocol-modal-icon { width:36px; height:36px; border-radius:10px; }
#finalizacaoModal .protocol-modal-footer,
#protocolConfirmModal .protocol-modal-footer { padding:14px 24px 18px; background:#fff; }
#finalizacaoModal .protocol-btn-primary,
#protocolConfirmModal .protocol-btn-primary { background:#0a6b34; border-radius:8px; }
#finalizacaoModal .protocol-btn-primary:hover,
#protocolConfirmModal .protocol-btn-primary:hover { background:#08582b; }
@media(max-width:560px) {
    .protocol-modal-header { padding:18px; }
    .protocol-modal-footer { flex-direction:column; align-items:stretch !important; gap:8px; }
    .protocol-modal-footer > div { display:flex; flex-direction:column; gap:8px; }
    .protocol-confirm-card div { display:block; }
    .protocol-confirm-card strong { display:block; text-align:left; margin-top:2px; }
    .protocol-modal-footer { padding:14px 18px 18px; }
    .protocol-action-group { width:100%; flex-direction:column-reverse; align-items:stretch; }
    .protocol-action-group .btn { width:100%; }
}
</style>

<!-- Modal: Finalização (protocolo oficial) -->
<div class="modal fade" id="finalizacaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered protocol-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4 protocol-modal">
            <div class="protocol-modal-header">
                <div class="protocol-modal-icon"><i class="fas fa-paper-plane"></i></div>
                <div>
                    <div class="protocol-modal-kicker">FINALIZAR PROCESSO</div>
                    <h5 class="modal-title fw-bold">Enviar protocolo oficial</h5>
                    <p>Informe o número que será enviado ao cidadão por e-mail.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 px-md-4">
                <div class="protocol-modal-alert">
                    <i class="fas fa-circle-check"></i>
                    <span>Depois do envio, o processo será marcado automaticamente como <strong>Finalizado</strong>.</span>
                </div>
                <div class="protocol-step-label"><span class="step-number">1</span><span>Identificação do protocolo</span></div>
                <div class="protocol-field">
                    <label for="protocolo_oficial">Protocolo Oficial da Prefeitura</label>
                    <div class="protocol-input-wrap">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" class="form-control form-control-lg" id="protocolo_oficial"
                            value="<?= htmlspecialchars($requerimento['protocolo_oficial'] ?? '') ?>"
                            placeholder="Ex.: 2025001234-SEMA" autocomplete="off">
                    </div>
                    <div class="protocol-help"><i class="fas fa-lock me-1"></i>Este número só será gravado quando o e-mail for enviado.</div>
                </div>
            </div>
            <div class="modal-footer protocol-modal-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <div class="protocol-action-group">
                    <button type="button" class="btn protocol-btn-preview" onclick="previewProtocolEmail()">
                        <i class="fas fa-eye me-1"></i>Ver prévia
                    </button>
                    <button type="button" class="btn protocol-btn-primary" onclick="showProtocolConfirmModal()">
                        Continuar <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Indeferimento de Processo -->
<div class="modal fade" id="indeferimentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content clean-action-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle text-danger me-2"></i>
                    Indeferir Processo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atenção:</strong> O requerente será notificado por e-mail sobre o indeferimento do processo.
                </div>

                <div class="mb-3">
                    <strong>Destinatário:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                </div>
                <div class="mb-3">
                    <strong>E-mail:</strong> <?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>
                </div>
                <div class="mb-3">
                    <strong>Protocolo:</strong> #<?php echo $requerimento['protocolo']; ?>
                </div>
                <div class="mb-3">
                    <strong>Tipo de Alvará:</strong> <?php echo htmlspecialchars($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'] ?? ''))); ?>
                </div>

                <div class="mb-3">
                    <strong>Motivo do Indeferimento:</strong> <span id="motivo-display"></span>
                </div>

                <div class="mb-3" id="orientacoes-display-container" style="display: none;">
                    <strong>Orientações Adicionais:</strong> <span id="orientacoes-display"></span>
                </div>
            </div>
            <div class="modal-footer clean-action-footer">
                <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <div class="clean-action-buttons">
                    <button type="button" class="btn clean-btn-preview" onclick="previewIndeferimentoEmail()">
                        Pré-visualizar e-mail
                    </button>
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="hidden_motivo_indeferimento" name="motivo_indeferimento">
                        <input type="hidden" id="hidden_orientacoes_adicionais" name="orientacoes_adicionais">
                        <button type="submit" name="indeferir_processo" class="btn btn-danger">
                            Confirmar Indeferimento
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal para Reabertura de Processo -->
<div class="modal fade" id="reopenProcessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content clean-action-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-unlock text-warning me-2"></i>
                    Reabrir Processo Finalizado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body clean-action-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Esta ação irá reabrir o processo finalizado, permitindo novas alterações.
                    </div>

                    <div class="mb-3">
                        <label for="novo_status" class="form-label">Novo Status</label>
                        <select class="form-select" id="novo_status" name="novo_status" required>
                            <option value="Em análise">Em análise</option>
                            <option value="Aprovado">Aprovado</option>
                            <option value="Reprovado">Reprovado</option>
                            <option value="Pendente">Pendente</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="motivo_reabertura" class="form-label">Motivo da Reabertura</label>
                        <textarea class="form-control" id="motivo_reabertura" name="motivo_reabertura"
                            rows="3" placeholder="Descreva o motivo da reabertura do processo..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <strong>Protocolo Atual:</strong> #<?php echo $requerimento['protocolo']; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Requerente:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                    </div>
                </div>
                <div class="modal-footer clean-action-footer">
                    <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" name="reabrir_processo" class="btn btn-warning">
                        <i class="fas fa-unlock me-2"></i>Confirmar Reabertura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Confirmação para Protocolo Oficial -->
<div class="modal fade" id="protocolConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered protocol-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4 protocol-modal">
            <div class="protocol-modal-header compact">
                <div class="protocol-modal-icon"><i class="fas fa-paper-plane"></i></div>
                <div>
                    <div class="protocol-modal-kicker">ÚLTIMA CONFIRMAÇÃO</div>
                    <h5 class="modal-title">Enviar protocolo oficial</h5>
                    <p>Confira os dados antes de notificar o cidadão.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="protocol-confirm-card">
                    <div><span>Destinatário</span><strong><?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?></strong></div>
                    <div><span>E-mail</span><strong><?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?></strong></div>
                    <div><span>Protocolo oficial</span><strong id="protocol-display">Aguardando preenchimento</strong></div>
                </div>
                <div class="protocol-modal-alert mt-3"><i class="fas fa-circle-info"></i><span>O protocolo será enviado agora. A etapa atual do processo não será alterada.</span></div>
            </div>
            <div class="modal-footer protocol-modal-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-light border" onclick="voltarParaFinalizacaoModal()">
                    Voltar
                </button>
                <div class="protocol-action-group">
                    <button type="button" class="btn protocol-btn-preview" onclick="previewProtocolEmail()">
                        <i class="fas fa-eye me-1"></i>Ver prévia
                    </button>
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="hidden_protocolo_oficial" name="protocolo_oficial">
                        <button type="submit" name="enviar_email_protocolo" class="btn protocol-btn-primary">
                            <i class="fas fa-paper-plane me-2"></i><?= strtolower((string) $requerimento['status']) === 'finalizado' ? 'Reenviar protocolo' : 'Enviar protocolo' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Arquivamento de Processo -->
<div class="modal fade" id="arquivarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content clean-action-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-archive text-warning me-2"></i>
                    Arquivar Processo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body clean-action-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> O processo será movido para o arquivo e ficará oculto da lista principal.
                    </div>

                    <div class="mb-3">
                        <strong>Protocolo:</strong> #<?php echo $requerimento['protocolo']; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Requerente:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Status Atual:</strong> <?php echo $requerimento['status']; ?>
                    </div>

                    <div class="mb-3">
                        <label for="modal_motivo_arquivamento" class="form-label">Motivo do Arquivamento</label>
                        <textarea class="form-control" id="modal_motivo_arquivamento" name="motivo_arquivamento"
                            rows="3" placeholder="Descreva o motivo do arquivamento..." required></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Informação:</strong> O processo não será deletado permanentemente e pode ser recuperado posteriormente se necessário.
                    </div>
                </div>
                <div class="modal-footer clean-action-footer">
                    <button type="button" class="btn clean-btn-cancel" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" name="arquivar_processo" class="btn btn-warning">
                        <i class="fas fa-archive me-2"></i>Confirmar Arquivamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Função para copiar texto para a área de transferência
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            const originalIcon = button.querySelector('i').className;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('copied');
            button.title = 'Copiado!';

            setTimeout(function() {
                button.innerHTML = '<i class="' + originalIcon + '"></i>';
                button.classList.remove('copied');
                button.title = 'Copiar';
            }, 2000);
        });
    }

    // === Paginação do Histórico de Ações ===
    (function () {
        const PER_PAGE = 10;
        let currentPage = 0;

        function getItems() {
            return document.querySelectorAll('[data-historico-item]');
        }

        function getTotalPages() {
            return Math.ceil(getItems().length / PER_PAGE);
        }

        function renderPage(page) {
            const items = getItems();
            if (!items.length) return;

            const total = Math.ceil(items.length / PER_PAGE);
            currentPage = Math.max(0, Math.min(page, total - 1));

            items.forEach(function (el, idx) {
                const start = currentPage * PER_PAGE;
                el.style.display = (idx >= start && idx < start + PER_PAGE) ? '' : 'none';
            });

            const info = document.getElementById('historico-page-info');
            const prev = document.getElementById('historico-prev');
            const next = document.getElementById('historico-next');

            if (info) info.textContent = 'Página ' + (currentPage + 1) + ' de ' + total;
            if (prev) prev.disabled = currentPage === 0;
            if (next) next.disabled = currentPage >= total - 1;
        }

        window.historicoChangePage = function (delta) {
            renderPage(currentPage + delta);
        };

        // Inicializar ao carregar
        document.addEventListener('DOMContentLoaded', function () {
            renderPage(0);
        });
    })();

    // Função para baixar todos os arquivos
    function downloadAllFiles() {
        const requerimentoId = <?php echo $id; ?>;
        if (!confirm('Deseja baixar todos os arquivos em um arquivo ZIP?')) {
            return;
        }
        window.location.href = 'download_arquivos.php?requerimento_id=' + requerimentoId;
    }

    // Função para mostrar modal de indeferimento
    function showIndeferimentoModal() {
        const motivoInput = document.getElementById('motivo_indeferimento');
        const motivoValue = motivoInput.value.trim();

        if (!motivoValue) {
            alert('Por favor, informe o motivo do indeferimento antes de continuar.');
            motivoInput.focus();
            return;
        }

        if (motivoValue.length < 10) {
            alert('O motivo do indeferimento deve ter pelo menos 10 caracteres.');
            motivoInput.focus();
            return;
        }

        // Buscar orientações adicionais
        const orientacoesInput = document.getElementById('orientacoes_adicionais');
        const orientacoesValue = orientacoesInput ? orientacoesInput.value.trim() : '';

        document.getElementById('motivo-display').textContent = motivoValue;
        document.getElementById('hidden_motivo_indeferimento').value = motivoValue;

        if (orientacoesValue) {
            document.getElementById('orientacoes-display').textContent = orientacoesValue;
            document.getElementById('hidden_orientacoes_adicionais').value = orientacoesValue;
            document.getElementById('orientacoes-display-container').style.display = 'block';
        } else {
            document.getElementById('orientacoes-display-container').style.display = 'none';
            document.getElementById('hidden_orientacoes_adicionais').value = '';
        }

        const modal = new bootstrap.Modal(document.getElementById('indeferimentoModal'));
        modal.show();
    }

    // Função para mostrar modal de reabertura
    function showReopenModal() {
        const modal = new bootstrap.Modal(document.getElementById('reopenProcessModal'));
        modal.show();
    }

    // Função para mostrar modal de arquivamento
    function showArquivarModal() {
        const modal = new bootstrap.Modal(document.getElementById('arquivarModal'));
        modal.show();
    }

    // Abre modal de finalização / protocolo oficial
    function abrirFinalizacaoModal() {
        const modal = new bootstrap.Modal(document.getElementById('finalizacaoModal'));
        modal.show();
    }

    // Todas as prévias passam pelo renderer real dos templates, em uma nova
    // aba com a mesma moldura Gmail da prévia do documento final.
    function abrirPreviewEmailTemplate(tipo, campos) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'preview_email_template.php';
        form.target = '_blank';
        const adicionar = (nome, valor) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = nome;
            input.value = valor == null ? '' : valor;
            form.appendChild(input);
        };
        adicionar('tipo', tipo);
        adicionar('requerimento_id', <?= (int) $id ?>);
        Object.keys(campos || {}).forEach(nome => adicionar(nome, campos[nome]));
        document.body.appendChild(form);
        form.submit();
        setTimeout(() => form.remove(), 1000);
    }

    function previewPendenciaEmail() {
        const titulo = document.getElementById('titulo_pendencia')?.value.trim() || '';
        const descricao = document.getElementById('descricao_pendencia')?.value.trim() || '';
        if (!titulo || !descricao) {
            alert('Preencha o que está faltando e o detalhamento antes de visualizar.');
            return;
        }
        abrirPreviewEmailTemplate('pendencia', {
            pendencias: titulo + '\n\n' + descricao,
            link_complementacao: 'O link seguro será incluído no envio real.'
        });
    }

    function previewBoletoEmail() {
        abrirPreviewEmailTemplate('boleto', {
            instrucoes: document.getElementById('instrucoes_boleto')?.value || '',
            url_pagamento: '<?= htmlspecialchars(gerarUrlPagamento($id, $requerimento['protocolo']), ENT_QUOTES, 'UTF-8') ?>'
        });
    }

    // Função para pré-visualizar email de protocolo oficial
    function previewProtocolEmail() {
        const protocolInput = document.getElementById('protocolo_oficial');
        const protocolValue = protocolInput.value.trim();

        if (!protocolValue) {
            alert('Por favor, informe o protocolo oficial antes de visualizar.');
            protocolInput.focus();
            return;
        }

        abrirPreviewEmailTemplate('protocolo_oficial', { protocolo_oficial: protocolValue });
        return;

        // Dados para o template
        const dados = {
            nome_destinatario: '<?php echo addslashes(htmlspecialchars($requerimento['requerente_nome'] ?? '')); ?>',
            protocolo_oficial: protocolValue
        };

        // Conteúdo do email de protocolo oficial
        const emailContent = `
            <div style="max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;">
                <div style="background-color: #ffffff; border-radius: 5px; padding: 30px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <div style="margin: 20px 0; text-align: left;">
                        <p>Prezado(a) <strong>${dados.nome_destinatario}</strong>,</p>

                        <p>Encaminhamos o número de protocolo referente ao processo requerido: <strong style="color: #009851;">${dados.protocolo_oficial}</strong></p>

                        <p>O protocolo pode ser acompanhado pelo site da Prefeitura no link
                            <a href="https://www.paudosferros.rn.gov.br" style="color: #009851; text-decoration: none;">www.paudosferros.rn.gov.br</a>
                            na aba <strong>SERVIÇOS > PORTAL DO CONTRIBUINTE > PROTOCOLO > ACOMPANHAMENTO</strong> (aqui digite o protocolo enviado).
                        </p>

                        <p>O alvará poderá ser retirado na Secretaria de Meio Ambiente / Setor de Obras quando a taxa for paga na Secretaria de Tributação.</p>

                        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                            <p>Atenciosamente,</p>
                            <p><strong>Setor de fiscalização ambiental<br>
                                    Secretaria Municipal de Meio Ambiente</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Preencher dados do modal
        document.getElementById('preview-destinatario').textContent = dados.nome_destinatario;
        document.getElementById('preview-email').textContent = '<?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>';
        document.getElementById('preview-assunto').textContent = 'Protocolo Oficial - Secretaria de Meio Ambiente';
        document.getElementById('email-preview-content').innerHTML = emailContent;

        // Mostrar modal
        const previewModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
        previewModal.show();
    }

    // Função para pré-visualizar email de indeferimento
    function previewIndeferimentoEmail() {
        const motivoValue = document.getElementById('hidden_motivo_indeferimento').value;
        const orientacoesValue = document.getElementById('hidden_orientacoes_adicionais').value;

        if (!motivoValue) {
            alert('Dados do indeferimento não encontrados.');
            return;
        }

        abrirPreviewEmailTemplate('indeferimento', {
            motivo_indeferimento: motivoValue,
            orientacoes_adicionais: orientacoesValue
        });
        return;

        // Dados para o template
        const dados = {
            nome_destinatario: '<?php echo addslashes(htmlspecialchars($requerimento['requerente_nome'] ?? '')); ?>',
            protocolo: '<?php echo $requerimento['protocolo']; ?>',
            motivo_indeferimento: motivoValue,
            orientacoes_adicionais: orientacoesValue
        };

        // Conteúdo do email de indeferimento
        let emailContent = `
            <div style="max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;">
                <div style="background-color: #ffffff; border-radius: 5px; padding: 30px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <div style="margin: 20px 0; text-align: left;">
                        <p>Prezado(a) <strong>${dados.nome_destinatario}</strong>,</p>

                        <p>Informamos que seu requerimento foi analisado pela equipe técnica da Secretaria do Meio Ambiente.</p>

                        <p><strong>PROCESSO INDEFERIDO</strong></p>

                        <p>Infelizmente, o processo de protocolo <strong style="color: #009851;">#${dados.protocolo}</strong> foi indeferido pelos seguintes motivos:</p>

                        <p><strong>${dados.motivo_indeferimento.replace(/\n/g, '<br>')}</strong></p>
        `;

        if (dados.orientacoes_adicionais) {
            emailContent += `
                        <p><strong>Orientações para Correção:</strong></p>
                        <p>${dados.orientacoes_adicionais.replace(/\n/g, '<br>')}</p>
            `;
        }

        emailContent += `
                        <p><strong>Para dar continuidade ao processo:</strong></p>
                        <ul>
                            <li>Envie um novo requerimento através do nosso sistema online</li>
                            <li>Corrija todos os pontos indicados acima</li>
                            <li>Apresente toda a documentação novamente, conforme as exigências atuais</li>
                        </ul>

                        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                            <p>Atenciosamente,<br>
                                <strong>Secretaria Municipal de Meio Ambiente</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Preencher dados do modal
        document.getElementById('preview-destinatario').textContent = dados.nome_destinatario;
        document.getElementById('preview-email').textContent = '<?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>';
        document.getElementById('preview-assunto').textContent = 'Processo Indeferido - Secretaria de Meio Ambiente';
        document.getElementById('email-preview-content').innerHTML = emailContent;

        // Mostrar modal
        const previewModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
        previewModal.show();
    }

    // Função para copiar conteúdo do email
    function copyEmailContent() {
        const content = document.getElementById('email-preview-content').innerText;
        navigator.clipboard.writeText(content).then(function() {
            // Feedback visual
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check me-2"></i>Copiado!';
            button.classList.add('btn-success');
            button.classList.remove('btn-primary');

            setTimeout(function() {
                button.innerHTML = originalContent;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
            }, 2000);
        }).catch(function(err) {
            alert('Erro ao copiar conteúdo: ' + err);
        });
    }

    // Função para mostrar modal de confirmação de protocolo
    function showProtocolConfirmModal() {
        const protocolInput = document.getElementById('protocolo_oficial');
        const protocolValue = protocolInput.value.trim();

        if (!protocolValue) {
            alert('Por favor, informe o protocolo oficial antes de continuar.');
            protocolInput.focus();
            return;
        }

        document.getElementById('protocol-display').textContent = protocolValue;
        document.getElementById('hidden_protocolo_oficial').value = protocolValue;

        const primeiraEtapa = document.getElementById('finalizacaoModal');
        const segundaEtapa = document.getElementById('protocolConfirmModal');
        const abrirConfirmacao = function () {
            const modal = bootstrap.Modal.getOrCreateInstance(segundaEtapa);
            modal.show();
        };
        const primeiraInstancia = bootstrap.Modal.getInstance(primeiraEtapa);
        if (primeiraInstancia) {
            primeiraEtapa.addEventListener('hidden.bs.modal', abrirConfirmacao, { once: true });
            primeiraInstancia.hide();
        } else {
            abrirConfirmacao();
        }
    }

    function voltarParaFinalizacaoModal() {
        const segundaEtapa = document.getElementById('protocolConfirmModal');
        const primeiraEtapa = document.getElementById('finalizacaoModal');
        const segundaInstancia = bootstrap.Modal.getInstance(segundaEtapa);
        const abrirPrimeira = function () {
            bootstrap.Modal.getOrCreateInstance(primeiraEtapa).show();
        };
        if (segundaInstancia) {
            segundaEtapa.addEventListener('hidden.bs.modal', abrirPrimeira, { once: true });
            segundaInstancia.hide();
        } else {
            abrirPrimeira();
        }
    }

     const _adminIdLogado = <?= $_adminIdLogado ?>;

     document.addEventListener('DOMContentLoaded', function() {
         carregarPareceresExistentes();
     });

     function renderCoStatus(p) {
         if (!p.co_total_esperado || p.co_total_esperado <= 1) return '';

         const total     = p.co_total_esperado;
         const assinado  = p.co_total_assinado;
         const assinantes = p.co_assinantes || [];
         const pendentes = p.co_pendentes || [];
         const recusados = p.co_recusados || [];
         const euPendente = p.co_eu_pendente;
         const solicitanteId = p.co_solicitante_id;

         let html = `<div style="margin-top:8px;padding-top:8px;border-top:1px solid #f0f0f0;">`;

         // Cabeçalho discreto: apenas a contagem de assinaturas esperadas
         const corContador = p.co_completo ? '#16a34a' : (recusados.length ? '#b91c1c' : '#9ca3af');
         html += `<div style="display:flex;align-items:center;gap:6px;font-size:.68rem;font-weight:700;color:${corContador};text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px;">
             <i class="fas fa-users" style="font-size:.62rem;"></i>
             <span>${assinado} de ${total} assinaturas</span>
         </div>`;

         // Quem já assinou — nome em destaque
         assinantes.forEach(a => {
             html += `<div style="display:flex;align-items:center;gap:6px;font-size:.74rem;color:#374151;margin-bottom:3px;">
                 <i class="fas fa-check-circle" style="font-size:.68rem;color:#16a34a;"></i>
                 <span>${escHtml(a.nome)}${a.cargo ? ` <span style="color:#9ca3af;">· ${escHtml(a.cargo)}</span>` : ''}</span>
             </div>`;
         });

         // Quem falta assinar — em cinza (discreto)
         pendentes.forEach(pend => {
             const euSou = pend.destinatario_id === _adminIdLogado;
             html += `<div style="display:flex;align-items:center;gap:6px;font-size:.74rem;color:#9ca3af;margin-bottom:3px;">
                 <i class="far fa-circle" style="font-size:.68rem;color:#cbd5e1;"></i>
                 <span>${escHtml(pend.nome)}${euSou ? ' <strong>(você)</strong>' : ''} <span style="font-style:italic;">— aguardando</span></span>
                 ${(solicitanteId === _adminIdLogado) ? `<button onclick="event.stopPropagation();cancelarCoSolic('${p.documento_id}',${pend.destinatario_id})" title="Cancelar pedido" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#cbd5e1;font-size:.7rem;padding:0;"><i class="fas fa-xmark"></i></button>` : ''}
             </div>`;
         });

         // Recusados
         recusados.forEach(rec => {
             html += `<div style="display:flex;align-items:center;gap:6px;font-size:.74rem;color:#b91c1c;margin-bottom:3px;">
                 <i class="fas fa-xmark" style="font-size:.68rem;"></i>
                 <span>${escHtml(rec.nome)} recusou${rec.motivo ? ' — ' + escHtml(rec.motivo) : ''}</span>
             </div>`;
         });

         // Botão assinar para este admin
         if (euPendente) {
             html += `<a href="coassinar_documento.php?documento_id=${encodeURIComponent(p.documento_id)}" onclick="event.stopPropagation();"
                 style="display:inline-flex;align-items:center;gap:5px;margin-top:6px;padding:5px 11px;border-radius:8px;background:#b45309;color:#fff;font-size:.76rem;font-weight:700;text-decoration:none;">
                 <i class="fas fa-pen-nib"></i> Assinar agora
             </a>`;
         }

         html += `</div>`;
         return html;
     }

     function cancelarCoSolic(documentoId, destinatarioId) {
         if (!confirm('Cancelar este pedido de co-assinatura?')) return;
         const fd = new FormData();
         fd.append('documento_id', documentoId);
         fd.append('destinatario_id', destinatarioId);
         fetch('assinatura/cancelar_solicitacao.php', { method:'POST', body:fd })
             .then(r => r.json())
             .then(d => {
                 if (d.success) carregarPareceresExistentes();
                 else alert(d.error || 'Erro ao cancelar.');
             });
     }

     function carregarPareceresExistentes() {
         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'listar_pareceres',
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             const lista = document.getElementById('pareceres-existentes-list');
             const secao = document.getElementById('secao-docs-assinados');
             const grid  = document.getElementById('docs-assinados-grid');
             const badge = document.getElementById('badge-docs-count');

             if (!data.pareceres || data.pareceres.length === 0) {
                 lista.innerHTML = '<p class="text-muted small px-1 mb-0">Nenhum documento assinado ainda.</p>';
                 secao.style.display = 'none';
                 return;
             }

             // ── Mini-lista na seção de ações (compacta) ──────────
             lista.innerHTML = '';
             data.pareceres.forEach(p => {
                 const viewerUrl   = p.documento_id ? `parecer_viewer.php?id=${p.documento_id}` : `../arquivo.php?path=${encodeURIComponent('pareceres/<?php echo $id; ?>/' + p.arquivo)}`;
                 const downloadUrl = p.documento_id ? `assinatura/redownload_pdf.php?id=${encodeURIComponent(p.documento_id)}` : `../arquivo.php?path=${encodeURIComponent('pareceres/<?php echo $id; ?>/' + p.arquivo)}`;
                 const { iconClass, iconColor } = obterIconeParecer(p.tipo);
                 const nomeLimpo = formatarNomeParecer(p.nome);
                 const seloTipo  = gerarSeloTipoParecer(p.tipo);
                 const coHtml    = renderCoStatus(p);

                 const rowViewerUrl = !p.apagado ? (downloadUrl ? downloadUrl + '&inline=1' : viewerUrl) : '';
                 lista.innerHTML += `
                    <div class="data-row doc-row-clickable" style="flex-wrap:wrap;${rowViewerUrl ? 'cursor:pointer;' : ''}" data-viewer-url="${escHtml(rowViewerUrl || '')}" title="${rowViewerUrl ? 'Abrir documento' : ''}">
                        <div class="data-label" style="min-width:40px">
                            <i class="fas ${iconClass}" style="color:${iconColor};font-size:20px"></i>
                        </div>
                        <div class="data-value" style="flex:1;min-width:0;">
                            <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                                <span>${nomeLimpo}</span>${seloTipo}
                            </div>
                            <div class="text-muted small">${p.data} • ${formatarTamanhoArquivo(p.tamanho)}
                                ${p.assinante ? `<br><span class="text-primary"><i class="fas fa-user-check me-1"></i>Assinado por: ${p.assinante}</span>` : ''}
                            </div>
                            ${coHtml ? `<div style="margin-top:6px;">${coHtml}</div>` : ''}
                        </div>
                        <div class="data-actions">
                            ${!p.apagado && downloadUrl ? `<a href="${downloadUrl}&inline=1" class="copy-btn me-1" title="Visualizar PDF" target="_blank" onclick="event.stopPropagation()" style="color:#2563eb"><i class="fas fa-eye"></i></a>` : ''}
                            ${!p.apagado && downloadUrl ? `<a href="${downloadUrl}" class="copy-btn me-1" title="Baixar PDF" onclick="event.stopPropagation()"><i class="fas fa-download"></i></a>` : ''}
                            <button onclick="event.stopPropagation();excluirDocAssinado('${p.documento_id}')" class="copy-btn" title="Excluir" style="color:#dc2626"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>`;
             });

             // ── Cards na seção de documentos assinados ───────────
             secao.style.display = '';
             badge.textContent = data.pareceres.length + ' documento(s)';
             grid.innerHTML = '';
             data.pareceres.forEach(p => {
                 const viewerUrl   = p.documento_id ? `parecer_viewer.php?id=${p.documento_id}` : null;
                 const downloadUrl = p.documento_id ? `assinatura/redownload_pdf.php?id=${encodeURIComponent(p.documento_id)}` : null;
                 const iniciais    = (p.assinante || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
                 const nomeLimpo   = formatarNomeParecer(p.nome);
                 const coHtml      = renderCoStatus(p);

                 // Badge de status de co-assinatura para o topo do card
                 let coTopBadge = '';
                 if (p.co_total_esperado > 1) {
                     if (p.co_completo) {
                         coTopBadge = '<span class="badge" style="background:#f0fdf4;color:#15803d;border:1px solid #86efac;font-size:.62rem;"><i class="fas fa-users-check me-1"></i>Todas assinaram</span>';
                     } else if ((p.co_pendentes||[]).length > 0) {
                         coTopBadge = `<span class="badge" style="background:#fffbeb;color:#b45309;border:1px solid #fcd34d;font-size:.62rem;"><i class="fas fa-hourglass-half me-1"></i>${p.co_pendentes.length} aguardando</span>`;
                     } else if ((p.co_recusados||[]).length > 0) {
                         coTopBadge = `<span class="badge" style="background:#fff1f2;color:#b91c1c;border:1px solid #fecdd3;font-size:.62rem;"><i class="fas fa-xmark me-1"></i>Recusada</span>`;
                     }
                 }

                 const docViewerUrl = p.documento_id ? `visualizar_documento.php?requerimento_id=<?php echo $id ?>&documento_id=${encodeURIComponent(p.documento_id)}` : '';
                 grid.innerHTML += `
                    <div class="doc-card-clickable" data-viewer-url="${escHtml(docViewerUrl)}"
                         style="border:1px solid ${p.co_eu_pendente ? '#fcd34d' : '#e8e8e8'};border-radius:8px;padding:14px;background:${p.co_eu_pendente ? '#fffdf0' : '#fff'};box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;flex-direction:column;${docViewerUrl ? 'cursor:pointer;transition:border-color .15s,box-shadow .15s;' : ''}">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #f0f0f0;gap:6px;flex-wrap:wrap;">
                            <span style="font-family:monospace;font-size:.68rem;color:#888;background:#f5f5f5;padding:3px 7px;border-radius:4px;">
                                <i class="fas fa-fingerprint me-1"></i>${p.documento_id ? p.documento_id.substring(0,12) + '…' : '—'}
                            </span>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                ${p.apagado ? '<span class="badge bg-danger" style="font-size:.65rem"><i class="fas fa-trash me-1"></i>Apagado</span>' :
                                              '<span class="badge" style="background:#f0fdf4;color:#1c4b36;border:1px solid #bbf7d0;font-size:.65rem"><i class="fas fa-check-circle me-1"></i>Assinado</span>'}
                                ${coTopBadge}
                            </div>
                        </div>
                        <div style="font-size:.78rem;font-weight:600;color:#333;margin-bottom:8px;word-break:break-word;">${nomeLimpo}</div>
                        <div style="display:flex;align-items:center;gap:8px;padding:8px;background:#fafafa;border-radius:6px;margin-bottom:10px;border:1px solid #f0f0f0;">
                            <div style="width:32px;height:32px;border-radius:50%;background:#1c4b36;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0;">${iniciais}</div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;color:#333;font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(p.assinante || '—')}</div>
                                <div style="font-size:.7rem;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(p.cargo || '')} • ${p.data}</div>
                            </div>
                        </div>
                        ${coHtml}
                        <div class="doc-card-actions" style="display:flex;gap:6px;margin-top:auto;padding-top:10px;border-top:1px solid #f0f0f0;">
                            ${!p.apagado && downloadUrl ? `<a href="${downloadUrl}&inline=1" class="btn btn-sm btn-outline-primary" style="font-size:.75rem" title="Visualizar PDF" target="_blank" onclick="event.stopPropagation()"><i class="fas fa-eye"></i></a>` : ''}
                            ${!p.apagado && downloadUrl ? `<a href="${downloadUrl}" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem" title="Baixar PDF" onclick="event.stopPropagation()"><i class="fas fa-download"></i></a>` : ''}
                            <button data-excluir-doc="${escHtml(p.documento_id)}" class="btn btn-sm btn-outline-danger" style="font-size:.75rem" title="Excluir"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>`;
             });
         })
         .catch(error => console.error('Erro ao carregar pareceres:', error));
     }

     // Delegação de click nas linhas da mini-lista de documentos (abre o documento)
     document.getElementById('pareceres-existentes-list').addEventListener('click', function(e) {
         if (e.target.closest('a, button')) return; // deixa botões/links agirem
         const row = e.target.closest('.doc-row-clickable');
         if (row && row.dataset.viewerUrl) {
             window.open(row.dataset.viewerUrl, '_blank');
         }
     });

     // Delegação de click nos cards de documento assinado
     document.getElementById('docs-assinados-grid').addEventListener('click', function(e) {
         const btn = e.target.closest('[data-excluir-doc]');
         if (btn) { excluirDocAssinado(btn.dataset.excluirDoc); return; }
         const card = e.target.closest('.doc-card-clickable');
         if (card && card.dataset.viewerUrl) {
             window.location.href = card.dataset.viewerUrl;
         }
     });

     function excluirDocAssinado(docId) {
         if (!confirm('Remover este documento da listagem?')) return;
         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({ action: 'excluir_documento_assinado', documento_id: docId, permanente: false })
         })
         .then(r => r.json())
         .then(d => { if (d.success) carregarPareceresExistentes(); else alert(d.error || 'Erro ao excluir'); });
     }

     function escHtml(str) {
         return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
     }

     function _REMOVIDO_carregarPareceresDocumentos_OLD(pareceres) {
         const listaDocumentos = document.getElementById('pareceres-documentos-list');
         const pareceresSection = document.getElementById('pareceres-section');

         if (pareceres.length === 0) {
             pareceresSection.style.display = 'none';
         } else {
             pareceresSection.style.display = 'block';
             let html = '';
             pareceres.forEach(p => {
                 const viewerUrl = p.documento_id ? `parecer_viewer.php?id=${p.documento_id}` : `../arquivo.php?path=${encodeURIComponent('pareceres/<?php echo $id; ?>/' + p.arquivo)}`;
                const { iconClass, iconColor } = obterIconeParecer(p.tipo);
                const nomeLimpo = formatarNomeParecer(p.nome);
                const seloTipo = gerarSeloTipoParecer(p.tipo);

                 html += `
                     <div class="data-row">
                         <div class="data-label" style="min-width: 40px;">
                             <i class="fas ${iconClass}" style="color: ${iconColor}; font-size: 20px;"></i>
                         </div>
                         <div class="data-value">
                            <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                                <span>${nomeLimpo}</span>
                                ${seloTipo}
                            </div>
                            <div class="text-muted small">${p.data} • ${formatarTamanhoArquivo(p.tamanho)}${p.assinante ? `<br><span class="text-primary"><i class="fas fa-user-check me-1"></i>Assinado por: ${p.assinante}</span>` : "" }</div>
                         </div>
                         <div class="data-actions">
                             <a href="${viewerUrl}"
                                class="copy-btn me-1"
                                target="_blank"
                                title="Visualizar parecer">
                                 <i class="fas fa-eye"></i>
                             </a>
                             <a href="parecer_handler.php?action=download_parecer&arquivo=${p.arquivo}&requerimento_id=<?php echo $id; ?>"
                                class="copy-btn me-1"
                                title="Baixar parecer">
                                 <i class="fas fa-download"></i>
                             </a>
                             <button onclick="excluirParecer('${p.arquivo}')" class="copy-btn" title="Excluir parecer" style="color: #dc2626;">
                                 <i class="fas fa-trash"></i>
                             </button>
                         </div>
                     </div>
                 `;
             });
             listaDocumentos.innerHTML = html;
         }
     }

     function formatarTamanhoArquivo(bytes) {
         if (bytes === 0) return '0 Bytes';
         const k = 1024;
         const sizes = ['Bytes', 'KB', 'MB', 'GB'];
         const i = Math.floor(Math.log(bytes) / Math.log(k));
         return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
     }

     function formatarNomeParecer(nomeArquivo) {
         if (!nomeArquivo) return 'Parecer técnico';

         const semExtensao = nomeArquivo.replace(/\.[^.]+$/, '');
         let legivel = semExtensao
             .replace(/^parecer[_-]?/i, '')
             .replace(/^assinatura[_-]?/i, '')
             .replace(/[_-]?\d{8,}$/i, '')
             .replace(/[_-]+/g, ' ')
             .trim();

         if (!legivel) {
             legivel = semExtensao;
         }

         return legivel
             .split(' ')
             .map(p => p.charAt(0).toUpperCase() + p.slice(1))
             .join(' ');
     }

     function obterIconeParecer(tipo) {
         if (tipo === 'pdf') {
             return { iconClass: 'fa-file-pdf', iconColor: '#dc2626' };
         }
         return { iconClass: 'fa-file-signature', iconColor: '#0ea5e9' };
     }

     function gerarSeloTipoParecer(tipo) {
         const label = tipo === 'pdf' ? 'PDF assinado' : 'Digital';
         const cor = tipo === 'pdf' ? '#fef2f2' : '#e0f2fe';
         const texto = tipo === 'pdf' ? '#b91c1c' : '#0ea5e9';
         return `<span class="badge rounded-pill" style="background:${cor}; color:${texto}; font-size: 11px;">${label}</span>`;
     }

     function excluirParecer(arquivo) {
         if (!confirm('Deseja excluir este parecer?')) return;

         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'excluir_parecer',
                 arquivo: arquivo,
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             if (data.success) {
                 carregarPareceresExistentes(); // Isso também atualizará a aba de documentos
             } else {
                 alert('Erro ao excluir: ' + data.error);
             }
         })
         .catch(error => {
             console.error('Erro ao excluir parecer:', error);
             alert('Erro ao excluir parecer');
         });
     }

     function resetarFluxoParecer(resetTemplate = false) {
         const etapaSelecao = document.getElementById('etapa-selecao-template');
         const etapaEditor = document.getElementById('etapa-editor');
         const etapaPosicionamento = document.getElementById('etapa-posicionamento');

         if (etapaSelecao) etapaSelecao.style.display = 'block';
         if (etapaEditor) etapaEditor.style.display = 'none';
         if (etapaPosicionamento) etapaPosicionamento.style.display = 'none';

         if (resetTemplate) {
             const select = document.getElementById('template-select');
             if (select) select.value = '';
         }

         if (tinymce.get('editor-parecer-content')) {
             tinymce.remove('#editor-parecer-content');
         }

         dadosAssinatura = null;
         coordenadasAssinatura = { x: 0, y: 0 };
         templateAtual = null;

         const senhaFinalizacao = document.getElementById('senha-finalizacao');
         if (senhaFinalizacao) senhaFinalizacao.value = '';
         const erroSenhaEl = document.getElementById('erro-senha-finalizacao');
         if (erroSenhaEl) erroSenhaEl.style.display = 'none';

         if (configAssinaturaModal) {
             configAssinaturaModal.hide();
         }
     }
</script>

<?php
// Função para obter a classe de cor com base no status
function getStatusClass($status)
{
    switch ($status) {
        case 'Aprovado':
            return 'success';
        case 'Finalizado':
            return 'purple';
        case 'Reprovado':
            return 'danger';
        case 'Em análise':
            return 'warning';
        case 'Pendente':
            return 'info';
        case 'Aguardando boleto':
            return 'warning';
        case 'Boleto pago':
            return 'success';
        case 'Cancelado':
            return 'secondary';
        default:
            return 'primary';
    }
}

function getStatusDotColor($status)
{
    switch (strtolower($status)) {
        case 'pendente':
            return '#f59e0b'; // amarelo
        case 'aprovado':
            return '#10b981'; // verde
        case 'aguardando boleto':
            return '#f59e0b'; // âmbar
        case 'boleto pago':
            return '#0f766e'; // teal escuro
        case 'finalizado':
            return '#8b5cf6'; // roxo
        case 'indeferido':
            return '#dc2626'; // vermelho forte
        case 'reprovado':
        case 'rejeitado':
            return '#ef4444'; // vermelho
        case 'em análise':
        case 'em_analise':
            return '#3b82f6'; // azul
        case 'cancelado':
            return '#6c757d'; // cinza
        default:
            return '#6b7280'; // cinza
    }
}
?>

<!-- O Modal de Verificação de Segurança (modalVerificacaoSeguranca) foi removido pois a checagem ocorre agora no Login -->

<!-- Modal de Sucesso -->
<div class="modal fade" id="modalSucessoAssinatura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center px-4 py-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center" style="width: 90px; height: 90px; box-shadow: 0 0 0 10px rgba(25, 135, 84, 0.1);">
                        <i class="fas fa-check text-white" style="font-size: 40px;"></i>
                    </div>
                </div>
                
                <h3 class="fw-bold text-success mb-2">Sucesso!</h3>
                <h5 class="fw-bold mb-3">Parecer Assinado Digitalmente</h5>
                
                <p class="text-muted mb-4">
                    O documento foi gerado, assinado e registrado com sucesso.
                    <br>O protocolo de autenticidade já está ativo.
                </p>
                
                <div class="d-grid gap-2 col-8 mx-auto">
                    <button type="button" class="btn btn-success btn-lg" data-bs-dismiss="modal">
                        <i class="fas fa-thumbs-up me-2"></i> Entendido
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-visualizar-sucesso">
                        <i class="fas fa-external-link-alt me-2"></i> Visualizar Documento
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="fas fa-check-circle fa-lg"></i>
                <span id="toastMessage">Operação realizada com sucesso!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
    // Variável global para o toast
    let toastInstance = null;

    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('liveToast');
        const toastBody = document.querySelector('#liveToast .toast-body span');
        const toastDiv = document.getElementById('liveToast');
        
        // Inicializar o toast apenas quando necessário (garante que o Bootstrap já carregou)
        if (!toastInstance && typeof bootstrap !== 'undefined') {
            toastInstance = new bootstrap.Toast(toastEl, { delay: 5000 });
        }
        
        toastBody.textContent = message;
        
        // Reset classes
        toastDiv.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');
        
        // Add new class based on type
        const toastIcon = document.querySelector('#liveToast .toast-body i');
        toastIcon.className = 'fa-lg fas ' + (
            type === 'success' ? 'fa-check-circle' :
            (type === 'error' || type === 'danger') ? 'fa-exclamation-circle' :
            type === 'warning' ? 'fa-triangle-exclamation' :
            type === 'info' ? 'fa-circle-info' : 'fa-check-circle'
        );
        switch(type) {
            case 'success': toastDiv.classList.add('bg-success'); break;
            case 'error':
            case 'danger': toastDiv.classList.add('bg-danger'); break;
            case 'warning': toastDiv.classList.add('bg-warning'); break;
            case 'info': toastDiv.classList.add('bg-info'); break;
            default: toastDiv.classList.add('bg-primary');
        }

        if (toastInstance) {
            toastInstance.show();
        } else {
            // Fallback caso o Bootstrap falhe ao carregar
            alert(message);
        }
    }
</script>

<script>
// Inicializa tooltips de Bootstrap nos botões de ação
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, { trigger: 'hover', delay: { show: 200, hide: 80 } });
    });

    // Mantém a aba escolhida na URL: recarregar, copiar o link ou voltar do
    // editor preserva exatamente o contexto em que o operador estava.
    document.querySelectorAll('#requerimentoTabs [data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            var target = event.target.getAttribute('data-bs-target');
            if (!target) return;
            var url = new URL(window.location.href);
            url.searchParams.set('tab', target.replace('#', ''));
            window.history.replaceState({}, '', url.toString());
        });
    });
});
</script>

<script>
// Prévia do e-mail de entrega: repassa a seleção atual do modal para uma página
// que renderiza o template real numa nova aba. Não envia nem grava nada.
function previewEmailDocFinal() {
    var form = document.getElementById('formDocFinal');
    if (!form) return;

    var previa = document.createElement('form');
    previa.method = 'post';
    previa.action = 'preview_email_doc_final.php';
    previa.target = '_blank';

    function campo(nome, valor) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = nome;
        input.value = valor;
        previa.appendChild(input);
    }

    campo('requerimento_id', <?= (int) $id ?>);
    form.querySelectorAll('.doc-final-cb:checked').forEach(function(cb) {
        campo('documento_ids[]', cb.value);
    });
    var obs = document.getElementById('instrucoes_doc_final');
    campo('instrucoes_doc_final', obs ? obs.value : '');

    document.body.appendChild(previa);
    previa.submit();
    previa.remove();
}

// ── Modal de envio de documentos ao cidadão ──────────────────────────────────
(function() {
    var form = document.getElementById('formDocFinal');
    if (!form) return;

    var checkboxes    = form.querySelectorAll('.doc-final-cb');
    var selectAll     = document.getElementById('docFinalSelectAll');
    var resumoBox     = document.getElementById('docFinalResumo');
    var btnEnviar     = document.getElementById('btnEnviarDocFinal');
    var emailDest     = <?= json_encode($emailDestinatario ?: null, JSON_HEX_TAG) ?>;

    // Highlight visual nos itens selecionados
    function atualizarResumo() {
        var selecionados = 0, semSec = 0;
        checkboxes.forEach(function(cb) {
            var item = cb.closest('.doc-final-check-item');
            item.classList.toggle('is-sel', cb.checked);
            if (cb.checked) {
                selecionados++;
                if (item.dataset.temSec === '0') semSec++;
            }
        });

        // Resumo
        if (selecionados === 0) {
            resumoBox.style.display = 'none';
            btnEnviar.disabled = true;
        } else {
            var html = '<strong>' + selecionados + '</strong> documento' + (selecionados > 1 ? 's' : '') + ' selecionado' + (selecionados > 1 ? 's' : '');

            if (emailDest) {
                html += ' — será enviado para <strong>' + escapeHtml(emailDest) + '</strong>';
            } else {
                html += ' <span style="color:#b42318;font-weight:600;">— sem e-mail cadastrado</span>';
            }

            if (semSec > 0) {
                html += '<br>';
                html += semSec === selecionados
                    ? 'Nenhum dos documentos foi assinado pelo Secretário'
                    : semSec + ' documento' + (semSec > 1 ? 's' : '') + ' sem assinatura do Secretário';
            }

            resumoBox.innerHTML = html;
            resumoBox.style.display = 'block';
            // Mesma barra lateral do aviso de reenvio; só a cor muda com o estado.
            resumoBox.style.borderLeftColor = semSec > 0 ? '#d99b16' : '#0a6b34';
            resumoBox.style.color = semSec > 0 ? '#6b5320' : '#245c3a';
            btnEnviar.disabled = !emailDest;
        }

        // Sincronizar "selecionar todos"
        if (selectAll) {
            selectAll.checked = selecionados === checkboxes.length && selecionados > 0;
            selectAll.indeterminate = selecionados > 0 && selecionados < checkboxes.length;
        }
    }

    // Eventos nos checkboxes individuais
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', atualizarResumo);
    });

    // "Selecionar todos"
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            atualizarResumo();
        });
    }

    // Estado inicial
    atualizarResumo();

    // Validação + confirmação antes de enviar
    form.addEventListener('submit', function(e) {
        if (!emailDest) {
            e.preventDefault();
            alert('Cadastre um e-mail válido para o cidadão antes de finalizar o processo.');
            return;
        }
        var checked = form.querySelectorAll('.doc-final-cb:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um documento para enviar.');
            return;
        }

        var algumComSec = Array.from(checked).some(function(cb) {
            var item = cb.closest('.doc-final-check-item');
            return item && item.dataset.temSec === '1';
        });

        if (!algumComSec) {
            if (!confirm('Nenhum dos documentos selecionados foi assinado pelo Secretário.\n\nDeseja enviar mesmo assim?')) {
                e.preventDefault();
                return;
            }
        }

        // Confirmação final
        var msg = 'Confirma o envio de ' + checked.length + ' documento' + (checked.length > 1 ? 's' : '') + ' ao cidadão?';
        msg += '\n\nApós o envio, o processo será marcado como Finalizado.';
        if (!emailDest) {
            msg += '\n\n⚠ O requerente NÃO tem e-mail cadastrado — ele não receberá notificação.';
        }

        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    function escapeHtml(text) {
        var d = document.createElement('span');
        d.textContent = text;
        return d.innerHTML;
    }
})();
</script>
<style>
.quick-editable { position:relative; cursor:text; transition:background .15s; border-radius:4px; }
.quick-editable:hover, .quick-editable:focus-within { background:#f8fbf9; }
.quick-edit-btn, .quick-original-btn { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; margin-left:3px; padding:0; border:0; border-radius:4px; background:transparent; color:#809087; font-size:.62rem; vertical-align:middle; opacity:0; transition:opacity .15s, color .15s, background .15s; }
.quick-editable:hover .quick-edit-btn, .quick-editable:focus-within .quick-edit-btn, .quick-editable.is-edited .quick-original-btn { opacity:1; }
.quick-edit-btn:hover, .quick-original-btn:hover { background:#eef5f0; color:#286143; }
.quick-original-btn { color:#7c8991; }
.quick-original-value { display:none; margin-top:5px; padding:5px 7px; border-left:2px solid #9aaab5; color:#64748b; font-size:.68rem; font-weight:400; line-height:1.35; }
.quick-editable.show-original .quick-original-value { display:block; }
.quick-edit-hint { color:#8a9b91; font-size:.68rem; font-weight:600; white-space:nowrap; }
.quick-edit-hint i { color:#5a8a6a; }
.quick-inline-input { display:inline-block; width:min(100%,260px); min-height:30px; padding:4px 7px; border:1px solid #a9c5b2; border-radius:5px; background:#fff; color:#1a2e1e; font:inherit; font-weight:500; outline:0; vertical-align:middle; }
textarea.quick-inline-input { min-height:54px; resize:vertical; vertical-align:top; }
.quick-inline-input:focus { border-color:#378257; box-shadow:0 0 0 2px rgba(55,130,87,.12); }
.quick-inline-actions { display:inline-flex; gap:2px; margin-left:4px; vertical-align:middle; }
.quick-inline-actions button { width:22px; height:22px; padding:0; border:0; border-radius:4px; background:#eef7f0; color:#257044; font-size:.65rem; }
.quick-inline-actions .quick-inline-cancel { background:#f3f5f4; color:#78857e; }
.quick-inline-actions.is-saving { opacity:.5; pointer-events:none; }
.quick-inline-error { display:block; margin-top:4px; color:#b42318; font-size:.68rem; font-weight:500; }
@media(max-width:640px) { .quick-edit-hint { display:none; } .quick-edit-btn { opacity:.45; } }
</style>
<script>
(function () {
    const csrf = <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>;
    const originais = <?= json_encode($valoresOriginaisProcesso, JSON_UNESCAPED_UNICODE) ?>;
    const campos = document.querySelectorAll('.quick-editable[data-quick-field]');

    function escapeHtml(value) {
        const el = document.createElement('span');
        el.textContent = value == null || value === '' ? 'Não informado' : value;
        return el.innerHTML;
    }

    function renderValue(element, value) {
        const valueNode = element.querySelector('.quick-value') || element;
        if (element.dataset.quickField === 'requerente_email') {
            valueNode.innerHTML = '<a href="mailto:' + escapeHtml(value) + '">' + escapeHtml(value) + '</a>';
        } else if (element.dataset.quickField === 'requerente_telefone') {
            valueNode.innerHTML = '<a href="tel:' + escapeHtml(value) + '">' + escapeHtml(value) + '</a>';
        } else {
            valueNode.textContent = value || 'Não informado';
        }
    }

    function showOriginal(element, original) {
        let box = element.querySelector('.quick-original-value');
        if (!box) {
            box = document.createElement('div');
            box.className = 'quick-original-value';
            element.appendChild(box);
        }
        box.innerHTML = '<i class="fas fa-history me-1"></i>Original: ' + escapeHtml(original);
        element.classList.add('is-edited');
        let button = element.querySelector('.quick-original-btn');
        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.className = 'quick-original-btn';
            button.title = 'Mostrar/ocultar valor original';
            button.innerHTML = '<i class="fas fa-clock-rotate-left"></i>';
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                element.classList.toggle('show-original');
            });
            element.appendChild(button);
        }
    }

    function editar(element) {
        if (element.classList.contains('is-editing')) return;
        const campo = element.dataset.quickField;
        const atual = element.dataset.quickValue || '';
        element.classList.add('is-editing');
        const valueNode = element.querySelector('.quick-value');
        const editor = document.createElement(['endereco_objetivo','especificacao'].includes(campo) ? 'textarea' : 'input');
        editor.className = 'quick-inline-input';
        editor.value = atual;
        editor.rows = 2;
        if (campo === 'requerente_email') {
            editor.type = 'email';
            editor.inputMode = 'email';
            editor.maxLength = 191;
        }
        editor.setAttribute('aria-label', 'Editar ' + campo);
        const actions = document.createElement('span');
        actions.className = 'quick-inline-actions';
        actions.innerHTML = '<button type="button" class="quick-inline-save" title="Salvar"><i class="fas fa-check"></i></button><button type="button" class="quick-inline-cancel" title="Cancelar"><i class="fas fa-xmark"></i></button>';
        valueNode.style.display = 'none';
        element.insertBefore(editor, valueNode);
        element.insertBefore(actions, valueNode);
        editor.focus();
        editor.select();

        const fechar = () => { editor.remove(); actions.remove(); valueNode.style.display = ''; element.classList.remove('is-editing'); };
        actions.querySelector('.quick-inline-cancel').addEventListener('click', function (event) { event.stopPropagation(); fechar(); });
        actions.querySelector('.quick-inline-save').addEventListener('click', function (event) {
            event.stopPropagation();
            const valor = editor.value.trim();
            if (valor === atual.trim()) { fechar(); return; }
            const form = new FormData();
            form.append('editar_campo_processo', '1');
            form.append('csrf_token', csrf);
            form.append('campo', campo);
            form.append('valor', valor);
            actions.classList.add('is-saving');
            fetch(window.location.href, { method:'POST', body:form, headers:{'X-Requested-With':'XMLHttpRequest'} })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.erro || 'Não foi possível salvar.');
                    const original = Object.prototype.hasOwnProperty.call(originais, campo) ? originais[campo] : atual;
                    fechar();
                    renderValue(element, data.valor);
                    element.dataset.quickValue = data.valor;
                    originais[campo] = original;
                    showOriginal(element, original);
                    if (typeof showToast === 'function') showToast('Campo atualizado.', 'success');
                })
                .catch(error => {
                    let message = element.querySelector('.quick-inline-error');
                    if (!message) { message = document.createElement('small'); message.className = 'quick-inline-error'; element.appendChild(message); }
                    message.textContent = error.message;
                    actions.classList.remove('is-saving');
                });
        });
        editor.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') fechar();
            if (event.key === 'Enter' && !event.shiftKey && editor.tagName !== 'TEXTAREA') actions.querySelector('.quick-inline-save').click();
        });
    }

    campos.forEach(function (element) {
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'quick-edit-btn';
        edit.title = 'Editar rapidamente';
        edit.innerHTML = '<i class="fas fa-pencil"></i>';
        edit.addEventListener('click', function (event) { event.stopPropagation(); editar(element); });
        element.appendChild(edit);
        const campo = element.dataset.quickField;
        if (Object.prototype.hasOwnProperty.call(originais, campo)) showOriginal(element, originais[campo]);
    });
})();
</script>
<?php include 'footer.php'; ?>
