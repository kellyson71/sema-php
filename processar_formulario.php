<?php
require_once 'includes/config.php';

// Incluir arquivo com os tipos de alvará
include_once 'tipos_alvara.php';

// Inclusão de arquivos necessários
require_once 'includes/functions.php';
require_once 'includes/models.php';
require_once 'includes/email_service.php';
require_once 'includes/admin_notifications.php';
require_once 'includes/public_form_components.php';

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $_SESSION['form_data'] = $_POST;
        setMensagem('erro', 'Sessão expirada. Recarregue a página e tente novamente.');
        redirect('index.php');
    }

    $formLoadedAt = (int) ($_POST['form_loaded_at'] ?? 0);
    if ($formLoadedAt > 0 && time() - $formLoadedAt < 3) {
        setMensagem('erro', 'Envio muito rápido. Revise o formulário e tente novamente.');
        redirect('index.php');
    }

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
    if (!$tipoInfo || !empty($tipoInfo['desabilitado'])) {
        setMensagem('erro', 'Tipo de alvará inválido ou não disponível.');
        redirect('index.php');
    }
    $isAmbiental = ($tipoInfo['categoria'] ?? '') === 'ambiental';
    $exigeCTF = $tipoInfo['exige_ctf'] ?? false;
    $exigeLicencaAnterior = $tipoInfo['exige_licenca_anterior'] ?? false;

    // Dados do requerente
    $requerente = [
        'nome' => trim($_POST['requerente']['nome'] ?? ''),
        'email' => trim($_POST['requerente']['email'] ?? ''),
        'cpf_cnpj' => trim($_POST['requerente']['cpf_cnpj'] ?? ''),
        'telefone' => trim($_POST['requerente']['telefone'] ?? '')
    ];
    $emailConfirmacao = trim($_POST['requerente']['email_confirmacao'] ?? '');

    // Validação dos dados do requerente
    if (empty($requerente['nome']) || empty($requerente['email']) || empty($requerente['cpf_cnpj']) || empty($requerente['telefone'])) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_step'] = 1;
        setMensagem('erro', 'Todos os campos do requerente são obrigatórios.');
        redirect('index.php');
    }
    if (!emailRequerenteValido($requerente['email'])) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_step'] = 1;
        setMensagem('erro', 'Informe um endereço de e-mail válido.');
        redirect('index.php');
    }
    if (!emailsRequerenteCoincidem($requerente['email'], $emailConfirmacao)) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_step'] = 1;
        setMensagem('erro', 'A confirmação do e-mail não coincide com o endereço informado.');
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
                $_SESSION['form_step'] = 1;
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
    if ($tipo_estudo_ambiental === '__outro__') {
        $tipo_estudo_ambiental = trim($_POST['tipo_estudo_ambiental_outro'] ?? '');
    }
    $data_certidao_municipal = $_POST['data_certidao_municipal'] ?? '';
    $enquadramento_atividade = trim($_POST['enquadramento_atividade'] ?? '');
    $localizacao_google_maps = trim($_POST['localizacao_google_maps'] ?? '');
    
    // Campos adicionais dos templates
    $area_construcao = trim($_POST['area_construcao'] ?? '');
    $numero_pavimentos = trim($_POST['numero_pavimentos'] ?? '');
    $area_construida = trim($_POST['area_construida'] ?? '');
    if ($area_construida === '' && $area_construcao !== '') {
        $area_construida = $area_construcao;
    } elseif ($area_construcao === '' && $area_construida !== '') {
        $area_construcao = $area_construida;
    }
    $area_lote = trim($_POST['area_lote'] ?? '');
    $responsavel_tecnico_nome = trim($_POST['responsavel_tecnico_nome'] ?? '');
    $responsavel_tecnico_registro = trim($_POST['responsavel_tecnico_registro'] ?? '');
    $responsavel_tecnico_tipo_documento = trim($_POST['responsavel_tecnico_tipo_documento'] ?? '');
    // O nome oficial persistido é responsavel_tecnico_numero; o alias antigo
    // continua aceito para preservar rascunhos e integrações anteriores.
    $responsavel_tecnico_art = trim($_POST['responsavel_tecnico_numero'] ?? $_POST['responsavel_tecnico_art'] ?? '');
    $responsavel_tecnico_email = trim($_POST['responsavel_tecnico_email'] ?? '');
    $responsavel_tecnico_telefone = trim($_POST['responsavel_tecnico_telefone'] ?? '');
    $descricao_atividade = trim($_POST['especificacao'] ?? $_POST['descricao_atividade'] ?? '');
    $tipo_edificacao = trim($_POST['tipo_edificacao'] ?? '');

    $obra_logradouro = trim($_POST['obra_logradouro'] ?? '');
    $obra_lote = trim($_POST['obra_lote'] ?? '');
    $obra_quadra = trim($_POST['obra_quadra'] ?? '');
    $obra_numero = trim($_POST['obra_numero'] ?? '');
    $obra_bairro = trim($_POST['obra_bairro'] ?? '');
    $obra_sem_numero = isset($_POST['obra_sem_numero']) ? 1 : 0;
    $obra_sem_lote_quadra = isset($_POST['obra_sem_lote_quadra']) ? 1 : 0;

    // A exigência acompanha a lista oficial de documentos do serviço.
    $documentosObrigatoriosTipo = array_values(array_filter((array) ($tipoInfo['documentos'] ?? []), 'is_string'));
    $exigeResponsavelTecnico = (bool) preg_match('/\bART(?:s)?\b|\bRRT(?:s)?\b|respons[aá]vel t[eé]cnico/i', implode(' ', $documentosObrigatoriosTipo));
    if ($exigeResponsavelTecnico && ($responsavel_tecnico_nome === '' || $responsavel_tecnico_tipo_documento === '' || $responsavel_tecnico_registro === '' || $responsavel_tecnico_art === '')) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_step'] = 1;
        setMensagem('erro', 'Informe todos os dados obrigatórios do responsável técnico, incluindo conselho, registro e ART/RRT.');
        redirect('index.php');
    }
    if ($responsavel_tecnico_tipo_documento !== '' && !in_array(strtoupper($responsavel_tecnico_tipo_documento), ['CREA', 'CAU', 'CTF'], true)) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_step'] = 1;
        setMensagem('erro', 'Selecione CREA, CAU ou CTF como conselho/documento do responsável técnico.');
        redirect('index.php');
    }

    // Campos adicionais dos modelos (construção / habite-se / desmembramento)
    $cadastro_imobiliario     = trim($_POST['cadastro_imobiliario'] ?? '');
    $matricula_imovel         = trim($_POST['matricula_imovel'] ?? '');
    $inicio_obra              = trim($_POST['inicio_obra'] ?? '');
    $termino_obra             = trim($_POST['termino_obra'] ?? '');
    $area_total_terreno       = trim($_POST['area_total_terreno'] ?? '');
    $area_remanescente        = trim($_POST['area_remanescente'] ?? '');
    $alvara_construcao_numero = trim($_POST['alvara_construcao_numero'] ?? '');
    $padrao_popular_post      = $_POST['padrao_popular'] ?? '';
    $padrao_popular           = in_array($padrao_popular_post, ['sim', 'nao'], true) ? $padrao_popular_post : null;
    $bombeiro_possui_post     = $_POST['bombeiro_possui'] ?? '';
    $bombeiro_possui          = $bombeiro_possui_post === '1' ? 1 : ($bombeiro_possui_post === '0' ? 0 : null);
    $bombeiro_numero          = $bombeiro_possui === 1 ? trim((string) ($_POST['bombeiro_numero'] ?? '')) : '';
    // O parecer técnico do Habite-se é parâmetro institucional, não campo do cidadão.
    $eng_fiscal_nome          = 'ISABELY KEYVA FERNANDES COSTA';
    $eng_fiscal_registro      = '2118668139';
    $habiteCampos = [];
    foreach (['uso', 'pavimento', 'tipo_construcao', 'padrao', 'estrutura', 'portas', 'janelas', 'piso', 'paredes', 'forro', 'cobertura'] as $campo) {
        $val = trim($_POST['habite_' . $campo] ?? '');
        if (strcasecmp($val, 'Outro') === 0 || $val === '__outro__') {
            $outro = trim($_POST['habite_' . $campo . '_outro'] ?? '');
            if ($outro !== '') {
                $val = $outro;
            }
        }
        $habiteCampos['habite_' . $campo] = $val;
    }
    $habiteAmbientes = json_decode((string) ($_POST['habite_ambientes_json'] ?? ''), true);
    $habiteAmbientes = is_array($habiteAmbientes) ? $habiteAmbientes : [];

    $quartos = max(0, (int) ($habiteAmbientes['quartos'] ?? $_POST['quartos'] ?? 0));
    $suites = max(0, (int) ($habiteAmbientes['suites'] ?? $_POST['suites'] ?? 0));
    $banheirosSociais = max(0, (int) ($habiteAmbientes['banheiros_sociais'] ?? $_POST['banheiros_sociais'] ?? $_POST['banheiros'] ?? 0));
    $salas = max(0, (int) ($habiteAmbientes['salas'] ?? $_POST['salas'] ?? 0));
    $cozinhas = max(0, (int) ($habiteAmbientes['cozinhas'] ?? $_POST['cozinhas'] ?? 0));

    $totalDormitorios = $quartos + $suites;
    $totalBanheiros = $banheirosSociais + $suites;

    $habiteAmbientes['quartos'] = $quartos;
    $habiteAmbientes['suites'] = $suites;
    $habiteAmbientes['banheiros_sociais'] = $banheirosSociais;
    $habiteAmbientes['banheiros'] = $banheirosSociais; // compatibilidade com código legado
    $habiteAmbientes['salas'] = $salas;
    $habiteAmbientes['cozinhas'] = $cozinhas;
    $habiteAmbientes['total_dormitorios'] = $totalDormitorios;
    $habiteAmbientes['total_banheiros'] = $totalBanheiros;

    $habiteAmbientesJson = json_encode($habiteAmbientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (in_array($tipoAlvara, ['construcao', 'construcao_obras_publicas'], true)
        && ($tipo_edificacao === '' || (int) $numero_pavimentos < 1 || $area_construcao === '' || $cadastro_imobiliario === '')) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_step'] = 2;
        setMensagem('erro', 'Informe o tipo da edificação, a quantidade de pavimentos, a área a construir e o cadastro imobiliário.');
        redirect('index.php');
    }
    if (in_array($tipoAlvara, ['habite_se', 'habite_se_simples', 'habite_se_obras_publicas'], true)) {
        $camposHabiteObrigatorios = array_merge(array_values($habiteCampos), [
            $area_construida, $cadastro_imobiliario, $alvara_construcao_numero, $inicio_obra, $termino_obra,
        ]);
        if (in_array('', $camposHabiteObrigatorios, true)) {
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_step'] = 2;
            setMensagem('erro', 'Preencha todos os dados e características obrigatórias da Carta de Habite-se.');
            redirect('index.php');
        }
    }

    $observacoes = '';
    $desmembramentoLotesJson = null;

    if ($tipoAlvara === 'desmembramento') {
        $toArea = static function ($value): float {
            $value = trim((string) $value);
            if ($value === '') return 0.0;
            // Aceita somente número positivo em formato simples (12.50) ou
            // brasileiro (1.250,50). Letras e unidades como "500m" são
            // rejeitadas; a unidade é responsabilidade da apresentação.
            $formatoSimples = preg_match('/^\d+(?:\.\d+)?$/', $value);
            $formatoBrasileiro = preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d+)?$/', $value);
            if (!$formatoSimples && !$formatoBrasileiro) return 0.0;
            if (strpos($value, ',') !== false) {
                $value = str_replace(',', '.', str_replace('.', '', $value));
            }
            return (float) $value;
        };
        $confrontacoes = static function (string $prefix = '') use ($toArea): array {
            $lados = [];
            foreach (['norte', 'oeste', 'leste', 'sul'] as $rumo) {
                $metragem = $_POST[$prefix . 'confrontacao_' . $rumo . '_metragem'] ?? '';
                $descricao = trim((string) ($_POST[$prefix . 'confrontacao_' . $rumo . '_descricao'] ?? ''));
                $lados[$rumo] = [
                    'metragem' => $toArea($metragem),
                    'descricao' => $descricao,
                ];
            }
            return $lados;
        };

        $lotes = [];
        $areaPrimeiroLote = $toArea($_POST['area_lote'] ?? '');
        $geometriaPrimeiroLote = ($_POST['geometria'] ?? 'regular') === 'irregular' ? 'irregular' : 'regular';
        $lotes[] = [
            'ordem' => 1,
            'area' => $areaPrimeiroLote,
            'cadastro_imobiliario' => trim((string) ($_POST['cadastro_imobiliario'] ?? '')),
            'geometria' => $geometriaPrimeiroLote,
            'descricao_irregular' => $geometriaPrimeiroLote === 'irregular' ? trim((string) ($_POST['descricao_irregular'] ?? '')) : '',
            'confrontacoes' => $geometriaPrimeiroLote === 'irregular' ? [] : $confrontacoes(),
        ];
        foreach ((array) ($_POST['lotes'] ?? []) as $index => $lotePost) {
            if (!is_array($lotePost)) continue;
            $geometriaLote = ($lotePost['geometria'] ?? 'regular') === 'irregular' ? 'irregular' : 'regular';
            $lados = [];
            if ($geometriaLote !== 'irregular') {
                foreach (['norte', 'oeste', 'leste', 'sul'] as $rumo) {
                    $ladoPost = (array) ($lotePost['confrontacoes'][$rumo] ?? []);
                    $lados[$rumo] = [
                        'metragem' => $toArea($ladoPost['metragem'] ?? ''),
                        'descricao' => trim((string) ($ladoPost['descricao'] ?? '')),
                    ];
                }
            }
            $lotes[] = [
                'ordem' => count($lotes) + 1,
                'area' => $toArea($lotePost['area'] ?? ''),
                'cadastro_imobiliario' => trim((string) ($lotePost['cadastro_imobiliario'] ?? '')),
                'geometria' => $geometriaLote,
                'descricao_irregular' => $geometriaLote === 'irregular' ? trim((string) ($lotePost['descricao_irregular'] ?? '')) : '',
                'confrontacoes' => $lados,
            ];
        }

        $totalTerreno = $toArea($_POST['area_total_terreno'] ?? '');
        $areaTotalDesconhecida = !empty($_POST['area_total_desconhecida']);
        $areaTotalDesconhecidaMotivo = $areaTotalDesconhecida ? trim((string) ($_POST['area_total_desconhecida_motivo'] ?? '')) : '';
        if ($areaTotalDesconhecida && $areaTotalDesconhecidaMotivo === '') {
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_step'] = 2;
            setMensagem('erro', 'Explique por que não sabe a área total do terreno.');
            redirect('index.php');
        }
        $somaLotes = array_sum(array_column($lotes, 'area'));
        $inconsistencia = $totalTerreno > 0 && $somaLotes > $totalTerreno;
        foreach ($lotes as $lote) {
            if ($lote['area'] <= 0) {
                $_SESSION['form_data'] = $_POST;
                $_SESSION['form_step'] = 2;
                setMensagem('erro', 'Informe uma área válida para cada lote do desmembramento.');
                redirect('index.php');
            }
            if ($lote['geometria'] === 'irregular') {
                if ($lote['descricao_irregular'] === '') {
                    $_SESSION['form_data'] = $_POST;
                    $_SESSION['form_step'] = 2;
                    setMensagem('erro', 'Descreva o formato de cada lote marcado como irregular.');
                    redirect('index.php');
                }
                continue;
            }
            foreach ($lote['confrontacoes'] as $lado) {
                if ($lado['metragem'] <= 0 || $lado['descricao'] === '') {
                    $_SESSION['form_data'] = $_POST;
                    $_SESSION['form_step'] = 2;
                    setMensagem('erro', 'Informe a metragem e o confrontante de cada lado de todos os lotes.');
                    redirect('index.php');
                }
            }
        }
        if ($totalTerreno <= 0 && !$areaTotalDesconhecida) {
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_step'] = 2;
            setMensagem('erro', 'Informe uma área válida para a porção maior do terreno.');
            redirect('index.php');
        }
        if ($inconsistencia) {
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_step'] = 2;
            setMensagem('erro', 'A soma das áreas dos lotes não pode ser maior que a área da porção maior.');
            redirect('index.php');
        }
        $area_remanescente = $areaTotalDesconhecida ? '' : number_format(max(0, $totalTerreno - $somaLotes), 2, ',', '.');
        $desmembramentoLotesJson = json_encode([
            'area_total_terreno' => $areaTotalDesconhecida ? null : $totalTerreno,
            'area_total_desconhecida' => $areaTotalDesconhecida,
            'area_total_desconhecida_motivo' => $areaTotalDesconhecidaMotivo ?: null,
            'soma_lotes' => $somaLotes,
            'area_remanescente' => $areaTotalDesconhecida ? null : max(0, $totalTerreno - $somaLotes),
            'inconsistencia' => $inconsistencia,
            'lotes' => $lotes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $requerimento = [
        'protocolo' => $protocolo,
        'tipo_alvara' => $tipoAlvara,
        'requerente_id' => $requerente_id,
        'proprietario_id' => $proprietario_id,
        'endereco_objetivo' => $_POST['endereco_objetivo'] ?? '',
        'obra_logradouro' => $obra_logradouro ?: null,
        'obra_lote' => (!$obra_sem_lote_quadra && $obra_lote !== '') ? $obra_lote : null,
        'obra_quadra' => (!$obra_sem_lote_quadra && $obra_lote !== '') ? ($obra_quadra ?: null) : null,
        'obra_numero' => $obra_sem_numero ? null : ($obra_numero ?: null),
        'obra_sem_numero' => $obra_sem_numero,
        'obra_sem_lote_quadra' => $obra_sem_lote_quadra,
        'obra_bairro' => $obra_bairro ?: null,
        'ctf_numero' => $ctf_numero ?: null,
        'licenca_anterior_numero' => $licenca_anterior_numero ?: null,
        'publicacao_diario_oficial' => $publicacao_diario_oficial ?: null,
        'comprovante_pagamento' => $comprovante_pagamento ?: null,
        'possui_estudo_ambiental' => $possui_estudo,
        'tipo_estudo_ambiental' => $tipo_estudo_ambiental ?: null,
        // Novos campos mapeados
        'area_construcao' => $area_construcao ?: null,
        'numero_pavimentos' => $numero_pavimentos ?: null,
        'tipo_edificacao' => $tipo_edificacao ?: null,
        'area_construida' => $area_construida ?: null,
        'area_lote' => $area_lote ?: null,
        'responsavel_tecnico_nome' => $responsavel_tecnico_nome ?: null,
        'responsavel_tecnico_registro' => $responsavel_tecnico_registro ?: null,
        'responsavel_tecnico_tipo_documento' => $responsavel_tecnico_tipo_documento ?: null,
        'responsavel_tecnico_numero' => $responsavel_tecnico_art ?: null,
        'responsavel_tecnico_email' => $responsavel_tecnico_email ?: null,
        'responsavel_tecnico_telefone' => $responsavel_tecnico_telefone ?: null,
        'especificacao' => $descricao_atividade ?: null,
        'cadastro_imobiliario' => $cadastro_imobiliario ?: null,
        'matricula_imovel' => $matricula_imovel ?: null,
        'inicio_obra' => $inicio_obra ?: null,
        'termino_obra' => $termino_obra ?: null,
        'area_total_terreno' => $area_total_terreno ?: null,
        'area_remanescente' => $area_remanescente ?: null,
        'desmembramento_lotes_json' => $desmembramentoLotesJson,
        'alvara_construcao_numero' => $alvara_construcao_numero ?: null,
        'eng_fiscal_nome' => $eng_fiscal_nome ?: null,
        'eng_fiscal_registro' => $eng_fiscal_registro ?: null,
        'habite_uso' => $habiteCampos['habite_uso'] ?: null,
        'habite_pavimento' => $habiteCampos['habite_pavimento'] ?: null,
        'habite_tipo_construcao' => $habiteCampos['habite_tipo_construcao'] ?: null,
        'habite_padrao' => $habiteCampos['habite_padrao'] ?: null,
        'habite_estrutura' => $habiteCampos['habite_estrutura'] ?: null,
        'habite_portas' => $habiteCampos['habite_portas'] ?: null,
        'habite_janelas' => $habiteCampos['habite_janelas'] ?: null,
        'habite_piso' => $habiteCampos['habite_piso'] ?: null,
        'habite_paredes' => $habiteCampos['habite_paredes'] ?: null,
        'habite_forro' => $habiteCampos['habite_forro'] ?: null,
        'habite_cobertura' => $habiteCampos['habite_cobertura'] ?: null,
        'habite_ambientes_json' => $habiteAmbientesJson,
        'padrao_popular' => $padrao_popular,
        'bombeiro_possui' => $bombeiro_possui,
        'bombeiro_numero' => $bombeiro_numero ?: null,
        'notificado_fiscal_obras' => isset($_POST['notificado_fiscal_obras']) ? (int)$_POST['notificado_fiscal_obras'] : null,
        'enquadramento_atividade' => $enquadramento_atividade ?: null,
        'localizacao_google_maps' => $localizacao_google_maps ?: null,
        'status' => 'Em análise'
    ];

    // Validação do endereço do objetivo
    if (!enderecoPauDosFerrosValido((string) $requerimento['endereco_objetivo'])) {
        $_SESSION['form_data'] = $_POST;
        setMensagem('erro', 'Informe a localização no padrão mínimo: rua, número ou SN, bairro e Pau dos Ferros/RN.');
        redirect('index.php');
    }

    foreach (['localizacao_area' => 'localização da área', 'endereco_imovel' => 'endereço do imóvel'] as $campoEndereco => $rotuloEndereco) {
        if (isset($_POST[$campoEndereco]) && trim((string) $_POST[$campoEndereco]) !== '' && !enderecoPauDosFerrosValido((string) $_POST[$campoEndereco])) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Revise a ' . $rotuloEndereco . ': informe rua, número ou SN, bairro e Pau dos Ferros/RN.');
            redirect('index.php');
        }
    }

    // Validações específicas para tipologias ambientais
    $exigeDiarioOficial = $isAmbiental && ($tipoInfo['exige_diario_oficial'] ?? true);
    if ($isAmbiental) {
        if ($descricao_atividade === '') {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Informe a atividade ou finalidade do empreendimento.');
            redirect('index.php');
        }

        if ($enquadramento_atividade === '') {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Selecione o enquadramento ambiental da atividade.');
            redirect('index.php');
        }

        if ($exigeDiarioOficial && empty($publicacao_diario_oficial)) {
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

    $limiteArquivo = $isAmbiental ? MAX_FILE_SIZE_AMBIENTAL : MAX_FILE_SIZE;
    foreach ($_FILES as $campo => $arquivo) {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Não foi possível receber um dos arquivos. Verifique o tamanho e tente novamente.');
            redirect('index.php');
        }

        if (($arquivo['size'] ?? 0) > $limiteArquivo) {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Um dos arquivos ultrapassa o limite permitido para este tipo de solicitação.');
            redirect('index.php');
        }

        $extensao = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, $arquivo['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);

        if ($extensao !== 'pdf' || $mimeReal !== 'application/pdf') {
            $_SESSION['form_data'] = $_POST;
            setMensagem('erro', 'Apenas arquivos PDF válidos são permitidos.');
            redirect('index.php');
        }
    }

    // Salvar requerimento
    $requerimento_id = $requerimentoModel->criar($requerimento);
    if ($responsavel_tecnico_registro !== '' && $responsavel_tecnico_nome !== '') {
        $conselho = strtoupper($responsavel_tecnico_tipo_documento);
        $conselho = in_array($conselho, ['CAU', 'RRT'], true) ? 'CAU' : 'CREA';
        $pdo->prepare("INSERT INTO responsaveis_tecnicos (conselho, registro, nome, email, telefone)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE conselho = VALUES(conselho), nome = VALUES(nome),
                email = VALUES(email), telefone = VALUES(telefone)")
            ->execute([$conselho, $responsavel_tecnico_registro, $responsavel_tecnico_nome,
                $responsavel_tecnico_email ?: null, $responsavel_tecnico_telefone ?: null]);
        $responsavelId = (int) $pdo->lastInsertId();
        if ($responsavelId === 0) {
            $stmtResponsavel = $pdo->prepare('SELECT id FROM responsaveis_tecnicos WHERE registro = ?');
            $stmtResponsavel->execute([$responsavel_tecnico_registro]);
            $responsavelId = (int) $stmtResponsavel->fetchColumn();
        }
        if ($responsavelId > 0) {
            $pdo->prepare('INSERT IGNORE INTO responsavel_tecnico_obras (responsavel_tecnico_id, requerimento_id) VALUES (?, ?)')
                ->execute([$responsavelId, $requerimento_id]);
        }
    }
    createAdminNotificationForRequerimento($pdo, (int) $requerimento_id, 'novo_protocolo');

    // Diretório para upload dos arquivos
    $diretorio_upload = UPLOAD_DIR . $protocolo;

    // Processar os arquivos enviados
    foreach ($_FILES as $campo => $arquivo) {
        // Verificar se é um documento opcional que foi marcado como "não preciso enviar"
        $checkbox_nao_preciso = $campo . '_nao_preciso';
        $nao_precisa_enviar = isset($_POST[$checkbox_nao_preciso]) && $_POST[$checkbox_nao_preciso] === 'on';

        if ($arquivo['error'] === UPLOAD_ERR_OK) {
            $limiteArquivo = $isAmbiental ? MAX_FILE_SIZE_AMBIENTAL : MAX_FILE_SIZE;
            $arquivo_info = salvarArquivo($arquivo, $diretorio_upload, $campo, $limiteArquivo);

            if ($arquivo_info) {
                // Registrar o documento no banco de dados
                $documento = [
                    'requerimento_id' => $requerimento_id,
                    'campo_formulario' => $campo,
                    'nome_original' => $arquivo_info['nome_original'],
                    'nome_salvo' => $arquivo_info['nome_salvo'],
                    'caminho' => $arquivo_info['caminho'],
                    'tipo_arquivo' => $arquivo_info['tipo'],
                    'tamanho' => $arquivo_info['tamanho']
                ];

                $documentoModel->criar($documento);
            }
        } elseif ($nao_precisa_enviar) {
            // Registrar que o documento foi marcado como "não preciso enviar"
            $documento = [
                'requerimento_id' => $requerimento_id,
                'campo_formulario' => $campo,
                'nome_original' => 'NÃO ENVIADO - Marcado como opcional',
                'nome_salvo' => '',
                'caminho' => '',
                'tipo_arquivo' => 'opcional_nao_enviado',
                'tamanho' => 0
            ];

            $documentoModel->criar($documento);
        }
    } // Redirecionar para a página de sucesso com o protocolo
    $_SESSION['protocolo'] = $protocolo;
    $_SESSION['proprietario_nome'] = $proprietario['nome'];
    $_SESSION['email_confirmacao_destino'] = $requerente['email'];

    // Enviar email de confirmação
    try {
        $emailService = new EmailService();
        $tipo_alvara_nome = $tipos_alvara[$tipoAlvara]['nome'] ?? $tipoAlvara;
        $dados_requerimento = [
            'id' => $requerimento_id,
            'data_envio' => date('Y-m-d H:i:s'),
            'endereco_objetivo' => $_POST['endereco_objetivo'] ?? ''
        ];

        $email_enviado = $emailService->enviarEmailProtocolo(
            $requerente['email'],
            $requerente['nome'],
            $protocolo,
            $tipo_alvara_nome,
            $dados_requerimento
        );

        if ($email_enviado) {
            error_log("Email de confirmação enviado com sucesso para: " . $requerente['email']);
        } else {
            error_log("Falha ao enviar email de confirmação para: " . $requerente['email']);
            $_SESSION['email_confirmacao_falhou'] = true;
        }
    } catch (Throwable $e) {
        error_log("Erro ao enviar email de confirmação: " . $e->getMessage());
        $_SESSION['email_confirmacao_falhou'] = true;
    }

    setMensagem('sucesso', 'Requerimento enviado com sucesso!');
    redirect('sucesso.php');
} else {
    // Se não foi um POST, redirecionar para a página inicial
    redirect('index.php');
}
