<?php
// Incluir arquivo com os tipos de alvará
include_once 'tipos_alvara.php';

// Iniciar sessão para mensagens flash
session_start();

// Inclusão de arquivos necessários
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/models.php';

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se o tipo de alvará foi informado
    if (empty($_POST['tipo_alvara'])) {
        setMensagem('erro', 'É necessário selecionar um tipo de alvará.');
        redirect('index.php');
    }

    // Inicialização dos modelos
    $requerenteModel = new Requerente();
    $proprietarioModel = new Proprietario();
    $requerimentoModel = new Requerimento();
    $documentoModel = new Documento();

    // Dados do requerente
    $requerente = [
        'nome' => $_POST['requerente']['nome'] ?? '',
        'email' => $_POST['requerente']['email'] ?? '',
        'cpf_cnpj' => $_POST['requerente']['cpf_cnpj'] ?? '',
        'telefone' => $_POST['requerente']['telefone'] ?? ''
    ];

    // Validação dos dados do requerente
    if (empty($requerente['nome']) || empty($requerente['email']) || empty($requerente['cpf_cnpj']) || empty($requerente['telefone'])) {
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

        // Validação dos dados do proprietário
        if (empty($proprietario['nome']) || empty($proprietario['cpf_cnpj'])) {
            setMensagem('erro', 'Todos os campos do proprietário são obrigatórios.');
            redirect('index.php');
        }
    }

    // Salvar proprietário
    $proprietario_id = $proprietarioModel->criar($proprietario);

    // Dados do requerimento
    $protocolo = gerarProtocolo();
    $requerimento = [
        'protocolo' => $protocolo,
        'tipo_alvara' => $_POST['tipo_alvara'],
        'requerente_id' => $requerente_id,
        'proprietario_id' => $proprietario_id,
        'endereco_objetivo' => $_POST['endereco_objetivo'] ?? '',
        'status' => 'Em análise'
    ];

    // Validação do endereço do objetivo
    if (empty($requerimento['endereco_objetivo'])) {
        setMensagem('erro', 'O endereço do objetivo é obrigatório.');
        redirect('index.php');
    }

    // Salvar requerimento
    $requerimento_id = $requerimentoModel->criar($requerimento);

    // Diretório para upload dos arquivos
    $diretorio_upload = UPLOAD_DIR . $protocolo;

    // Processar os arquivos enviados
    foreach ($_FILES as $campo => $arquivo) {
        if ($arquivo['error'] === UPLOAD_ERR_OK) {
            // Salvar o arquivo
            $arquivo_info = salvarArquivo($arquivo, $diretorio_upload, $campo);

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
        }
    }

    // Redirecionar para a página de sucesso com o protocolo
    $_SESSION['protocolo'] = $protocolo;
    setMensagem('sucesso', 'Requerimento enviado com sucesso!');
    redirect('sucesso.php');
} else {
    // Se não foi um POST, redirecionar para a página inicial
    redirect('index.php');
}
