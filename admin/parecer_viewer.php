<?php
require_once 'conexao.php';
verificaLogin();

$documentoId = $_GET['id'] ?? '';

if (empty($documentoId)) {
    die('ID do documento não fornecido');
}

try {
    // 1. Buscar dados do documento
    $stmt = $pdo->prepare("SELECT * FROM assinaturas_digitais WHERE documento_id = ?");
    $stmt->execute([$documentoId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        die('Documento não encontrado no banco de dados');
    }

    $caminhoHtml = $doc['caminho_arquivo'];
    
    // Tenta resolver o caminho físico se não existir diretamente
    if (!file_exists($caminhoHtml)) {
        // Tenta relativo à raiz (../../)
        $tentativa1 = dirname(__DIR__, 1) . '/' . ltrim($caminhoHtml, '/');
        // Tenta na pasta admin (onde estamos)
        $tentativa2 = __DIR__ . '/' . ltrim($caminhoHtml, '/');
        
        if (file_exists($tentativa1)) {
            $caminhoHtml = $tentativa1;
        } elseif (file_exists($tentativa2)) {
            $caminhoHtml = $tentativa2;
        }
    }

    if (!file_exists($caminhoHtml)) {
        die('Arquivo físico não encontrado no servidor: ' . htmlspecialchars($doc['caminho_arquivo']));
    }

    $htmlContent = file_get_contents($caminhoHtml);
    
    // Se for arquivo JSON lateral (metadados), tenta extrair o HTML de lá
    $caminhoJson = dirname($caminhoHtml) . '/' . pathinfo($caminhoHtml, PATHINFO_FILENAME) . '.json';
    if (file_exists($caminhoJson)) {
        $jsonData = json_decode(file_get_contents($caminhoJson), true);
        if ($jsonData) {
            $htmlContent = $jsonData['html_com_assinatura'] ?? $jsonData['html_completo'] ?? $htmlContent;
        }
    }

} catch (Exception $e) {
    die('Erro ao carregar documento: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Visualizador Rápido - <?php echo htmlspecialchars($documentoId); ?></title>
    <style>
        body {
            font-family: sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .toolbar {
            width: 100%;
            max-width: 850px;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn-back { background: #e4e6eb; color: #050505; }
        .btn-print { background: #1877f2; color: #fff; }
        .btn-pdf { background: #00a400; color: #fff; }
        
        .document-container {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 20mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            overflow-wrap: break-word;
        }

        /* Estilos básicos para o conteúdo do documento */
        .document-content {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            line-height: 1.5;
            text-align: justify;
        }
        .document-content img { max-width: 100%; height: auto; }
        
        .footer-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #65676b;
            text-align: center;
        }

        @media print {
            body { background: white; padding: 0; }
            .toolbar { display: none; }
            .document-container { box-shadow: none; padding: 0; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <div>
            <strong>Visualizando:</strong> <?php echo htmlspecialchars($doc['nome_arquivo']); ?>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-back" onclick="history.back()">Voltar</button>
            <button class="btn btn-print" onclick="window.print()">Imprimir</button>
            <a href="assinatura/redownload_pdf.php?id=<?php echo urlencode($documentoId); ?>" class="btn btn-pdf">Baixar PDF</a>
        </div>
    </div>

    <div class="document-container">
        <div class="document-content">
            <?php echo $htmlContent; ?>
        </div>
        
        <div class="footer-info">
            Documento assinado digitalmente por <strong><?php echo htmlspecialchars($doc['assinante_nome']); ?></strong><br>
            CPF: <?php echo htmlspecialchars($doc['assinante_cpf']); ?> | Cargo: <?php echo htmlspecialchars($doc['assinante_cargo']); ?><br>
            Data da Assinatura: <?php echo date('d/m/Y H:i:s', strtotime($doc['timestamp_assinatura'])); ?><br>
            Identificador: <?php echo htmlspecialchars($documentoId); ?>
        </div>
    </div>

</body>
</html>
