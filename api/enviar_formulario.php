<?php
header('Content-Type: application/json; charset=utf-8');

// Configuração de CORS (ajuste conforme necessário para produção)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 1. Inclusões necessárias
require_once '../includes/config.php';
require_once '../includes/database.php'; // Adicionado explícito para garantir conexão
require_once '../includes/functions.php';
require_once '../includes/models.php';
require_once '../includes/email_service.php';
require_once '../tipos_alvara.php';

// Função auxiliar para resposta JSON
function jsonResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    // 2. Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método não permitido. Use POST.', [], 405);
    }

    // 3. Verificar tipo de alvará
    if (empty($_POST['tipo_alvara'])) {
        jsonResponse(false, 'É necessário selecionar um tipo de alvará.');
    }

    $tipoAlvara = $_POST['tipo_alvara'];

    // Validar se o tipo de alvará existe
    global $tipos_alvara;
    if (!isset($tipos_alvara[$tipoAlvara])) {
        jsonResponse(false, 'Tipo de alvará inválido.');
    }

    // 4. Inicialização dos modelos
    $requerenteModel = new Requerente();
    $proprietarioModel = new Proprietario();
    $requerimentoModel = new Requerimento();
    $documentoModel = new Documento();

    // 5. Setup de regras (replicado de processar_formulario.php)
    $tiposAmbientais = [
        'licenca_previa_ambiental', 'licenca_previa_instalacao', 'licenca_instalacao_operacao',
        'licenca_operacao', 'licenca_ambiental_unica', 'licenca_ampliacao',
        'licenca_operacional_corretiva', 'autorizacao_supressao'
    ];
    $tiposExigemCTF = [
        'licenca_operacao', 'licenca_instalacao_operacao', 'licenca_ambiental_unica',
        'licenca_ampliacao', 'licenca_operacional_corretiva'
    ];
    $tiposExigemLicencaAnterior = ['licenca_operacao', 'licenca_instalacao_operacao'];

    // 6. Processar Requerente
    $requerente_dados = $_POST['requerente'] ?? [];
    if (empty($requerente_dados['nome']) || empty($requerente_dados['email']) || 
        empty($requerente_dados['cpf_cnpj']) || empty($requerente_dados['telefone'])) {
        jsonResponse(false, 'Todos os campos do requerente (nome, email, cpf_cnpj, telefone) são obrigatórios.');
    }

    // Salvar ou buscar requerente (a lógica do model geralmente trata duplicação ou atualização se implementada assim, 
    // mas aqui estamos apenas "criando" conforme o código original)
    $requerente_id = $requerenteModel->criar([
        'nome' => $requerente_dados['nome'],
        'email' => $requerente_dados['email'],
        'cpf_cnpj' => $requerente_dados['cpf_cnpj'],
        'telefone' => $requerente_dados['telefone']
    ]);

    // 7. Processar Proprietário
    $mesmo_requerente = isset($_POST['mesmo_requerente']) && ($_POST['mesmo_requerente'] === 'true' || $_POST['mesmo_requerente'] === '1');
    
    if ($mesmo_requerente) {
        $proprietario = [
            'nome' => $requerente_dados['nome'],
            'cpf_cnpj' => $requerente_dados['cpf_cnpj'],
            'mesmo_requerente' => 1,
            'requerente_id' => $requerente_id
        ];
    } else {
        $prop_dados = $_POST['proprietario'] ?? [];
        if (empty($prop_dados['nome']) || empty($prop_dados['cpf_cnpj'])) {
            // Se indicou que NÃO é o mesmo, mas não mandou dados, verifica comportamento padrão
            // O código original redireciona com erro se campos estiverem vazios E tiver tentado preencher, 
            // ou usa dados do requerente se totalmente vazio? 
            // Original: "Se não informou nada, usa os dados do requerente".
            // Vamos assumir que se o usuário marcou "não é mesmo requerente", ele DEVE mandar os dados na API.
            // Mas para robustez, vamos validar:
            jsonResponse(false, 'Dados do proprietário (nome, cpf_cnpj) são obrigatórios quando não é o mesmo requerente.');
        }
        $proprietario = [
            'nome' => $prop_dados['nome'],
            'cpf_cnpj' => $prop_dados['cpf_cnpj'],
            'mesmo_requerente' => 0,
            'requerente_id' => $requerente_id
        ];
    }
    $proprietario_id = $proprietarioModel->criar($proprietario);

    // 8. Validar Endereço do Objetivo
    if (empty($_POST['endereco_objetivo'])) {
        jsonResponse(false, 'O endereço do objetivo é obrigatório.');
    }

    // 9. Validações Específicas (Ambientais)
    $ctf_numero = trim($_POST['ctf_numero'] ?? '');
    $licenca_anterior_numero = trim($_POST['licenca_anterior_numero'] ?? '');
    $publicacao_diario_oficial = trim($_POST['publicacao_diario_oficial'] ?? '');
    $comprovante_pagamento = trim($_POST['comprovante_pagamento'] ?? '');
    $possui_estudo = isset($_POST['possui_estudo_ambiental']) ? (int) $_POST['possui_estudo_ambiental'] : null;
    $tipo_estudo_ambiental = trim($_POST['tipo_estudo_ambiental'] ?? '');
    $data_certidao_municipal = $_POST['data_certidao_municipal'] ?? '';
    $observacoes = '';

    if (in_array($tipoAlvara, $tiposAmbientais)) {
        $erros_ambientais = [];
        if (empty($publicacao_diario_oficial)) $erros_ambientais[] = 'Dados da publicação em Diário Oficial';
        if (empty($comprovante_pagamento)) $erros_ambientais[] = 'Comprovante de pagamento';
        
        if (in_array($tipoAlvara, $tiposExigemCTF) && empty($ctf_numero)) 
            $erros_ambientais[] = 'Número do CTF';
            
        if (in_array($tipoAlvara, $tiposExigemLicencaAnterior) && empty($licenca_anterior_numero)) 
            $erros_ambientais[] = 'Número da licença anterior';
            
        if ($possui_estudo === null) $erros_ambientais[] = 'Informação sobre estudo ambiental';
        if ($possui_estudo === 1 && empty($tipo_estudo_ambiental)) $erros_ambientais[] = 'Tipo de estudo ambiental';
        
        if (!empty($data_certidao_municipal)) {
            $dataCertidao = strtotime($data_certidao_municipal);
            if ($dataCertidao === false) {
                jsonResponse(false, 'Data da certidão municipal inválida.');
            }
            $observacoes = "Certidão municipal emitida em: " . date('d/m/Y', $dataCertidao);
        }

        if (!empty($erros_ambientais)) {
            jsonResponse(false, 'Faltam informações ambientais obrigatórias: ' . implode(', ', $erros_ambientais));
        }
    }

    // 10. Validação de Arquivos Obrigatórios
    $documentosObrigatorios = $tipos_alvara[$tipoAlvara]['documentos'] ?? [];
    $errosDocumentos = [];

    // Nota: O código original verifica documentos por índice, ex: doc_{tipo}_{index}
    // A API espera o mesmo formato de chaves no $_FILES.
    if (!empty($documentosObrigatorios)) {
        foreach ($documentosObrigatorios as $index => $documento_label) {
            $campoDoc = "doc_{$tipoAlvara}_{$index}";
            $checkbox_nao_preciso = $campoDoc . '_nao_preciso';
            // Aceita string 'on', 'true' ou '1' para o checkbox via API
            $naoPrecisa = isset($_POST[$checkbox_nao_preciso]) && 
                          ($_POST[$checkbox_nao_preciso] === 'on' || $_POST[$checkbox_nao_preciso] === 'true' || $_POST[$checkbox_nao_preciso] === '1');
            
            $arquivo = $_FILES[$campoDoc] ?? null;

            if ((!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) && !$naoPrecisa) {
                $errosDocumentos[] = "Documento obrigatório ausente: $documento_label (campo: $campoDoc)";
            }
        }
    }

    if (!empty($errosDocumentos)) {
        jsonResponse(false, implode('; ', $errosDocumentos));
    }

    // 11. Validação de Formato (PDF Only)
    foreach ($_FILES as $campo => $arquivo) {
        if ($arquivo['error'] === UPLOAD_ERR_OK) {
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $type = $arquivo['type'];
            if ($extensao !== 'pdf' || $type !== 'application/pdf') {
                jsonResponse(false, "O arquivo no campo '$campo' não é um PDF válido.");
            }
        }
    }

    // 12. Gerar Protocolo e Salvar Requerimento
    $protocolo = gerarProtocolo();
    
    $requerimento = [
        'protocolo' => $protocolo,
        'tipo_alvara' => $tipoAlvara,
        'requerente_id' => $requerente_id,
        'proprietario_id' => $proprietario_id,
        'endereco_objetivo' => $_POST['endereco_objetivo'],
        'ctf_numero' => $ctf_numero ?: null,
        'licenca_anterior_numero' => $licenca_anterior_numero ?: null,
        'publicacao_diario_oficial' => $publicacao_diario_oficial ?: null,
        'comprovante_pagamento' => $comprovante_pagamento ?: null,
        'possui_estudo_ambiental' => $possui_estudo,
        'tipo_estudo_ambiental' => $tipo_estudo_ambiental ?: null,
        'status' => 'Em análise',
        'observacoes' => isset($observacoes) && !empty($observacoes) ? $observacoes : null
    ];

    $requerimento_id = $requerimentoModel->criar($requerimento);
    if (!$requerimento_id) {
        jsonResponse(false, 'Erro interno ao criar requerimento no banco de dados.');
    }

    // 13. Processar Uploads
    $diretorio_upload = UPLOAD_DIR . $protocolo; // Definido em config.php/functions.php
    $arquivos_salvos = [];

    foreach ($_FILES as $campo => $arquivo) {
        $checkbox_nao_preciso = $campo . '_nao_preciso';
        $nao_precisa_enviar = isset($_POST[$checkbox_nao_preciso]) && 
                              ($_POST[$checkbox_nao_preciso] === 'on' || $_POST[$checkbox_nao_preciso] === 'true' || $_POST[$checkbox_nao_preciso] === '1');

        if ($arquivo['error'] === UPLOAD_ERR_OK) {
            $arquivo_info = salvarArquivo($arquivo, $diretorio_upload, $campo);
            if ($arquivo_info) {
                $documentoData = [
                    'requerimento_id' => $requerimento_id,
                    'campo_formulario' => $campo,
                    'nome_original' => $arquivo_info['nome_original'],
                    'nome_salvo' => $arquivo_info['nome_salvo'],
                    'caminho' => $arquivo_info['caminho'],
                    'tipo_arquivo' => $arquivo_info['tipo'],
                    'tamanho' => $arquivo_info['tamanho']
                ];
                $documentoModel->criar($documentoData);
                $arquivos_salvos[] = $arquivo_info['nome_original'];
            }
        } elseif ($nao_precisa_enviar) {
             $documentoData = [
                'requerimento_id' => $requerimento_id,
                'campo_formulario' => $campo,
                'nome_original' => 'NÃO ENVIADO - Marcado como opcional',
                'nome_salvo' => '',
                'caminho' => '',
                'tipo_arquivo' => 'opcional_nao_enviado',
                'tamanho' => 0
            ];
            $documentoModel->criar($documentoData);
        }
    }

    // 14. Envio de Email
    $email_enviado = false;
    try {
        $emailService = new EmailService();
        $tipo_alvara_nome = $tipos_alvara[$tipoAlvara]['nome'] ?? $tipoAlvara;
        $dados_req_email = [
            'id' => $requerimento_id,
            'data_envio' => date('Y-m-d H:i:s'),
            'endereco_objetivo' => $_POST['endereco_objetivo']
        ];

        $email_enviado = $emailService->enviarEmailProtocolo(
            $requerente_dados['email'],
            $requerente_dados['nome'],
            $protocolo,
            $tipo_alvara_nome,
            $dados_req_email
        );
    } catch (Exception $e) {
        error_log("API: Erro ao enviar email: " . $e->getMessage());
    }

    // 15. Retorno Final
    jsonResponse(true, 'Requerimento enviado com sucesso.', [
        'protocolo' => $protocolo,
        'requerimento_id' => $requerimento_id,
        'email_enviado' => $email_enviado,
        'arquivos_processados' => count($arquivos_salvos)
    ], 201);

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    jsonResponse(false, 'Erro interno do servidor: ' . $e->getMessage(), [], 500);
}
