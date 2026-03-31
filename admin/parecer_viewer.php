<?php
require_once 'conexao.php';
verificaLogin();

$documentoId = $_GET['id'] ?? '';

if (empty($documentoId)) {
    die('ID do documento não fornecido');
}

// Buscar o nome do arquivo para exibir no título
$stmt = $pdo->prepare("SELECT nome_arquivo FROM assinaturas_digitais WHERE documento_id = ?");
$stmt->execute([$documentoId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
$nomeArquivo = $doc ? $doc['nome_arquivo'] : 'Documento';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Visualizador - <?php echo htmlspecialchars($nomeArquivo); ?></title>
    <!-- Incluir FontAwesome para ícones mais bonitos -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #111827; /* Fundo escuro igual ao admin */
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #1f2937;
            padding: 12px 24px;
            color: #f3f4f6;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 10;
            flex-shrink: 0;
            height: 60px;
            box-sizing: border-box;
        }

        .document-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .document-title i {
            color: #ef4444; /* Vermelho estilo PDF */
            font-size: 20px;
        }

        .actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-close {
            background: #374151;
            color: #d1d5db;
        }
        
        .btn-close:hover {
            background: #4b5563;
        }

        .btn-download {
            background: #059669;
            color: #ffffff;
        }

        .btn-download:hover {
            background: #047857;
        }

        .btn-print {
            background: #1d4ed8;
            color: #ffffff;
        }

        .btn-print:hover {
            background: #1e40af;
        }

        .viewer-container {
            flex: 1;
            width: 100%;
            height: calc(100% - 60px);
            position: relative;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background-color: #525659; /* Cor de fundo típica de visualizadores de PDF */
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="document-title">
            <i class="fas fa-file-pdf"></i>
            <div>
                <?php echo htmlspecialchars($nomeArquivo); ?>
            </div>
        </div>
        <div class="actions">
            <!-- Botão Fechar -->
            <button onclick="history.back()" class="btn btn-close">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>
            
            <!-- Botão Imprimir -->
            <button onclick="document.querySelector('iframe').contentWindow.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Imprimir
            </button>

            <!-- Botão Download -->
            <a href="assinatura/redownload_pdf.php?id=<?php echo urlencode($documentoId); ?>" class="btn btn-download">
                <i class="fas fa-download"></i> Baixar PDF
            </a>
        </div>
    </div>

    <div class="viewer-container">
        <iframe id="pdfFrame" src="assinatura/redownload_pdf.php?id=<?php echo urlencode($documentoId); ?>&inline=1" title="Visualizador de PDF"></iframe>
    </div>

<?php if (!empty($_GET['autoprint'])): ?>
<script>
    document.getElementById('pdfFrame').addEventListener('load', function() {
        setTimeout(function() {
            document.getElementById('pdfFrame').contentWindow.print();
        }, 800);
    });
</script>
<?php endif; ?>

</body>
</html>

