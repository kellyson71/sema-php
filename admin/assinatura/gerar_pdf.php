<?php

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
}
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class SEMA_PDF extends TCPDF {
    
    public $assinante_nome = '';
    public $assinante_cargo = '';
    public $assinante_data = '';
    public $assinante_cpf = '';
    public $assinante_matricula = '';

    // Header Premium com Marca D'água
    public function Header() {
        $image_file = dirname(__DIR__, 2) . '/assets/SEMA/PNG/Azul/Logo SEMA Vertical.png';
        
        // 1. Marca D'água Transparente no Centro da Página (Discreta: 6%)
        $this->SetAlpha(0.06); 
        if (file_exists($image_file)) {
            $this->Image($image_file, 55, 100, 100, '', 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        $this->SetAlpha(1); 
        
        // 2. Logo no Topo Esquerdo (Movida um pouco mais para cima: de Y 10 para Y 5, mantendo o tamanho 15mm)
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 6, 17, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        $this->SetFont('helvetica', 'B', 10);
        $this->SetXY(35, 11);
        $this->SetTextColor(40, 40, 40);
        $this->Cell(0, 5, 'PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN', 0, 1, 'L', 0, '', 0, false, 'M', 'M');
        
        $this->SetFont('helvetica', 'B', 8);
        $this->SetXY(35, 15);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA', 0, 1, 'L', 0, '', 0, false, 'M', 'M');
        
        // Linha divisória fina verde movida pra cima (-2mm)
        $this->SetLineStyle(array('width' => 0.3, 'color' => array(45, 134, 97))); 
        $this->Line(15, 23, 195, 23);
    }

    // Footer — linha discreta + numeração de página
    public function Footer() {
        $this->SetY(-12);
        $this->SetLineStyle(array('width' => 0.1, 'color' => array(210, 210, 210)));
        $this->Line(15, $this->GetY(), 195, $this->GetY());

        $this->SetY(-10);
        $this->SetFont('helvetica', '', 6);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 10, 'Pag. ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages() . '   —   Prefeitura Municipal de Pau dos Ferros/RN — SEMA', 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

/**
 * Cria uma instância base do PDF com a mesma área útil exibida no editor A4.
 */
function criarParecerPdfBase(array $primeiroAssinante, string $numero_processo, array $layout): SEMA_PDF
{
    $pdf = new SEMA_PDF('P', 'mm', 'A4', true, 'UTF-8', false);

    $pdf->assinante_nome      = strtoupper($primeiroAssinante['nome'] ?? '');
    $pdf->assinante_cargo     = $primeiroAssinante['cargo'] ?? '';
    $pdf->assinante_data      = $primeiroAssinante['data_hora'] ?? date('d/m/Y H:i:s');
    $pdf->assinante_cpf       = $primeiroAssinante['cpf'] ?? '';
    $pdf->assinante_matricula = $primeiroAssinante['matricula'] ?? '';

    $pdf->SetCreator('SEMA Documentos Digitais');
    $pdf->SetAuthor($pdf->assinante_nome);
    $pdf->SetTitle('Parecer Ambiental - ' . $numero_processo);

    $marginLeft = (float) ($layout['margin_left'] ?? 15.0);
    $marginTop = (float) ($layout['margin_top'] ?? 27.0);
    $marginRight = (float) ($layout['margin_right'] ?? 15.0);
    $footerMargin = (float) ($layout['footer_margin'] ?? 12.0);
    $pageBreakBottom = (float) ($layout['page_break_bottom'] ?? 15.0);
    $cellHeightRatio = (float) ($layout['cell_height_ratio'] ?? 1.0);

    $pdf->SetMargins($marginLeft, $marginTop, $marginRight);
    $pdf->SetFooterMargin($footerMargin);
    $pdf->SetAutoPageBreak(true, $pageBreakBottom);
    $pdf->setCellHeightRatio($cellHeightRatio);
    // TCPDF adiciona ~1 line-height de espaço antes de cada bloco (via $hbz na
    // função de abertura do HTML parser) quando $on > 0 e ~1 line-height depois
    // (via $hb) quando a tag não é div/dt/dd/li/br/hr. Zeramos $on só para os
    // blocos de fluxo de texto — NUNCA para ul/ol/li/table, porque o $hbz
    // deles funciona como quebra de linha natural entre itens e linhas.
    $zeroVSpace = [0 => ['h' => '', 'n' => 0], 1 => ['h' => '', 'n' => 0]];
    $pdf->setHtmlVSpace([
        'div'   => $zeroVSpace,
        'p'     => $zeroVSpace,
        'h1'    => $zeroVSpace,
        'h2'    => $zeroVSpace,
        'h3'    => $zeroVSpace,
        'h4'    => $zeroVSpace,
        'h5'    => $zeroVSpace,
        'h6'    => $zeroVSpace,
    ]);
    $pdf->SetFont('times', '', 12);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->AddPage();

    return $pdf;
}

/**
 * Remove ruídos do editor e força uma régua visual consistente para o TCPDF.
 */
function normalizarHtmlParaParecerPdf(string $conteudo_html): string
{
    $html = trim($conteudo_html);

    $html = preg_replace('/\s+id=("|\')(documento|conteudo|fundo-imagem)\1/i', '', $html);
    // Marcadores visuais de corte/separação de página do editor — nunca vão para o PDF
    $html = preg_replace('/<div[^>]*class="[^"]*page-(?:cut|gap|break-indicator)[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $html);
    $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
    // Summernote injeta <p><br></p> ao apertar Enter — no editor viram "respiros"
    // visualmente discretos, mas no TCPDF cada um vira uma linha cheia. Removemos
    // para o PDF bater com a preview.
    $html = preg_replace('/<p[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html);
    $html = preg_replace('/<div[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/div>/i', '', $html);

    return $html;
}

/**
 * Gera a folha de estilos do PDF alinhada ao preview do editor.
 */
function montarCssParecerPdf(array $layout): string
{
    $bodyFontSize      = number_format((float) $layout['body_font_size'], 2, '.', '');
    $bodyLineHeight    = number_format((float) $layout['body_line_height'], 2, '.', '');
    $tableCellVPad     = number_format((float) $layout['table_cell_v_padding'], 2, '.', '');
    $tableCellHPad     = number_format((float) $layout['table_cell_h_padding'], 2, '.', '');
    $condPadV          = number_format((float) $layout['cond_padding_v'], 2, '.', '');
    $condPadH          = number_format((float) $layout['cond_padding_h'], 2, '.', '');

    return '<style>
        body, .pdf-document {
            font-family: "Times New Roman", Times, serif;
            font-size: ' . $bodyFontSize . 'pt;
            line-height: ' . $bodyLineHeight . ';
            color: #1e1e1e;
            text-align: justify;
            margin: 0;
            padding: 0;
        }

        p {
            margin: 0 0 9pt 0;
            line-height: ' . $bodyLineHeight . ';
        }

        .texto-parecer p {
            text-indent: 50px;
            line-height: 1.70;
            margin-bottom: 9pt;
        }

        div {
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 8pt 0;
        }

        h2, h3 {
            font-size: 12pt;
            font-weight: bold;
            margin: 0 0 8pt 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 8pt 0;
        }

        td, th {
            vertical-align: middle;
            line-height: ' . $bodyLineHeight . ';
            padding: ' . $tableCellVPad . 'px ' . $tableCellHPad . 'px;
            font-size: 11pt;
        }

        ul, ol {
            margin: 4pt 0;
            padding-left: 18pt;
        }

        li {
            margin: 0 0 2pt 0;
            line-height: ' . $bodyLineHeight . ';
        }

        .condicionantes {
            font-size: 9pt;
            border: 1px solid #000;
            padding: ' . $condPadV . 'pt ' . $condPadH . 'pt;
        }

        .var-field {
            color: #1e1e1e !important;
            background: transparent !important;
            font-weight: bold !important;
            text-decoration: none !important;
        }

        span[style*="1a5276"] {
            color: #1e1e1e !important;
        }
    </style>';
}

/**
 * Renderiza o HTML em um PDF com a configuração de layout especificada.
 */
function renderizarParecerPdf(string $conteudo_html, array $assinantes, string $numero_processo, array $layout): SEMA_PDF
{
    $primeiro = $assinantes[0] ?? [];
    $pdf = criarParecerPdfBase($primeiro, $numero_processo, $layout);

    $htmlNormalizado = normalizarHtmlParaParecerPdf($conteudo_html);
    $cssBase = montarCssParecerPdf($layout);
    $htmlCorpo = $cssBase . '<div class="pdf-document">' . $htmlNormalizado . '</div>';

    $pdf->writeHTML($htmlCorpo, true, false, true, false, '');

    $pageCount = $pdf->getNumPages();
    $blankTrailingPageThreshold = (float) ($layout['margin_top'] ?? 27.0) + 6.0;
    if ($pageCount > 1 && $pdf->GetY() <= $blankTrailingPageThreshold) {
        $pdf->deletePage($pageCount);
        $pdf->setPage($pdf->getNumPages());
    }

    return $pdf;
}

/**
 * Mascara CPF para exibição pública no carimbo: 123.456.789-01 → ***.456.789-**
 */
function _mascararCpfCarimbo(string $cpf): string
{
    $dig = preg_replace('/\D/', '', $cpf);
    if (strlen($dig) !== 11) return '';
    return '***.' . substr($dig, 3, 3) . '.' . substr($dig, 6, 3) . '-**';
}

/**
 * Dimensões do bloco de assinatura digital (mm) conforme nº de assinantes.
 * Centralizado para que preview (editor) e PDF usem os mesmos números.
 */
function dimensoesBlocoAssinatura(int $nAssinantes): array
{
    $w = 88.0;
    $h = 20.0 + max(0, $nAssinantes - 1) * 7.5;
    return [$w, $h];
}

/**
 * Posiciona o bloco de assinatura digital sem interferir na paginação.
 *
 * $opcoes:
 *   verify_url  — URL completa de verificação pública (vira QR code)
 *   doc_codigo  — código curto exibido ao lado do QR
 *   sig_pos     — ['x' => mm, 'y' => mm] canto superior-esquerdo do bloco
 *                 na última página; null = inferior-direito padrão
 */
function aplicarBlocosAssinaturaNoPdf(SEMA_PDF $pdf, array $assinantes, array $opcoes = []): void
{
    if (empty($assinantes)) return;

    $pdf->lastPage();
    $pdf->SetAutoPageBreak(false);

    $pw = $pdf->getPageWidth();
    $ph = $pdf->getPageHeight();

    // Modo linha manual: apenas um assinante com tipo='manual'
    if (($assinantes[0]['tipo'] ?? '') === 'manual') {
        $bW = 70;
        $bX = ($pw - $bW) / 2;
        $bY = $ph - 14 - 13;
        _renderLinhaAssinaturaManual($pdf, $assinantes[0], $bX, $bY, $bW);
        return;
    }

    [$bW, $bH] = dimensoesBlocoAssinatura(count($assinantes));

    $pos = $opcoes['sig_pos'] ?? null;
    if (is_array($pos) && isset($pos['x'], $pos['y'])) {
        // Limita dentro da área útil da página
        $bX = max(10.0, min((float) $pos['x'], $pw - $bW - 10.0));
        $bY = max(25.0, min((float) $pos['y'], $ph - $bH - 12.0));
    } else {
        $bX = $pw - 15 - $bW;
        $bY = $ph - 14 - $bH;
    }

    _renderBlocoAssinaturaGov($pdf, $assinantes, $bX, $bY, $bW, $bH, $opcoes);
}

/**
 * Bloco de assinatura institucional: logo da SEMA à esquerda, relação de
 * assinantes à direita, rodapé com URL/código de verificação.
 */
function _renderBlocoAssinaturaGov(SEMA_PDF $pdf, array $assinantes, float $bX, float $bY, float $bW, float $bH, array $opcoes): void
{
    $verifyUrl = $opcoes['verify_url'] ?? '';
    $docCodigo = $opcoes['doc_codigo'] ?? '';

    // Moldura
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($bX, $bY, $bW, $bH, 'F');
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.2);
    $pdf->Rect($bX, $bY, $bW, $bH, 'D');

    // Barra de acento verde SEMA no topo
    $pdf->SetFillColor(28, 75, 54);
    $pdf->Rect($bX, $bY, $bW, 1.1, 'F');

    // Logo SEMA à esquerda (no lugar do antigo QR code)
    $logoSize = 15.0;
    $logoX = $bX + 2.5;
    $logoY = $bY + 2.8;
    $logoFile = dirname(__DIR__, 2) . '/assets/SEMA/PNG/Azul/Logo SEMA Vertical.png';
    $textX = $bX + 2.0;
    if (is_file($logoFile)) {
        $pdf->Image($logoFile, $logoX, $logoY, $logoSize, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $textX = $logoX + $logoSize + 3.0;
    }
    $textW = $bX + $bW - 2.0 - $textX;

    // Cabeçalho
    $pdf->SetFont('helvetica', 'B', 6.2);
    $pdf->SetTextColor(28, 75, 54);
    $pdf->SetXY($textX, $bY + 2.4);
    $titulo = count($assinantes) > 1
        ? 'DOCUMENTO ASSINADO ELETRONICAMENTE POR'
        : 'DOCUMENTO ASSINADO ELETRONICAMENTE';
    $pdf->Cell($textW, 2.8, $titulo, 0, 0, 'L');

    // Assinantes
    $linhaY = $bY + 5.6;
    foreach ($assinantes as $assinante) {
        $nome  = strtoupper($assinante['nome'] ?? '');
        $cargo = $assinante['cargo'] ?? '';
        $cpfM  = _mascararCpfCarimbo($assinante['cpf'] ?? '');
        $data  = $assinante['data_hora'] ?? '';

        $pdf->SetFont('helvetica', 'B', 6.4);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY($textX, $linhaY);
        $pdf->Cell($textW, 2.8, $nome, 0, 0, 'L');

        $detalhe = $cargo;
        if ($cpfM)  $detalhe .= ($detalhe ? '  |  ' : '') . 'CPF ' . $cpfM;
        if ($data)  $detalhe .= ($detalhe ? '  |  ' : '') . $data;

        $pdf->SetFont('helvetica', '', 5.4);
        $pdf->SetTextColor(85, 85, 85);
        $pdf->SetXY($textX, $linhaY + 2.9);
        $pdf->Cell($textW, 2.4, $detalhe, 0, 0, 'L');

        $linhaY += 7.5;
    }

    // Rodapé: código do documento + URL curta de verificação
    if ($verifyUrl !== '' || $docCodigo !== '') {
        $rodapeY = $bY + $bH - 5.6;
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.15);
        $pdf->Line($textX, $rodapeY - 0.5, $bX + $bW - 2.0, $rodapeY - 0.5);

        // Código do documento, em destaque
        if ($docCodigo !== '') {
            $pdf->SetFont('helvetica', '', 4.8);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($textX, $rodapeY);
            $pdf->Cell(8.0, 2.2, 'Código: ', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 4.8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->SetXY($textX + 8.0, $rodapeY);
            $pdf->Cell($textW - 8.0, 2.2, $docCodigo, 0, 0, 'L');
        }

        // URL curta de verificação (host/verificar, sem caminho longo)
        if ($verifyUrl !== '') {
            $pdf->SetFont('helvetica', 'B', 4.8);
            $pdf->SetTextColor(28, 75, 54);
            $pdf->SetXY($textX, $rodapeY + 2.3);
            $urlExibicao = preg_replace('#^https?://#', '', $verifyUrl);
            $pdf->Cell($textW, 2.2, $urlExibicao, 0, 0, 'L');
        }
    }
}

/**
 * Renderiza uma linha simples de assinatura manual (sem bloco digital).
 */
function _renderLinhaAssinaturaManual(SEMA_PDF $pdf, array $assinante, float $bX, float $bY, float $bW): void
{
    $nome  = strtoupper($assinante['nome'] ?? '');
    $cargo = $assinante['cargo'] ?? '';

    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.35);
    $pdf->Line($bX, $bY + 7, $bX + $bW, $bY + 7);

    $pdf->SetFont('helvetica', 'B', 5.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($bX, $bY + 8);
    $pdf->Cell($bW, 3, $nome, 0, 0, 'C');

    if ($cargo) {
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY($bX, $bY + 11);
        $pdf->Cell($bW, 2.5, $cargo, 0, 0, 'C');
    }
}

/**
 * Gera e baixa/salva o PDF assinado.
 *
 * $assinante_ou_assinantes aceita:
 *   - array simples com 'nome' (1 assinante, retrocompatível)
 *   - array indexado de arrays de assinante (múltiplos)
 *
 * $opcoes (todas opcionais):
 *   verify_url — URL de verificação pública (gera QR no carimbo)
 *   doc_codigo — código curto do documento
 *   sig_pos    — ['x' => mm, 'y' => mm] posição do bloco na última página
 */
function emitirParecerAssinado($conteudo_html, $assinante_ou_assinantes, $numero_processo, $modo_saida = 'D', $caminho_salvar = null, array $opcoes = []) {

    if (isset($assinante_ou_assinantes['nome'])) {
        $assinantes = [$assinante_ou_assinantes];
    } else {
        $assinantes = array_values((array) $assinante_ou_assinantes);
    }

    $layout = [
        'body_font_size' => 12.0,
        'body_line_height' => 1.40,       // editor: --doc-line-h: 1.4
        'margin_left' => 15.0,
        'margin_top' => 27.0,
        'margin_right' => 15.0,
        'footer_margin' => 12.0,
        'page_break_bottom' => 15.0,
        'cell_height_ratio' => 1.0,
        'table_cell_v_padding' => 5.0,    // editor: --doc-table-vpad: 5px
        'table_cell_h_padding' => 8.0,    // editor: --doc-table-hpad: 8px
        'cond_padding_v' => 6.0,
        'cond_padding_h' => 8.0,
    ];

    $pdf = renderizarParecerPdf($conteudo_html, $assinantes, $numero_processo, $layout);

    aplicarBlocosAssinaturaNoPdf($pdf, $assinantes, $opcoes);

    // Embute o documento_id nos metadados (Keywords) para o verificador por
    // upload reconhecer o documento mesmo se o hash do arquivo mudar. Ignora o
    // valor 'PREVIEW' (pré-visualização não registra nem deve ser rastreável).
    $docCodigo = $opcoes['doc_codigo'] ?? '';
    if ($docCodigo !== '' && $docCodigo !== 'PREVIEW') {
        $pdf->SetKeywords('SEMA-DOC-ID:' . $docCodigo);
    }

    if (ob_get_length()) {
       ob_clean();
    }

    $nome_arquivo = 'Parecer_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $numero_processo) . '_' . date('His') . '.pdf';
    
    if ($modo_saida === 'F' && $caminho_salvar) {
        $pdf->Output($caminho_salvar, 'F');
        return $nome_arquivo;
    } else {
        $pdf->Output($nome_arquivo, $modo_saida);
    }
}
