<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Redireciona se acesso direto fora do ambiente correto
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: http://sema.paudosferros.rn.gov.br' . $requestUri);
    exit;
}

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Coletar e validar dados ────────────────────────────────────────────────

$anonimo           = isset($_POST['anonimo']) && $_POST['anonimo'] === '1';
$denuncianteNome   = trim($_POST['denunciante_nome'] ?? '');
$denuncianteEnd    = trim($_POST['denunciante_endereco'] ?? '');
$propNome          = trim($_POST['proprietario_nome'] ?? '');
$propEndereco      = trim($_POST['proprietario_endereco'] ?? '');
$propContato       = trim($_POST['proprietario_contato'] ?? '');
$observacoes       = trim($_POST['observacoes'] ?? '');
$tiposSelecionados = $_POST['tipos_denuncia'] ?? [];
$outrosDescricao   = trim($_POST['outros_descricao'] ?? '');

$erros = [];

if (!$anonimo && empty($denuncianteNome)) {
    $erros[] = 'Informe seu nome ou marque a opção de denúncia anônima.';
}
if (empty($tiposSelecionados)) {
    $erros[] = 'Selecione pelo menos um tipo de ocorrência.';
}
if (in_array('outros', (array) $tiposSelecionados, true) && empty($outrosDescricao)) {
    $erros[] = 'Descreva a ocorrência no campo "Outros".';
}

if (!empty($erros)) {
    $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => implode(' ', $erros)];
    header('Location: index.php');
    exit;
}

// ── Gerar protocolo único ──────────────────────────────────────────────────

$protocolo = 'DEN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

// ── Filtrar e montar JSON dos tipos ───────────────────────────────────────

$tiposValidos = [
    'obstrucao_via', 'terreno_sujo', 'terreno_baldio',
    'esgoto_via', 'construcao_irregular',
    'entulho_construcao', 'entulho_via', 'outros',
];
$tiposFiltrados = array_values(array_intersect((array) $tiposSelecionados, $tiposValidos));

if (empty($tiposFiltrados)) {
    $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Tipo de ocorrência inválido.'];
    header('Location: index.php');
    exit;
}

// Incorporar descrição de "Outros" às observações
if (in_array('outros', $tiposFiltrados, true) && !empty($outrosDescricao)) {
    $observacoes = ($observacoes ? $observacoes . "\n\n" : '') . 'Outros: ' . $outrosDescricao;
}

$tipoDenunciaJson = json_encode($tiposFiltrados, JSON_UNESCAPED_UNICODE);

// ── Conexão e inserção ─────────────────────────────────────────────────────

$database = new Database();
$pdo      = $database->getConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO denuncias
            (infrator_nome, infrator_endereco, observacoes,
             admin_id, origem,
             denunciante_nome, denunciante_endereco, anonimo,
             proprietario_nome, proprietario_endereco, proprietario_contato,
             tipo_denuncia, protocolo_publico)
        VALUES (?, ?, ?, NULL, 'publico', ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $propNome ?: 'Não informado',
        $propEndereco ?: null,
        $observacoes ?: null,
        $anonimo ? null : $denuncianteNome,
        $anonimo ? null : $denuncianteEnd,
        $anonimo ? 1 : 0,
        $propNome ?: null,
        $propEndereco ?: null,
        $propContato ?: null,
        $tipoDenunciaJson,
        $protocolo,
    ]);

    $denunciaId = (int) $pdo->lastInsertId();

    // ── Uploads de evidências ─────────────────────────────────────────────

    if (!empty($_FILES['evidencias']['name'][0])) {
        $uploadDir    = 'uploads/denuncias/publico/' . $protocolo . '/';
        $uploadFisico = __DIR__ . '/' . $uploadDir;
        if (!is_dir($uploadFisico)) {
            mkdir($uploadFisico, 0755, true);
        }

        $tiposPermitidos = ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov'];
        $arquivos        = $_FILES['evidencias'];

        for ($i = 0; $i < count($arquivos['name']); $i++) {
            if ($arquivos['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($arquivos['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $tiposPermitidos, true)) continue;

            $nomeSeguro    = md5(uniqid('', true)) . '.' . $ext;
            $caminhoFisico = $uploadFisico . $nomeSeguro;
            $caminhoDB     = $uploadDir . $nomeSeguro;

            if (move_uploaded_file($arquivos['tmp_name'][$i], $caminhoFisico)) {
                $pdo->prepare("
                    INSERT INTO denuncia_anexos (denuncia_id, nome_arquivo, caminho_arquivo, tipo_arquivo)
                    VALUES (?, ?, ?, ?)
                ")->execute([$denunciaId, $arquivos['name'][$i], $caminhoDB, $ext]);
            }
        }
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[denuncia_publica] Erro protocolo=' . ($protocolo ?? '?') . ': ' . $e->getMessage());
    $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Erro ao registrar a denúncia. Tente novamente.'];
    header('Location: index.php');
    exit;
}

// ── Página de sucesso ──────────────────────────────────────────────────────

$_SESSION['denuncia_protocolo'] = $protocolo;
$_SESSION['denuncia_anonimo']   = $anonimo;
header('Location: sucesso_denuncia.php');
exit;
