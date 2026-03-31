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
 * Função para gerar e baixar/exibir o PDF assinado
 */
function emitirParecerAssinado($conteudo_html, $assinante, $numero_processo, $modo_saida = 'D', $caminho_salvar = null) {
    
    $pdf = new SEMA_PDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf->assinante_nome = strtoupper($assinante['nome'] ?? '');
    $pdf->assinante_cargo = $assinante['cargo'] ?? '';
    $pdf->assinante_data = $assinante['data_hora'] ?? date('d/m/Y H:i:s');
    $pdf->assinante_cpf = $assinante['cpf'] ?? '';
    $pdf->assinante_matricula = $assinante['matricula'] ?? '';

    $pdf->SetCreator('SEMA Documentos Digitais');
    $pdf->SetAuthor($pdf->assinante_nome);
    $pdf->SetTitle('Parecer Ambiental - ' . $numero_processo);

    // Proteger documento — leitura, impressão e cópia de texto permitidas; edição bloqueada
    $pdf->SetProtection(
        array('print', 'print-high', 'copy'), // impressão em alta qualidade + cópia de texto
        '',                                   // sem senha para abrir
        hash('sha256', $numero_processo . $assinante['nome'] . date('Y')),
        1,                                    // RC4 40-bit — mais compatível com leitores variados
        null
    );

    // Margens super otimizadas
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    $pdf->AddPage();

    $pdf->SetFont('times', '', 12);
    $pdf->SetTextColor(30, 30, 30);

    // CSS compatível com TCPDF — mínimo necessário, pois Components.php usa atributos HTML
    $css_base = '<style>
        body { font-family: "times"; font-size: 12pt; line-height: 1.4; }
        p { margin-top: 0pt; margin-bottom: 4pt; line-height: 1.4; }

        /* Títulos */
        h1 { font-size: 13pt; font-weight: bold; margin-top: 6pt; margin-bottom: 4pt; }
        h2 { font-size: 12pt; font-weight: bold; margin-top: 5pt; margin-bottom: 3pt; }
        h3 { font-size: 12pt; font-weight: bold; margin-top: 4pt; margin-bottom: 2pt; }

        /* Tabelas — atributos HTML (width, border, cellpadding, bgcolor) fazem o trabalho pesado */
        table { border-collapse: collapse; }
        td, th { vertical-align: middle; line-height: 1.4; }

        /* Texto e parágrafos */
        .texto-parecer p { margin-bottom: 4pt; text-indent: 25pt; line-height: 1.45; }
        .data-local { text-align: right; }
        .linha-assinatura { text-align: center; padding-top: 3pt; }

        /* Condicionantes */
        .condicionantes { font-size: 9pt; border: 1px solid #000; padding: 6pt 8pt; }

        /* Listas */
        ul, ol { margin-top: 2pt; margin-bottom: 5pt; }
        li { margin-bottom: 2pt; line-height: 1.35; }

        /* Segurança: anula highlight de var-field caso chegue aqui */
        .var-field { color: inherit !important; background: transparent !important;
                     font-weight: inherit !important; text-decoration: none !important; }
    </style>';

    $html_corpo = $css_base . '<div style="text-align: justify; line-height: 1.4;">' . $conteudo_html . '</div>';

    $pdf->writeHTML($html_corpo, true, false, true, false, '');

    // Bloco de assinatura digital — desenhado com TCPDF nativo (não afeta paginação)
    $pdf->lastPage();
    $pw     = $pdf->getPageWidth();   // 210
    $bW     = 65;
    $bH     = 15;
    $bX     = $pw - 15 - $bW;        // alinhado à direita, margem 15mm
    $bY     = $pdf->getPageHeight() - 12 - $bH - 2;  // acima do footer

    // Borda externa
    $pdf->SetDrawColor(160, 160, 160);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($bX, $bY, $bW, $bH, 'D');

    // Faixa de cabeçalho cinza claro
    $pdf->SetFillColor(225, 225, 225);
    $pdf->SetDrawColor(160, 160, 160);
    $pdf->Rect($bX, $bY, $bW, 4.5, 'FD');

    // Marcador quadrado preenchido (substitui ícone unicode)
    $pdf->SetFillColor(50, 50, 50);
    $pdf->Rect($bX + 2, $bY + 1.3, 2, 2, 'F');

    // Texto do cabeçalho
    $pdf->SetFont('helvetica', 'B', 5.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($bX + 5.5, $bY + 0.8);
    $pdf->Cell($bW - 7, 3, 'ASSINADO DIGITALMENTE', 0, 0, 'L');

    // Nome
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetXY($bX + 2, $bY + 5.2);
    $pdf->Cell($bW - 4, 3.5, $pdf->assinante_nome, 0, 0);

    // Cargo + CPF
    $pdf->SetFont('helvetica', '', 5.5);
    $linha2 = $pdf->assinante_cargo;
    if (!empty($pdf->assinante_cpf)) $linha2 .= '  |  CPF: ' . $pdf->assinante_cpf;
    $pdf->SetXY($bX + 2, $bY + 8.8);
    $pdf->Cell($bW - 4, 3, $linha2, 0, 0);

    // Data
    $pdf->SetFont('helvetica', '', 5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY($bX + 2, $bY + 11.8);
    $pdf->Cell($bW - 4, 3, $pdf->assinante_data, 0, 0);

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
