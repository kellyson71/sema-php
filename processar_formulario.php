<?php
require_once 'includes/config.php';

// Redireciona apenas o ambiente principal; homologação deve permanecer local.
$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $requestUri;
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}

// Incluir arquivo com os tipos de alvará
include_once 'tipos_alvara.php';

// Iniciar sessão para mensagens flash
session_start();

// Inclusão de arquivos necessários
require_once 'includes/functions.php';
require_once 'includes/models.php';
require_once 'includes/email_service.php';
require_once 'includes/admin_notifications.php';

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se o tipo de alvará foi informado
    if (empty($_POST['tipo_alvara'])) {
        $_SESSION['form_data'] = $_POST;
        setMensagem('erro', 'É necessário selecionar um tipo de alvará.');
        redirect('index.php');
    }

    // Inicialização dos modelos
    $requerenteModel = new Requerente();
    $proprietarioModel = new Proprietario();
    $requerimentoModel = new Requerimento();
    $documentoModel = new Documento();
    $database = new Database();
    $pdo = $database->getConnection();

    // Regras de negócio derivadas da fonte de verdade (tipos_alvara.php)
    $tipoInfo = $tipos_alvara[$_POST['tipo_alvara'] ?? ''] ?? null;
    $isAmbiental = ($tipoInfo['categoria'] ?? '') === 'ambiental';
    $exigeCTF = $tipoInfo['exige_ctf'] ?? false;
    $exigeLicencaAnterior = $tipoInfo['exige_licenca_anterior'] ?? false;

    // Dados do requerente
    $requerente = [
        'nome' => $_POST['requerente']['nome'] ?? '',
        'email' => $_POST['requerente']['email'] ?? '',
        'cpf_cnpj' => $_POST['requerente']['cpf_cnpj'] ?? '',
        'telefone' => $_POST['requerente']['telefone'] ?? ''
    ];

    // Validação dos dados do requerente
    if (empty($requerente['nome']) || empty($requerente['email']) || empty($requerente['cpf_cnpj']) || empty($requerente['telefone'])) {
        $_SESSION['form_data'] = $_POST;
        setMensagem('erro', 'Todos os campos do requerente são obrigatórios.');
        redirect('index.php');
    }

    // Salvar requerente
    $requerente_id = $requerenteModel->criar($requerente);

    // Verificar se o proprietário é o mesmo que o requerente
    $mesmo_requerente = isset($_POST['mesmo_requerente']) && $_POST['mesmo_requerente'] === 'true';

    if ($mesmo_requerente) {
        // Se for o mesmo, copia os dados do requerente
        $proprietario = [
            'nome' => $requerente['nome'],
            'cpf_cnpj' => $requerente['cpf_cnpj'],
            'mesmo_requerente' => 1,
            'requerente_id' => $requerente_id
        ];
    } else {
        // Se não for o mesmo, pega os dados informados
        $proprietario = [
            'nome' => $_POST['proprietario']['nome'] ?? '',
            'cpf_cnpj' => $_POST['proprietario']['cpf_cnpj'] ?? '',
            'mesmo_requerente' => 0,
            'requerente_id' => $requerente_id
        ];

        // Validação dos dados do proprietário APENAS se foram preenchidos
        if (!empty($proprietario['nome']) || !empty($proprietario['cpf_cnpj'])) {
            if (empty($proprietario['nome']) || empty($proprietario['cpf_cnpj'])) {
                $_SESSION['form_data'] = $_POST;
                setMensagem('erro', 'Se informar dados do proprietário, preencha nome E CPF/CNPJ.');
                redirect('index.php');
            }
        } else {
            // Se não informou nada, usa os dados do requerente
            $proprietario = [
                'nome' => $requerente['nome'],
                'cpf_cnpj' => $requerente['cpf_cnpj'],
                'mesmo_requerente' => 1,
                'requerente_id' => $requerente_id
            ];
        }
    }

    // Salvar proprietário
    $proprietario_id = $proprietarioModel->criar($proprietario);

    // Dados do requerimento
    $protocolo = gerarProtocolo();
    $tipoAlvara = $_POST['tipo_alvara'];

    // Campos adicionais
    $ctf_numero = trim($_POST['ctf_numero'] ?? '');
    $licenca_anterior_numero = trim($_POST['licenca_anterior_numero'] ?? '');
    $publicacao_diario_oficial = trim($_POST['publicacao_diario_oficial'] ?? '');
    $comprovante_pagamento = trim($_POST['comprovante_pagamento'] ?? '');
    $possui_estudo = isset($_POST['possui_estudo_ambiental']) ? (int) $_POST['possui_estudo_ambiental'] : null;
    $tipo_estudo_ambiental = trim($_POST['tipo_estudo_ambiental'] ?? '');
    $data_certidao_municipal = $_POST['data_certidao_municipal'] ?? '';
    $enquadramento_atividade = trim($_POST['enquadramento_atividade'] ?? '');
    $localizacao_google_maps = trim($_POST['localizacao_google_maps'] ?? '');
    
    // Campos adicionais dos templates
    $area_construcao = trim($_POST['area_construcao'] ?? '');
    $numero_pavimentos = trim($_POST['numero_pavimentos'] ?? '');
    $area_construida = trim($_POST['area_construida'] ?? '');
    $area_lote = trim($_POST['area_lote'] ?? '');
    $responsavel_tecnico_nome = trim($_POST['responsavel_tecnico_nome'] ?? '');
    $responsavel_tecnico_registro = trim($_POST['responsavel_tecnico_registro'] ?? '');
    $responsavel_tecnico_tipo_documento = trim($_POST['responsavel_tecnico_tipo_documento'] ?? '');
    $responsavel_tecnico_art = trim($_POST['responsavel_tecnico_art'] ?? $_POST['responsavel_tecnico_numero'] ?? '');
    $descricao_atividade = trim($_POST['especificacao'] ?? $_POST['descricao_atividade'] ?? '');
    
    $observacoes = '';

    $requerimento = [
        'protocolo' => $protocolo,
        'tipo_alvara' => $tipoAlvara,
        'requerente_id' => $requerente_id,
        'proprietario_id' => $proprietario_id,
        'endereco_objetivo' => $_POST['endereco_objetivo'] ?? '',
        'ctf_numero' => $ctf_numero ?: null,
        'licenca_anterior_numero' => $licenca_anterior_numero ?: null,
        'publicacao_diario_oficial' => $publicacao_diario_oficial ?: null,
        'comprovante_pagamento' => $comprovante_pagamento ?: null,
        'possui_estudo_ambiental' => $possui_estudo,
        'tipo_estudo_ambiental' => $tipo_estudo_ambiental ?: null,
        // Novos campos mapeados
        'area_construcao' => $area_construcao ?: null,
        'numero_pavimentos' => $numero_pavimentos ?: null,
        'area_construida' => $area_construida ?: null,
        'area_lote' => $area_lote ?: null,
        'responsavel_tecnico_nome' => $responsavel_tecnico_nome ?: null,
        'responsavel_tecnico_registro' => $responsavel_tecnico_registro ?: null,
        'responsavel_tecnico_tipo_documento' => $responsavel_tecnico_tipo_documento ?: null,
        'responsavel_tecnico_numero' => $responsavel_tecnico_art ?: null,
        'especificacao' => $descricao_atividade ?: null,
        'notificado_fiscal_obras' => isset($_POST['notificado_fiscal_obras']) ? (int)$_POST['notificado_fiscal_obras'] : null,
        'enquadramento_atividade' => $enquadramento_atividade ?: null,
        'localizacao_google_maps' => $localizacao_google_maps ?: null,
        'status' => 'Em análise'
    ];

    // Validação do endereço do objetivo
    if (empty($requerimento['endereco_objetivo'])) {
        $_SESSION['form_data'] = $_POST;
        setMensagem('erro', 'O endereço do objetivo é obrigatório.');
        redirect('index.php');
    }

    // Validações específicas para tipologias ambientais
    if ($isAmbiental) {
        if (empty($publicacao_diario_oficial)) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Informe os dados da publicação em Diário Oficial.');
            redirect('index.php');
        }

        if ($exigeCTF && empty($ctf_numero)) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Informe o número do Cadastro Técnico Federal (CTF).');
            redirect('index.php');
        }

        if ($exigeLicencaAnterior && empty($licenca_anterior_numero)) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Informe o número da licença anterior.');
            redirect('index.php');
        }

        if ($possui_estudo === null) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Informe se há estudo ambiental.');
            redirect('index.php');
        }

        if ($possui_estudo === 1 && empty($tipo_estudo_ambiental)) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Informe o tipo de estudo ambiental.');
            redirect('index.php');
        }

        if (!empty($data_certidao_municipal)) {
            $dataCertidao = strtotime($data_certidao_municipal);
            if ($dataCertidao === false) {
                $_SESSION['form_data'] = $_POST;
                setMensagem('erro', 'Data da certidão municipal inválida.');
                redirect('index.php');
            }
            // REMOVIDO: Validação de 2 anos - apenas registra a data
            $observacoes = "Certidão municipal emitida em: " . date('d/m/Y', $dataCertidao);
            $requerimento['observacoes'] = $observacoes;
        }
    }

    // Validação de documentos obrigatórios conforme checklist
    $documentosObrigatorios = $tipos_alvara[$tipoAlvara]['documentos'] ?? [];
    $errosDocumentos = [];

    if (!empty($documentosObrigatorios)) {
        foreach ($documentosObrigatorios as $index => $documento) {
            $campoDoc = "doc_{$tipoAlvara}_{$index}";
            $checkbox_nao_preciso = $campoDoc . '_nao_preciso';
            $naoPrecisa = isset($_POST[$checkbox_nao_preciso]) && $_POST[$checkbox_nao_preciso] === 'on';
            $arquivo = $_FILES[$campoDoc] ?? null;

            if ((!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) && !$naoPrecisa) {
                $errosDocumentos[] = $documento;
            }
        }
    }

    if (!empty($errosDocumentos)) {
        $_SESSION['form_data'] = $_POST;
        setMensagem('erro', 'Envie todos os documentos obrigatórios: ' . implode('; ', $errosDocumentos));
        redirect('index.php');
    }

    // Salvar requerimento
    $requerimento_id = $requerimentoModel->criar($requerimento);
    createAdminNotificationForRequerimento($pdo, (int) $requerimento_id, 'novo_protocolo');

    // Diretório para upload dos arquivos
    $diretorio_upload = UPLOAD_DIR . $protocolo;

    // Verificar tipos de arquivo antes de processar
    foreach ($_FILES as $campo => $arquivo) {
        if ($arquivo['error'] === UPLOAD_ERR_OK) {
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            if ($extensao !== 'pdf' || $arquivo['type'] !== 'application/pdf') {
                $_SESSION['form_data'] = $_POST;
                // Limpar requerimento já salvo
                $pdo->prepare("DELETE FROM requerimentos WHERE id = ?")->execute([$requerimento_id]);
                setMensagem('erro', 'Apenas arquivos PDF são permitidos. Por favor, converta seus documentos para PDF e tente novamente.');
                redirect('index.php');
            }
        }
    }

    // Processar os arquivos enviados e rastrear falhas físicas
    $arquivos_salvos    = []; // caminhos físicos gravados com sucesso
    $falhas_upload      = []; // nomes dos arquivos que falharam

    foreach ($_FILES as $campo => $arquivo) {
        $checkbox_nao_preciso = $campo . '_nao_preciso';
        $nao_precisa_enviar   = isset($_POST[$checkbox_nao_preciso]) && $_POST[$checkbox_nao_preciso] === 'on';

        if ($arquivo['error'] === UPLOAD_ERR_OK) {
            $arquivo_info = salvarArquivo($arquivo, $diretorio_upload, $campo);

            if ($arquivo_info && file_exists($arquivo_info['caminho_completo'])) {
                $documentoModel->criar([
                    'requerimento_id' => $requerimento_id,
                    'campo_formulario' => $campo,
                    'nome_original'    => $arquivo_info['nome_original'],
                    'nome_salvo'       => $arquivo_info['nome_salvo'],
                    'caminho'          => $arquivo_info['caminho'],
                    'tipo_arquivo'     => $arquivo_info['tipo'],
                    'tamanho'          => $arquivo_info['tamanho'],
                ]);
                $arquivos_salvos[] = $arquivo_info['caminho_completo'];
            } else {
                $falhas_upload[] = $arquivo['name'];
            }
        } elseif ($nao_precisa_enviar) {
            $documentoModel->criar([
                'requerimento_id' => $requerimento_id,
                'campo_formulario' => $campo,
                'nome_original'    => 'NÃO ENVIADO - Marcado como opcional',
                'nome_salvo'       => '',
                'caminho'          => '',
                'tipo_arquivo'     => 'opcional_nao_enviado',
                'tamanho'          => 0,
            ]);
        }
    }

    // Se qualquer arquivo obrigatório não foi gravado no disco, desfaz tudo e retorna erro
    if (!empty($falhas_upload)) {
        // Remover arquivos que chegaram a ser salvos
        foreach ($arquivos_salvos as $caminho) {
            if (file_exists($caminho)) unlink($caminho);
        }
        if (is_dir($diretorio_upload)) rmdir($diretorio_upload);

        // Limpar registros do banco
        $pdo->prepare("DELETE FROM documentos WHERE requerimento_id = ?")->execute([$requerimento_id]);
        $pdo->prepare("DELETE FROM requerimentos WHERE id = ?")->execute([$requerimento_id]);

        error_log("Upload falhou para protocolo $protocolo. Arquivos: " . implode(', ', $falhas_upload));
        setMensagem('erro', 'Erro ao salvar os documentos no servidor. Por favor, tente novamente. Se o problema persistir, entre em contato com a SEMA.');
        redirect('index.php');
    }

    // Todos os arquivos foram confirmados em disco — redirecionar para sucesso
    $_SESSION['protocolo']        = $protocolo;
    $_SESSION['proprietario_nome'] = $proprietario['nome'];

    // Enviar email de confirmação
    try {
        $emailService    = new EmailService();
        $tipo_alvara_nome = $tipos_alvara[$tipoAlvara]['nome'] ?? $tipoAlvara;

        $emailService->enviarEmailProtocolo(
            $requerente['email'],
            $requerente['nome'],
            $protocolo,
            $tipo_alvara_nome,
            ['id' => $requerimento_id, 'data_envio' => date('Y-m-d H:i:s'), 'endereco_objetivo' => $_POST['endereco_objetivo'] ?? '']
        );
    } catch (Exception $e) {
        error_log("Erro ao enviar email de confirmação: " . $e->getMessage());
    }

    setMensagem('sucesso', 'Requerimento enviado com sucesso!');
    redirect('sucesso.php');
} else {
    // Se não foi um POST, redirecionar para a página inicial
    redirect('index.php');
}
