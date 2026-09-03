<?php
require_once '../conexao.php';
verificaLogin();
require_once __DIR__ . '/../../includes/fiscalizacao_obras_helpers.php';

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$isAdmin = in_array($nivelAtual, ['admin', 'admin_geral'], true);
if ($nivelAtual !== 'fiscal' && !$isAdmin) {
    http_response_code(403);
    header('Location: ../index.php?error=sem_permissao');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'cadastrar') {
    $tipoDocumento = $_POST['tipo_documento'] ?? '';
    $origem = in_array($_POST['origem'] ?? '', ['gerado_sistema', 'upload_pdf'], true) ? $_POST['origem'] : 'gerado_sistema';
    $notificadoNome = trim($_POST['notificado_nome'] ?? '');
    $descricaoFato = trim($_POST['descricao_fato'] ?? '');
    $dataEmissao = $_POST['data_emissao'] ?? date('Y-m-d');
    $prazoDias = trim($_POST['prazo_dias'] ?? '');
    $denunciaOrigemId = trim($_POST['denuncia_origem_id'] ?? '');

    if (!array_key_exists($tipoDocumento, FISCALIZACAO_OBRAS_TIPOS) || $notificadoNome === '' || $descricaoFato === '') {
        header('Location: nova.php?error=vazio');
        exit;
    }

    $pdfUploadPath = null;
    if ($origem === 'upload_pdf') {
        if (empty($_FILES['pdf_pronto']) || $_FILES['pdf_pronto']['error'] !== UPLOAD_ERR_OK) {
            header('Location: nova.php?error=pdf_obrigatorio');
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['pdf_pronto']['tmp_name']);
        finfo_close($finfo);
        $extOk = strtolower(pathinfo($_FILES['pdf_pronto']['name'], PATHINFO_EXTENSION)) === 'pdf';
        if ($mime !== 'application/pdf' || !$extOk) {
            header('Location: nova.php?error=pdf_invalido');
            exit;
        }

        $uploadDirRel = 'uploads/fiscalizacao_obras/' . date('Y/m/');
        $uploadDir = '../../' . $uploadDirRel;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $nomeSeguro = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($_FILES['pdf_pronto']['tmp_name'], $uploadDir . $nomeSeguro)) {
            header('Location: nova.php?error=upload_falhou');
            exit;
        }
        $pdfUploadPath = $uploadDirRel . $nomeSeguro;
    }

    try {
        $notificacaoId = inserirNotificacaoObras($pdo, [
            'tipo_documento' => $tipoDocumento,
            'origem' => $origem,
            'notificado_nome' => $notificadoNome,
            'notificado_cpf_cnpj' => trim($_POST['notificado_cpf_cnpj'] ?? '') ?: null,
            'proprietario_nome' => trim($_POST['proprietario_nome'] ?? '') ?: null,
            'endereco' => trim($_POST['endereco'] ?? '') ?: null,
            'bairro' => trim($_POST['bairro'] ?? '') ?: null,
            'numero_imovel' => trim($_POST['numero_imovel'] ?? '') ?: null,
            'descricao_fato' => $descricaoFato,
            'artigos_selecionados' => !empty($_POST['artigos']) ? json_encode($_POST['artigos'], JSON_UNESCAPED_UNICODE) : null,
            'prazo_dias' => $prazoDias !== '' ? (int) $prazoDias : null,
            'data_emissao' => $dataEmissao,
            'denuncia_origem_id' => $denunciaOrigemId !== '' ? (int) $denunciaOrigemId : null,
            'pdf_upload_path' => $pdfUploadPath,
            'fiscal_id' => $_SESSION['admin_id'],
        ]);

        header('Location: visualizar.php?id=' . $notificacaoId . '&success=cadastrada');
        exit;
    } catch (Throwable $e) {
        error_log('Erro ao cadastrar notificação de obras: ' . $e->getMessage());
        header('Location: nova.php?error=criacao');
        exit;
    }
}

header('Location: index.php');
exit;
