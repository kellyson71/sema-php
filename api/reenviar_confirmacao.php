<?php
header('Content-Type: application/json; charset=utf-8');

// Configuração de CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';
require_once '../includes/email_service.php';
require_once '../tipos_alvara.php';

function jsonResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido. Use POST.', [], 405);
}

// Ler input JSON ou POST normal
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$protocolo = $input['protocolo'] ?? null;
$requerimento_id = $input['requerimento_id'] ?? null;

if (empty($protocolo) && empty($requerimento_id)) {
    jsonResponse(false, 'Informe o número do protocolo ou o ID do requerimento.');
}

try {
    $requerimentoModel = new Requerimento();
    $requerenteModel = new Requerente();
    
    // Buscar requerimento
    $requerimento = null;
    if ($protocolo) {
        $requerimento = $requerimentoModel->buscarPorProtocolo($protocolo);
    } else {
        $requerimento = $requerimentoModel->buscarPorId($requerimento_id);
    }

    if (!$requerimento) {
        jsonResponse(false, 'Requerimento não encontrado.', [], 404);
    }

    // Buscar requerente associado
    $requerente = $requerenteModel->buscarPorId($requerimento['requerente_id']);
    if (!$requerente) {
        jsonResponse(false, 'Dados do requerente não encontrados.', [], 404);
    }

    // Preparar envio
    $emailService = new EmailService();
    global $tipos_alvara;
    
    $tipoAlvaraCodigo = $requerimento['tipo_alvara'];
    $tipoAlvaraNome = $tipos_alvara[$tipoAlvaraCodigo]['nome'] ?? $tipoAlvaraCodigo;
    
    $dados_req = [
        'id' => $requerimento['id'],
        'data_envio' => $requerimento['data_envio'], // Assumindo que o model retorna isso
        'endereco_objetivo' => $requerimento['endereco_objetivo']
    ];

    $enviado = $emailService->enviarEmailProtocolo(
        $requerente['email'],
        $requerente['nome'],
        $requerimento['protocolo'],
        $tipoAlvaraNome,
        $dados_req
    );

    if ($enviado) {
        jsonResponse(true, 'Email de confirmação enviado com sucesso.', [
            'email_destino' => $requerente['email'],
            'protocolo' => $requerimento['protocolo']
        ]);
    } else {
        jsonResponse(false, 'Falha ao enviar o email. Verifique os logs do servidor.', [], 500);
    }

} catch (Exception $e) {
    error_log("API Reenvio Email Error: " . $e->getMessage());
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), [], 500);
}
