<?php
require_once 'conexao.php';
verificaLogin();

if (!isset($_GET['requerimento_id'])) {
    header("Location: requerimentos.php");
    exit;
}

$requerimento_id = (int)$_GET['requerimento_id'];

try {
    // Buscar dados do requerimento
    $stmt = $pdo->prepare("SELECT protocolo FROM requerimentos WHERE id = ?");
    $stmt->execute([$requerimento_id]);
    $requerimento = $stmt->fetch();

    if (!$requerimento) {
        header("Location: requerimentos.php");
        exit;
    }

    // Buscar documentos do requerimento
    $stmt = $pdo->prepare("SELECT * FROM documentos WHERE requerimento_id = ? ORDER BY id");
    $stmt->execute([$requerimento_id]);
    $documentos = $stmt->fetchAll();

    if (empty($documentos)) {
        header("Location: visualizar_requerimento.php?id=" . $requerimento_id . "&error=no_files");
        exit;
    }

    // Criar arquivo ZIP temporário
    $zip = new ZipArchive();
    $zipFileName = "documentos_protocolo_" . $requerimento['protocolo'] . "_" . date('Y-m-d_H-i-s') . ".zip";
    $zipFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

    if ($zip->open($zipFilePath, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("Não foi possível criar o arquivo ZIP");
    }
    $addedFiles = 0;
    foreach ($documentos as $doc) {
        // Construir o caminho correto do arquivo
        $caminho_doc = ltrim($doc['caminho'], '/\\');
        $filePath = "../uploads/" . $caminho_doc;

        if (file_exists($filePath)) {
            // Adicionar arquivo ao ZIP com nome original
            $zip->addFile($filePath, $doc['nome_original']);
            $addedFiles++;
        } else {
            error_log("Arquivo não encontrado: " . $filePath . " (caminho no banco: " . $doc['caminho'] . ")");
        }
    }

    $zip->close();

    if ($addedFiles === 0) {
        unlink($zipFilePath);
        header("Location: visualizar_requerimento.php?id=" . $requerimento_id . "&error=no_files_found");
        exit;
    }

    // Registrar no histórico de ações
    $acao = "Baixou todos os documentos do protocolo #{$requerimento['protocolo']} ({$addedFiles} arquivos)";
    $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['admin_id'], $requerimento_id, $acao]);

    // Configurar headers para download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . filesize($zipFilePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Enviar arquivo
    readfile($zipFilePath);

    // Limpar arquivo temporário
    unlink($zipFilePath);
} catch (Exception $e) {
    error_log("Erro ao criar ZIP: " . $e->getMessage());
    header("Location: visualizar_requerimento.php?id=" . $requerimento_id . "&error=zip_error");
    exit;
}
