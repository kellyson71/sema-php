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

    $html = preg_replace('/\s+id=("|\')(documento|conteudo|fundo-imagem)\1/i', '', $html);
    $html = preg_replace('/<div[^>]+class="page-break-indicator"[^>]*><\/div>/i', '', $html);
    $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
    // NÃO remover <p> e <div> vazios — eles são respiros intencionais entre blocos
    // e garantem que o espaçamento do PDF fique fiel ao preview/editor.

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
            padding: ' . $tableCellVPad . 'pt ' . $tableCellHPad . 'pt;
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

    log_debug_pdf('final_pdf_html', $htmlCorpo);

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
