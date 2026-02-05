<?php
require_once 'conexao.php';
verificaLogin();

$documentoId = $_GET['id'] ?? '';

if (empty($documentoId)) {
    header("Location: requerimentos.php");
    exit;
}

// 1. Buscar assinatura inicial pelo ID fornecido na URL
$stmt = $pdo->prepare("SELECT * FROM assinaturas_digitais WHERE documento_id = ?");
$stmt->execute([$documentoId]);
$assinaturaInicial = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assinaturaInicial || !file_exists($assinaturaInicial['caminho_arquivo'])) {
    die('Documento não encontrado');
}

// 2. Buscar TODAS as assinaturas associadas ao MESMO arquivo físico e requerimento
// Isso permite agrupar assinatura técnica + assinatura do secretário
$stmtAll = $pdo->prepare("SELECT ad.*, a.matricula_portaria as assinante_matricula_portaria 
                          FROM assinaturas_digitais ad 
                          LEFT JOIN administradores a ON ad.assinante_id = a.id 
                          WHERE ad.requerimento_id = ? AND ad.nome_arquivo = ? 
                          ORDER BY ad.timestamp_assinatura ASC");
$stmtAll->execute([$assinaturaInicial['requerimento_id'], $assinaturaInicial['nome_arquivo']]);
$assinaturas = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Usa o arquivo da assinatura inicial (são todos o mesmo)
$caminhoHtml = $assinaturaInicial['caminho_arquivo'];
$caminhoJson = dirname($caminhoHtml) . '/' . pathinfo($caminhoHtml, PATHINFO_FILENAME) . '.json';

$jsonData = null;
if (file_exists($caminhoJson)) {
    $jsonData = json_decode(file_get_contents($caminhoJson), true);
}

// ... Lógica de carregamento de $htmlContent mantém-se igual ... 
// Priorizar HTML formatado dos metadados (com formatação do TinyMCE)
$htmlContent = '';
$ehTemplateA4 = false;

// Verificar se é template A4
if ($jsonData && isset($jsonData['template'])) {
    $templateNome = $jsonData['template'];
    $ehTemplateA4 = strpos($templateNome, 'template_oficial_a4') !== false || strpos($templateNome, 'licenca_previa_projeto') !== false || strpos($templateNome, 'licenca_') !== false || strpos($templateNome, 'parecer_tecnico') !== false;
}

if ($ehTemplateA4 && $jsonData && isset($jsonData['html_com_assinatura'])) {
    $htmlContent = $jsonData['html_com_assinatura'];
} elseif ($jsonData && isset($jsonData['html_completo'])) {
    $htmlContent = $jsonData['html_completo'];
} elseif ($jsonData && isset($jsonData['html_com_assinatura'])) {
    $htmlContent = $jsonData['html_com_assinatura'];
} else {
    $htmlContent = file_get_contents($caminhoHtml);
}

if (empty($htmlContent)) {
    $htmlContent = file_get_contents($caminhoHtml);
}

$conteudoTexto = '';
// $blocoAssinatura agora será um array ou string concatenada
$blocosAssinaturaHtml = ''; 

$posicaoAssinatura = ['x' => 0.7, 'y' => 0.85];
if ($jsonData && isset($jsonData['posicao_assinatura'])) {
    $posicaoAssinatura = $jsonData['posicao_assinatura'];
}

// Parse do HTML para extrair conteúdo
$parser = new DOMDocument();
libxml_use_internal_errors(true);
@$parser->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
libxml_clear_errors();

// Extrair conteúdo (mantém lógica existente)
$conteudoDiv = $parser->getElementById('conteudo');
if ($conteudoDiv) {
    foreach ($conteudoDiv->childNodes as $node) {
        $conteudoTexto .= $parser->saveHTML($node);
    }
} else {
    $body = $parser->getElementsByTagName('body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $node) {
            $conteudoTexto .= $parser->saveHTML($node);
        }
    } else {
        $conteudoTexto = $htmlContent;
    }
}

// GERAÇÃO DOS BLOCOS DE ASSINATURA (Loop para cada assinante)
// Vamos criar um container flex para as assinaturas
require_once '../includes/qrcode_service.php';

$blocosAssinaturaHtml = '<div id="container-assinaturas" style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; width: 100%;">';

foreach ($assinaturas as $ass) {
    // Preparar URL Verificação
    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Lógica para base path
    $basePath = dirname(dirname($scriptName)); // Subir um nível de /admin para raiz
    $basePath = rtrim($basePath, '/\\');
    
    // URL específica para este documento (pode ser a mesma para todos, ou por hash se necessário)
    // Aqui usamos o ID do documento que é o agrupador
    $urlVerificacaoFinal = $protocolo . '://' . $host . $basePath . '/consultar/verificar.php?id=' . $documentoId;

    $qrCodeDataUri = QRCodeService::gerarQRCode($urlVerificacaoFinal);

    $blocosAssinaturaHtml .= '<div class="assinatura-item" style="position: absolute; display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px dashed #ccc; background: rgba(255,255,255,0.85); border-radius: 8px; cursor: move; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-width: 200px;">';
    $blocosAssinaturaHtml .= '<img src="' . htmlspecialchars($qrCodeDataUri) . '" style="width: 55px; height: 55px; flex-shrink: 0;" />';
    $blocosAssinaturaHtml .= '<div class="dados-assinante" style="font-size: 11px; text-align: left; line-height: 1.3;">';
    $blocosAssinaturaHtml .= '<strong>Assinado digitalmente por:</strong><br>';
    $blocosAssinaturaHtml .= '<span style="font-size: 12px; font-weight: bold;">' . htmlspecialchars($ass['assinante_nome']) . '</span><br>';
    $blocosAssinaturaHtml .= htmlspecialchars($ass['assinante_cargo']) . '<br>';
    if (!empty($ass['assinante_matricula_portaria'])) {
        $blocosAssinaturaHtml .= 'Matrícula/Portaria: ' . htmlspecialchars($ass['assinante_matricula_portaria']) . '<br>';
    }
    $blocosAssinaturaHtml .= 'Em: ' . date('d/m/Y H:i', strtotime($ass['timestamp_assinatura'])) . '<br>';
    $blocosAssinaturaHtml .= '<a href="' . htmlspecialchars($urlVerificacaoFinal) . '" target="_blank" style="font-size: 9px; color: #0066cc; text-decoration: none;">Verificar Autenticidade</a>';
    $blocosAssinaturaHtml .= '</div></div>';
}

$blocosAssinaturaHtml .= '</div>';

// Limpar variáveis antigas para não interferir
$blocoAssinatura = $blocosAssinaturaHtml; 
$areaAssinatura = null; // Ignorar área antiga do HTML parser

$logoPath = '../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png';
$fundoPath = '../assets/SEMA/PNG/Azul/fundo.png';

$logoBase64 = '';
$fundoBase64 = '';

if (file_exists(dirname(__DIR__) . '/assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png')) {
    $logoData = file_get_contents(dirname(__DIR__) . '/assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png');
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}

if (file_exists(dirname(__DIR__) . '/assets/SEMA/PNG/Azul/fundo.png')) {
    $fundoData = file_get_contents(dirname(__DIR__) . '/assets/SEMA/PNG/Azul/fundo.png');
    $fundoBase64 = 'data:image/png;base64,' . base64_encode($fundoData);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Parecer Técnico - <?php echo htmlspecialchars($documentoId); ?></title>
    <style>
        @page {
            size: A4;
            margin: 0;
            @top-left { content: ""; }
            @top-center { content: ""; }
            @top-right { content: ""; }
            @bottom-left { content: ""; }
            @bottom-center { content: ""; }
            @bottom-right { content: ""; }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #000;
            background: #f4f4f4;
        }
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            gap: 10px;
            display: none;
        }
        .btn-save-pos {
            background: #16a34a;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            display: none;
        }
        .pagina {
            position: relative;
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .logo-container {
            position: relative;
            width: 100%;
            z-index: 10;
            text-align: center;
            padding: 8mm 20mm 2mm 20mm;
            flex-shrink: 0;
        }
        .logo-container img {
            max-width: 50mm;
            height: auto;
        }
        .conteudo-container {
            position: relative;
            flex: 1;
            z-index: 20;
            padding: 3mm 20mm 3mm 20mm;
            overflow: hidden;
            min-height: 0;
        }
        .conteudo-texto {
            position: relative;
            z-index: 21;
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            text-align: justify;
            text-justify: inter-word;
            max-height: 100%;
            overflow: hidden;
        }
        .conteudo-texto p,
        .conteudo-texto div {
            text-align: justify;
            text-justify: inter-word;
        }
        .conteudo-texto p,
        .conteudo-texto div,
        .conteudo-texto li {
            position: relative;
            z-index: 22;
        }
        /* Preservar formatação do TinyMCE */
        .conteudo-texto strong,
        .conteudo-texto b {
            font-weight: bold;
        }
        .conteudo-texto em,
        .conteudo-texto i {
            font-style: italic;
        }
        .conteudo-texto u {
            text-decoration: underline;
        }
        .conteudo-texto p[style*="text-align: center"],
        .conteudo-texto div[style*="text-align: center"],
        .conteudo-texto *[style*="text-align: center"] {
            text-align: center !important;
        }
        .conteudo-texto p[style*="text-align: right"],
        .conteudo-texto div[style*="text-align: right"],
        .conteudo-texto *[style*="text-align: right"] {
            text-align: right !important;
        }
        /* Se o usuário escolher explicitamente esquerda no editor, ainda assim mantemos justificado por padrão 
           a menos que queira mudar esta regra para permitir alinhamento à esquerda real */
        .conteudo-texto p[style*="text-align: left"],
        .conteudo-texto div[style*="text-align: left"],
        .conteudo-texto *[style*="text-align: left"] {
            text-align: justify !important;
            text-justify: inter-word;
        }
        .conteudo-texto table {
            border-collapse: collapse;
            width: 100%;
            margin: 5pt 0;
        }
        .conteudo-texto table td,
        .conteudo-texto table th {
            border: 1px solid #ddd;
            padding: 4pt 6pt;
        }
        .conteudo-texto ul,
        .conteudo-texto ol {
            padding-left: 20pt;
            margin: 5pt 0;
        }
        .conteudo-texto img {
            max-width: 100%;
            height: auto;
        }
        .fundo-imagem {
            position: absolute;
            top: 20mm;
            left: 30mm;
            right: 30mm;
            bottom: 30mm;
            width: calc(100% - 60mm);
            height: calc(100% - 50mm);
            z-index: 1;
            object-fit: contain;
            pointer-events: none;
            opacity: 0.6;
        }
        .rodape-container {
            position: relative;
            width: 100%;
            z-index: 10;
            margin-top: auto;
            padding: 5mm 20mm;
            text-align: center;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            flex-shrink: 0;
        }
        .rodape-container a {
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0 15px;
        }
        .rodape-container a:hover {
            color: #333;
        }
        .rodape-icon {
            width: 16px;
            height: 16px;
            display: inline-block;
            vertical-align: middle;
        }
        .assinatura-container {
            position: absolute;
            z-index: 30;
            flex-shrink: 0;
        }
        .assinatura-container #area-assinatura {
            background: transparent !important;
            border: none !important;
        }
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                height: 100vh;
                overflow: hidden !important;
            }
            .pagina {
                margin: 0;
                box-shadow: none;
                width: 210mm;
                height: 297mm;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
            }
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Ocultar elementos desnecessários na impressão */
            .rodape-container {
                position: absolute;
                bottom: 0;
            }
            .no-print, .assinatura-item {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button id="btn-save-position" class="btn-save-pos" onclick="salvarPosicaoAssinatura()">
            Salvar Posição
        </button>
    </div>

    <div class="pagina" id="document-page">
        <?php if (!empty($logoBase64)): ?>
        <div class="logo-container">
            <img src="<?php echo htmlspecialchars($logoBase64); ?>" alt="Logo SEMA" />
        </div>
        <?php endif; ?>

        <div class="conteudo-container">
            <?php if (!empty($fundoBase64)): ?>
            <img src="<?php echo htmlspecialchars($fundoBase64); ?>" alt="Fundo" class="fundo-imagem" />
            <?php endif; ?>

            <div class="conteudo-texto">
                <?php echo $conteudoTexto; ?>
            </div>
        </div>

        <?php if (!empty($blocoAssinatura)): ?>
        <div class="assinatura-container" id="draggable-signatures">
            <?php echo $blocoAssinatura; ?>
        </div>
        <?php endif; ?>

        <div class="rodape-container">
            <a href="https://www.instagram.com/prefeiturapaudosferros" target="_blank" rel="noopener">
                <svg class="rodape-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <span>@prefeiturapaudosferros</span>
            </a>
            <span>|</span>
            <a href="https://paudosferros.rn.gov.br/" target="_blank" rel="noopener">
                <svg class="rodape-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
                </svg>
                <span>https://paudosferros.rn.gov.br/</span>
            </a>
        </div>
    </div>

    <script>
        let coordenadas = {
            x: <?php echo $posicaoAssinatura['x']; ?>,
            y: <?php echo $posicaoAssinatura['y']; ?>
        };

        window.onload = function() {
            const container = document.getElementById('draggable-signatures');
            const items = document.querySelectorAll('.assinatura-item');
            const page = document.getElementById('document-page');
            
            if (container && page) {
                // Posicionar inicialmente conforme salvo
                const pageRect = page.getBoundingClientRect();
                items.forEach((item, index) => {
                    const itemWidth = item.offsetWidth;
                    const itemHeight = item.offsetHeight;
                    
                    // Se houver mais de uma, dar um offset horizontal para não ficarem em cima uma da outra
                    const offsetX = (index * 220); 
                    
                    const left = (coordenadas.x * pageRect.width) - (itemWidth / 2) + offsetX;
                    const top = (coordenadas.y * pageRect.height) - (itemHeight / 2);
                    
                    item.style.left = left + 'px';
                    item.style.top = top + 'px';
                    
                    inicializarDrag(item, page);
                });
            }
        }

        function inicializarDrag(elem, bounds) {
            let isDragging = false;
            let offset = { x: 0, y: 0 };

            elem.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'A') return;
                isDragging = true;
                const rect = elem.getBoundingClientRect();
                offset.x = e.clientX - rect.left;
                offset.y = e.clientY - rect.top;
                elem.style.cursor = 'grabbing';
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                
                const boundsRect = bounds.getBoundingClientRect();
                let x = e.clientX - boundsRect.left - offset.x;
                let y = e.clientY - boundsRect.top - offset.y;

                // Restringir ao documento
                const maxX = boundsRect.width - elem.offsetWidth;
                const maxY = boundsRect.height - elem.offsetHeight;
                
                x = Math.max(0, Math.min(x, maxX));
                y = Math.max(0, Math.min(y, maxY));

                elem.style.left = x + 'px';
                elem.style.top = y + 'px';
                
                document.getElementById('btn-save-position').style.display = 'block';
                const noPrint = document.querySelector('.no-print');
                if (noPrint) {
                    noPrint.style.display = 'flex';
                }
            });

            document.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    elem.style.cursor = 'move';
                    
                    const boundsRect = bounds.getBoundingClientRect();
                    const centroX = elem.offsetLeft + (elem.offsetWidth / 2);
                    const centroY = elem.offsetTop + (elem.offsetHeight / 2);
                    
                    coordenadas.x = centroX / boundsRect.width;
                    coordenadas.y = centroY / boundsRect.height;
                }
            });
        }

        async function salvarPosicaoAssinatura() {
            const btn = document.getElementById('btn-save-position');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Salvando...';

            try {
                const response = await fetch('parecer_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'atualizar_posicao_assinatura',
                        requerimento_id: <?php echo $assinaturaInicial['requerimento_id']; ?>,
                        nome_arquivo: '<?php echo $assinaturaInicial['nome_arquivo']; ?>',
                        posicao_x: coordenadas.x,
                        posicao_y: coordenadas.y
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    btn.textContent = 'Salvo!';
                    setTimeout(() => {
                        btn.style.display = 'none';
                        btn.disabled = false;
                        btn.textContent = originalText;
                        const noPrint = document.querySelector('.no-print');
                        if (noPrint) {
                            noPrint.style.display = 'none';
                        }
                    }, 2000);
                } else {
                    alert('Erro ao salvar: ' + data.error);
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro na conexão ao salvar.');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
    </script>
</body>
</html>
