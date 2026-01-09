<?php
require_once 'conexao.php';
verificaLogin();

$documentoId = $_GET['id'] ?? '';

if (empty($documentoId)) {
    header("Location: requerimentos.php");
    exit;
}

$stmt = $pdo->prepare("SELECT caminho_arquivo, documento_id FROM assinaturas_digitais WHERE documento_id = ?");
$stmt->execute([$documentoId]);
$assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assinatura || !file_exists($assinatura['caminho_arquivo'])) {
    die('Documento não encontrado');
}

$caminhoHtml = $assinatura['caminho_arquivo'];
$caminhoJson = dirname($caminhoHtml) . '/' . pathinfo($caminhoHtml, PATHINFO_FILENAME) . '.json';

$jsonData = null;
if (file_exists($caminhoJson)) {
    $jsonData = json_decode(file_get_contents($caminhoJson), true);
}

// Priorizar HTML formatado dos metadados (com formatação do TinyMCE)
$htmlContent = '';
$ehTemplateA4 = false;

// Verificar se é template A4 pelo nome do arquivo ou template no JSON
if ($jsonData && isset($jsonData['template'])) {
    $templateNome = $jsonData['template'];
    $ehTemplateA4 = strpos($templateNome, 'template_oficial_a4') !== false || strpos($templateNome, 'licenca_previa_projeto') !== false || strpos($templateNome, 'parecer_tecnico') !== false;
}

if ($ehTemplateA4) {
    // Para templates A4, usar html_com_assinatura que tem a estrutura completa + assinatura
    if ($jsonData && isset($jsonData['html_com_assinatura'])) {
        $htmlContent = $jsonData['html_com_assinatura'];
    } else {
        // Fallback para arquivo HTML
        $htmlContent = file_get_contents($caminhoHtml);
    }
} else {
    // Para outros templates, usar html_completo (formatação TinyMCE)
    if ($jsonData && isset($jsonData['html_completo'])) {
        $htmlContent = $jsonData['html_completo'];
    } else {
        // Fallback para html_com_assinatura ou arquivo
        if ($jsonData && isset($jsonData['html_com_assinatura'])) {
            $htmlContent = $jsonData['html_com_assinatura'];
        } else {
            $htmlContent = file_get_contents($caminhoHtml);
        }
    }
}

// Se não encontrou HTML completo, usar o HTML do arquivo
if (empty($htmlContent)) {
    $htmlContent = file_get_contents($caminhoHtml);
}

$conteudoTexto = '';
$blocoAssinatura = '';
$posicaoAssinatura = ['x' => 0.7, 'y' => 0.85];

if ($jsonData && isset($jsonData['posicao_assinatura'])) {
    $posicaoAssinatura = $jsonData['posicao_assinatura'];
}

// Para templates A4, extrair conteúdo e assinatura do html_com_assinatura
if ($ehTemplateA4) {
    // O htmlContent já deve ser html_com_assinatura que tem estrutura completa
    $parser = new DOMDocument();
    libxml_use_internal_errors(true);
    @$parser->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
    libxml_clear_errors();

    $conteudoDiv = $parser->getElementById('conteudo');
    if ($conteudoDiv) {
        $conteudoTexto = '';
        foreach ($conteudoDiv->childNodes as $node) {
            $conteudoTexto .= $parser->saveHTML($node);
        }
    }

    $areaAssinatura = $parser->getElementById('area-assinatura');
} else {
    // Para outros templates, usar o parser padrão
    $parser = new DOMDocument();
    libxml_use_internal_errors(true);
    @$parser->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
    libxml_clear_errors();

    $conteudoDiv = $parser->getElementById('conteudo');
    if ($conteudoDiv) {
        $conteudoTexto = '';
        foreach ($conteudoDiv->childNodes as $node) {
            $conteudoTexto .= $parser->saveHTML($node);
        }
    } else {
        $body = $parser->getElementsByTagName('body')->item(0);
        if ($body) {
            $conteudoTexto = '';
            foreach ($body->childNodes as $node) {
                $conteudoTexto .= $parser->saveHTML($node);
            }
        } else {
            $conteudoTexto = $htmlContent;
        }
    }

    $areaAssinatura = $parser->getElementById('area-assinatura');
}
$urlVerificacao = '';
if ($jsonData && isset($jsonData['url_verificacao'])) {
    $urlVerificacao = $jsonData['url_verificacao'];
}

// Se não encontrou área de assinatura no HTML, tentar reconstruir usando dados do JSON
$blocoAssinatura = '';
if (!$areaAssinatura && $jsonData && isset($jsonData['dados_assinatura'])) {
    $dadosAssinatura = $jsonData['dados_assinatura'];
    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = dirname($scriptName);
    $basePath = '';
    if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') {
        $basePath = rtrim($scriptDir, '/\\');
        if (strpos($basePath, '/admin') !== false) {
            $basePath = str_replace('/admin', '', $basePath);
        }
        if (strpos($basePath, '\\admin') !== false) {
            $basePath = str_replace('\\admin', '', $basePath);
        }
        $basePath = rtrim($basePath, '/\\');
    }
    $urlVerificacaoFinal = $urlVerificacao ?: ($protocolo . '://' . $host . $basePath . '/consultar/verificar.php?id=' . $documentoId);

    // Gerar QR code se necessário
    require_once '../includes/qrcode_service.php';
    $qrCodeDataUri = QRCodeService::gerarQRCode($urlVerificacaoFinal);

    // Criar bloco de assinatura diretamente como HTML
    $blocoAssinatura = '<div id="area-assinatura" style="position: relative; display: flex; align-items: center; gap: 15px; background: transparent; padding: 10px; z-index: 1000;">';
    $blocoAssinatura .= '<img src="' . htmlspecialchars($qrCodeDataUri) . '" style="width: 60px; height: 60px; flex-shrink: 0;" />';
    $blocoAssinatura .= '<div class="dados-assinante" style="font-size: 12px; text-align: left;">';
    $blocoAssinatura .= '<strong>' . htmlspecialchars($dadosAssinatura['assinante_nome'] ?? '') . '</strong><br>';
    if (!empty($dadosAssinatura['assinante_cpf'])) {
        $blocoAssinatura .= 'CPF: ' . htmlspecialchars($dadosAssinatura['assinante_cpf']) . '<br>';
    }
    $blocoAssinatura .= htmlspecialchars($dadosAssinatura['assinante_cargo'] ?? '') . '<br>';
    if (!empty($dadosAssinatura['assinante_matricula_portaria'])) {
        $blocoAssinatura .= htmlspecialchars($dadosAssinatura['assinante_matricula_portaria']) . '<br>';
    }
    if (!empty($dadosAssinatura['data_assinatura'])) {
        $dataAssinatura = date('d/m/Y H:i', strtotime($dadosAssinatura['data_assinatura']));
        $blocoAssinatura .= htmlspecialchars($dataAssinatura) . '<br>';
    }
    $blocoAssinatura .= '<a href="' . htmlspecialchars($urlVerificacaoFinal) . '" target="_blank" style="font-size: 10px; color: #0066cc; text-decoration: underline;">Verificar Autenticidade</a>';
    $blocoAssinatura .= '</div></div>';
}

if ($areaAssinatura && empty($blocoAssinatura)) {
    $blocoAssinaturaHtml = $parser->saveHTML($areaAssinatura);

    $parserAssinatura = new DOMDocument();
    @$parserAssinatura->loadHTML('<?xml encoding="UTF-8">' . $blocoAssinaturaHtml);
    $areaAssinaturaNova = $parserAssinatura->getElementById('area-assinatura');
    if ($areaAssinaturaNova) {
        $styleAtual = $areaAssinaturaNova->getAttribute('style');
        $styleAtual = preg_replace('/background:\s*[^;]+;?/i', '', $styleAtual);
        $styleAtual = preg_replace('/background-color:\s*[^;]+;?/i', '', $styleAtual);
        $styleAtual = trim($styleAtual, '; ');
        $styleAtual .= '; background: transparent;';
        $areaAssinaturaNova->setAttribute('style', $styleAtual);

        if (!empty($urlVerificacao)) {
            $divDados = $parserAssinatura->getElementsByTagName('div')->item(0);
            if ($divDados && $divDados instanceof DOMElement) {
                $linksExistentes = $parserAssinatura->getElementsByTagName('a');
                $temLinkVerificacao = false;
                foreach ($linksExistentes as $link) {
                    if ($link instanceof DOMElement && strpos($link->getAttribute('href'), 'verificar.php') !== false) {
                        $temLinkVerificacao = true;
                        break;
                    }
                }

                if (!$temLinkVerificacao) {
                    $linkVerificacao = $parserAssinatura->createElement('a', 'Verificar Autenticidade');
                    $linkVerificacao->setAttribute('href', $urlVerificacao);
                    $linkVerificacao->setAttribute('target', '_blank');
                    $linkVerificacao->setAttribute('style', 'font-size: 10px; color: #0066cc; text-decoration: underline;');
                    $divDados->appendChild($parserAssinatura->createElement('br'));
                    $divDados->appendChild($linkVerificacao);
                }
            }
        }

        $blocoAssinatura = $parserAssinatura->saveHTML($areaAssinaturaNova);
    } else {
        $blocoAssinatura = $blocoAssinaturaHtml;
    }
}

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
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            background: #f0f0f0;
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
            font-size: 10pt;
            line-height: 1.5;
            color: #000;
            text-align: justify;
            max-height: 100%;
            overflow: hidden;
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
        .conteudo-texto p[style*="text-align: left"],
        .conteudo-texto div[style*="text-align: left"],
        .conteudo-texto *[style*="text-align: left"] {
            text-align: left !important;
        }
        .conteudo-texto table {
            border-collapse: collapse;
            width: 100%;
            margin: 10pt 0;
        }
        .conteudo-texto table td,
        .conteudo-texto table th {
            border: 1px solid #ddd;
            padding: 8pt;
        }
        .conteudo-texto ul,
        .conteudo-texto ol {
            padding-left: 30pt;
            margin: 10pt 0;
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
            }
            .pagina {
                margin: 0;
                box-shadow: none;
                width: 210mm;
                height: 297mm;
                page-break-after: always;
            }
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="pagina">
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
        <div class="assinatura-container" style="left: <?php echo ($posicaoAssinatura['x'] * 100); ?>%; top: <?php echo ($posicaoAssinatura['y'] * 100); ?>%; transform: translate(-50%, -50%);">
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
        window.onload = function() {
            setTimeout(function() {
                if (window.print) {
                    try {
                        const mediaQueryList = window.matchMedia('print');
                        mediaQueryList.addListener(function(mql) {
                            if (mql.matches) {
                                document.body.style.margin = '0';
                                document.body.style.padding = '0';
                            }
                        });
                    } catch (e) {
                        console.log('Configuração de impressão não suportada');
                    }
                    window.print();
                }
            }, 500);
        }

        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 100);
        }

        window.onbeforeprint = function() {
            document.body.classList.add('printing');
        }
    </script>
</body>
</html>
