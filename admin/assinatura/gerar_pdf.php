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

    $html = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $html);
    $html = preg_replace('/\s+id=("|\')(documento|conteudo|fundo-imagem)\1/i', '', $html);
    $html = preg_replace('/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html);
    $html = preg_replace('/<div>(?:\s|&nbsp;|<br\s*\/?>)*<\/div>/i', '', $html);

    return $html;
}

/**
 * Gera a folha de estilos do PDF alinhada ao preview do editor.
 */
function montarCssParecerPdf(array $layout): string
{
    $bodyFontSize      = number_format((float) $layout['body_font_size'], 2, '.', '');
    $bodyLineHeight    = number_format((float) $layout['body_line_height'], 2, '.', '');
    $paragraphSpacing  = number_format((float) $layout['paragraph_spacing'], 2, '.', '');
    $paragraphIndent   = number_format((float) $layout['paragraph_indent'], 2, '.', '');
    $paragraphLine     = number_format((float) $layout['paragraph_line_height'], 2, '.', '');
    $titleSpacing      = number_format((float) $layout['title_spacing'], 2, '.', '');
    $sectionSpacing    = number_format((float) $layout['section_spacing'], 2, '.', '');
    $dataLocalTop      = number_format((float) $layout['data_local_top'], 2, '.', '');
    $dataLocalBottom   = number_format((float) $layout['data_local_bottom'], 2, '.', '');
    $signatureTop      = number_format((float) $layout['signature_top'], 2, '.', '');
    $signaturePad      = number_format((float) $layout['signature_padding'], 2, '.', '');
    $titleFontSize     = number_format((float) ($layout['title_font_size'] ?? 15.0), 2, '.', '');
    $sectionTitleSize  = number_format((float) ($layout['section_title_font_size'] ?? 11.5), 2, '.', '');
    $sectionTitleTop   = number_format((float) ($layout['section_title_margin_top'] ?? ($paragraphSpacing + 2.0)), 2, '.', '');
    $sectionTitleBottom = number_format((float) ($layout['section_title_margin_bottom'] ?? $paragraphSpacing), 2, '.', '');
    $dataBlockSpacing  = number_format((float) ($layout['data_block_spacing'] ?? $sectionSpacing), 2, '.', '');
    $dataBlockLine     = number_format((float) ($layout['data_block_line_height'] ?? $bodyLineHeight), 2, '.', '');
    $dataLineSpacing   = number_format((float) ($layout['data_line_spacing'] ?? 2.0), 2, '.', '');
    $tableCellVPad     = number_format((float) $layout['table_cell_v_padding'], 2, '.', '');
    $tableCellHPad     = number_format((float) $layout['table_cell_h_padding'], 2, '.', '');
    $listSpacing       = number_format((float) $layout['list_spacing'], 2, '.', '');
    $condPadV          = number_format((float) $layout['cond_padding_v'], 2, '.', '');
    $condPadH          = number_format((float) $layout['cond_padding_h'], 2, '.', '');

    return '<style>
        body, .pdf-document {
            font-family: "Times New Roman", Times, serif;
            font-size: ' . $bodyFontSize . 'pt;
            line-height: ' . $bodyLineHeight . ';
            color: #1e1e1e;
            text-align: justify;
        }

        p {
            margin: 0 0 ' . $paragraphSpacing . 'pt 0;
            line-height: ' . $paragraphLine . ';
        }

        div {
            margin: 0;
            padding: 0;
        }

        .titulo {
            font-size: ' . $titleFontSize . 'pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: ' . $titleSpacing . 'pt;
            border-bottom: 2px solid #000;
            padding-bottom: 8pt;
            letter-spacing: 0.5pt;
        }

        .secao-titulo {
            font-size: ' . $sectionTitleSize . 'pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: ' . $sectionTitleTop . 'pt 0 ' . $sectionTitleBottom . 'pt 0;
        }

        .dados-interessado {
            margin-bottom: ' . $dataBlockSpacing . 'pt;
            line-height: ' . $dataBlockLine . ';
        }

        .dados-interessado .linha {
            margin-bottom: ' . $dataLineSpacing . 'pt;
        }

        .dados-interessado .label {
            font-weight: bold;
        }

        .texto-parecer {
            margin-bottom: ' . $sectionSpacing . 'pt;
        }

        .texto-parecer p {
            margin: 0 0 ' . $paragraphSpacing . 'pt 0;
            text-indent: ' . $paragraphIndent . 'pt;
            line-height: ' . $paragraphLine . ';
            text-align: justify;
        }

        .data-local {
            margin-top: ' . $dataLocalTop . 'pt;
            margin-bottom: ' . $dataLocalBottom . 'pt;
            text-align: right;
            font-weight: normal;
        }

        .linha-assinatura {
            border-top: 1px solid #000;
            margin-top: ' . $signatureTop . 'pt;
            padding-top: ' . $signaturePad . 'pt;
            text-align: center;
            width: 60%;
            margin-left: auto;
            margin-right: auto;
        }

        .nome-assinante {
            font-weight: bold;
            font-size: 11pt;
        }

        .cargo-assinante {
            font-size: 10pt;
            display: block;
        }

        h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 ' . $paragraphSpacing . 'pt 0;
        }

        h2, h3 {
            font-size: 12pt;
            font-weight: bold;
            margin: 0 0 ' . $paragraphSpacing . 'pt 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 ' . $paragraphSpacing . 'pt 0;
        }

        td, th {
            vertical-align: middle;
            line-height: ' . $bodyLineHeight . ';
            padding: ' . $tableCellVPad . 'pt ' . $tableCellHPad . 'pt;
            font-size: 11pt;
        }

        ul, ol {
            margin: ' . $listSpacing . 'pt 0 ' . $listSpacing . 'pt 0;
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
 * Posiciona os blocos de assinatura digital sem interferir na paginação do conteúdo.
 */
function aplicarBlocosAssinaturaNoPdf(SEMA_PDF $pdf, array $assinantes): void
{
    $pdf->lastPage();
    $pdf->SetAutoPageBreak(false);

    $pw = $pdf->getPageWidth();
    $ph = $pdf->getPageHeight();
    $bH = 13;
    $bY = $ph - 14 - $bH;

    $n = count($assinantes);

    if ($n === 1) {
        $bW = 62;
        $bX = $pw - 15 - $bW;
        _renderBlocoAssinatura($pdf, $assinantes[0], $bX, $bY, $bW, $bH);
        return;
    }

    $bW = ($n <= 2) ? 62 : 55;
    $gap = 4;
    $totalW = $n * $bW + ($n - 1) * $gap;
    $startX = ($pw - $totalW) / 2;

    foreach ($assinantes as $i => $assinante) {
        $bX = $startX + $i * ($bW + $gap);
        _renderBlocoAssinatura($pdf, $assinante, $bX, $bY, $bW, $bH);
    }
}

/**
 * Renderiza um bloco de assinatura digital no PDF.
 */
function _renderBlocoAssinatura(SEMA_PDF $pdf, array $assinante, float $bX, float $bY, float $bW, float $bH): void {
    $nome      = strtoupper($assinante['nome'] ?? '');
    $cargo     = $assinante['cargo'] ?? '';
    $cpf       = $assinante['cpf'] ?? '';
    $data_hora = $assinante['data_hora'] ?? '';

    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($bX, $bY, $bW, $bH, 'F');

    $pdf->SetDrawColor(160, 160, 160);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($bX, $bY, $bW, $bH, 'D');

    $pdf->SetFillColor(220, 220, 220);
    $pdf->Rect($bX, $bY, $bW, 4, 'F');

    $pdf->SetFillColor(50, 50, 50);
    $pdf->Rect($bX + 2, $bY + 1.2, 1.8, 1.8, 'F');

    $pdf->SetFont('helvetica', 'B', 5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($bX + 5, $bY + 0.7);
    $pdf->Cell($bW - 6, 2.8, 'ASSINADO DIGITALMENTE', 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 5.5);
    $pdf->SetXY($bX + 2, $bY + 4.5);
    $pdf->Cell($bW - 4, 2.8, $nome, 0, 0);

    $pdf->SetFont('helvetica', '', 5);
    $linha2 = $cargo;
    if (!empty($cpf)) $linha2 .= '  |  CPF: ' . $cpf;
    $pdf->SetXY($bX + 2, $bY + 7.3);
    $pdf->Cell($bW - 4, 2.5, $linha2, 0, 0);

    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY($bX + 2, $bY + 10);
    $pdf->Cell($bW - 4, 2.5, $data_hora, 0, 0);
}

/**
 * Gera e baixa/salva o PDF assinado.
 *
 * $assinante_ou_assinantes aceita:
 *   - array simples com 'nome' (1 assinante, retrocompatível)
 *   - array indexado de arrays de assinante (múltiplos)
 */
function emitirParecerAssinado($conteudo_html, $assinante_ou_assinantes, $numero_processo, $modo_saida = 'D', $caminho_salvar = null) {

    if (isset($assinante_ou_assinantes['nome'])) {
        $assinantes = [$assinante_ou_assinantes];
    } else {
        $assinantes = array_values((array) $assinante_ou_assinantes);
    }

    $layouts = [
        [
            'body_font_size' => 12.0,
            'body_line_height' => 1.40,
            'paragraph_spacing' => 9.0,
            'paragraph_indent' => 37.5,
            'paragraph_line_height' => 1.70,
            'title_font_size' => 15.0,
            'title_spacing' => 22.0,
            'section_title_font_size' => 11.5,
            'section_title_margin_top' => 14.0,
            'section_title_margin_bottom' => 9.0,
            'section_spacing' => 16.0,
            'data_block_spacing' => 14.0,
            'data_block_line_height' => 1.45,
            'data_line_spacing' => 2.0,
            'data_local_top' => 18.0,
            'data_local_bottom' => 24.0,
            'signature_top' => 18.0,
            'signature_padding' => 6.0,
            'margin_left' => 15.0,
            'margin_top' => 27.0,
            'margin_right' => 15.0,
            'footer_margin' => 12.0,
            'page_break_bottom' => 15.0,
            'cell_height_ratio' => 1.0,
            'table_cell_v_padding' => 3.75,
            'table_cell_h_padding' => 6.0,
            'list_spacing' => 4.0,
            'cond_padding_v' => 6.0,
            'cond_padding_h' => 8.0,
        ],
        [
            'body_font_size' => 11.6,
            'body_line_height' => 1.34,
            'paragraph_spacing' => 6.0,
            'paragraph_indent' => 32.0,
            'paragraph_line_height' => 1.52,
            'title_font_size' => 14.0,
            'title_spacing' => 16.0,
            'section_title_font_size' => 11.0,
            'section_title_margin_top' => 10.0,
            'section_title_margin_bottom' => 6.0,
            'section_spacing' => 10.0,
            'data_block_spacing' => 9.0,
            'data_block_line_height' => 1.30,
            'data_line_spacing' => 1.5,
            'data_local_top' => 12.0,
            'data_local_bottom' => 16.0,
            'signature_top' => 14.0,
            'signature_padding' => 5.0,
            'margin_left' => 14.0,
            'margin_top' => 25.0,
            'margin_right' => 14.0,
            'footer_margin' => 10.0,
            'page_break_bottom' => 12.0,
            'cell_height_ratio' => 0.94,
            'table_cell_v_padding' => 3.0,
            'table_cell_h_padding' => 5.0,
            'list_spacing' => 3.0,
            'cond_padding_v' => 5.0,
            'cond_padding_h' => 7.0,
        ],
        [
            'body_font_size' => 11.2,
            'body_line_height' => 1.28,
            'paragraph_spacing' => 4.0,
            'paragraph_indent' => 28.0,
            'paragraph_line_height' => 1.40,
            'title_font_size' => 13.2,
            'title_spacing' => 12.0,
            'section_title_font_size' => 10.5,
            'section_title_margin_top' => 8.0,
            'section_title_margin_bottom' => 4.0,
            'section_spacing' => 8.0,
            'data_block_spacing' => 7.0,
            'data_block_line_height' => 1.22,
            'data_line_spacing' => 1.0,
            'data_local_top' => 9.0,
            'data_local_bottom' => 12.0,
            'signature_top' => 11.0,
            'signature_padding' => 4.0,
            'margin_left' => 13.0,
            'margin_top' => 23.0,
            'margin_right' => 13.0,
            'footer_margin' => 8.0,
            'page_break_bottom' => 10.0,
            'cell_height_ratio' => 0.88,
            'table_cell_v_padding' => 2.5,
            'table_cell_h_padding' => 4.5,
            'list_spacing' => 2.0,
            'cond_padding_v' => 4.0,
            'cond_padding_h' => 6.0,
        ],
        [
            'body_font_size' => 10.6,
            'body_line_height' => 1.18,
            'paragraph_spacing' => 2.0,
            'paragraph_indent' => 18.0,
            'paragraph_line_height' => 1.20,
            'title_font_size' => 12.4,
            'title_spacing' => 8.0,
            'section_title_font_size' => 9.8,
            'section_title_margin_top' => 5.0,
            'section_title_margin_bottom' => 3.0,
            'section_spacing' => 4.0,
            'data_block_spacing' => 4.0,
            'data_block_line_height' => 1.12,
            'data_line_spacing' => 0.5,
            'data_local_top' => 5.0,
            'data_local_bottom' => 7.0,
            'signature_top' => 8.0,
            'signature_padding' => 3.0,
            'margin_left' => 12.0,
            'margin_top' => 22.0,
            'margin_right' => 12.0,
            'footer_margin' => 7.0,
            'page_break_bottom' => 8.0,
            'cell_height_ratio' => 0.82,
            'table_cell_v_padding' => 2.0,
            'table_cell_h_padding' => 4.0,
            'list_spacing' => 1.0,
            'cond_padding_v' => 3.0,
            'cond_padding_h' => 5.0,
        ],
    ];

    $pdf = null;

    foreach ($layouts as $layout) {
        $pdf = renderizarParecerPdf($conteudo_html, $assinantes, $numero_processo, $layout);
        if ($pdf->getNumPages() <= 1) {
            break;
        }
    }

    aplicarBlocosAssinaturaNoPdf($pdf, $assinantes);

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
