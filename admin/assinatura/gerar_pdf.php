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

    // Footer — Carimbo de Assinatura Digital Profissional
    public function Footer() {
        $this->SetY(-32);
        $y = $this->GetY();

        // ── Linha separadora fina ──────────────────────────────
        $this->SetLineStyle(array('width' => 0.15, 'color' => array(200, 205, 210)));
        $this->Line(15, $y, 195, $y);

        // ── Carimbo de Assinatura (centralizado, largura 90mm) ─
        $w = 90;
        $x = (210 - $w) / 2;
        $yc = $y + 2;

        // Fundo e borda do carimbo
        $this->SetLineStyle(array('width' => 0.3, 'color' => array(45, 134, 97)));
        $this->SetFillColor(248, 252, 249);
        $this->RoundedRect($x, $yc, $w, 20, 1.5, '1111', 'DF');

        // Barra verde no topo do carimbo
        $this->SetFillColor(45, 134, 97);
        $this->RoundedRect($x, $yc, $w, 4, 1.5, '1100', 'F');

        // Título na barra verde
        $this->SetFont('helvetica', 'B', 6.5);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($x, $yc + 0.3);
        $this->Cell($w, 3.5, chr(0xE2).chr(0x9C).chr(0x93) . '  DOCUMENTO ASSINADO DIGITALMENTE  ' . chr(0xE2).chr(0x9C).chr(0x93), 0, 1, 'C');

        // Nome do assinante
        $this->SetFont('helvetica', 'B', 8.5);
        $this->SetTextColor(30, 35, 40);
        $this->SetXY($x + 2, $yc + 5);
        $nome = mb_strimwidth($this->assinante_nome, 0, 45, '...');
        $this->Cell($w - 4, 4, $nome, 0, 1, 'C', 0, '', 1);

        // Cargo
        $this->SetFont('helvetica', '', 6);
        $this->SetTextColor(80, 85, 90);
        $this->SetXY($x + 2, $yc + 9);
        $this->Cell($w - 4, 3, $this->assinante_cargo, 0, 1, 'C', 0, '', 1);

        // CPF | Matrícula
        $cpfMatTxt = '';
        if (!empty($this->assinante_cpf)) $cpfMatTxt .= 'CPF: ' . $this->assinante_cpf;
        if (!empty($this->assinante_matricula)) $cpfMatTxt .= ($cpfMatTxt ? '  |  ' : '') . 'Mat: ' . $this->assinante_matricula;

        if ($cpfMatTxt) {
            $this->SetFont('helvetica', '', 5.5);
            $this->SetTextColor(110, 115, 120);
            $this->SetXY($x + 2, $yc + 12);
            $this->Cell($w - 4, 3, $cpfMatTxt, 0, 1, 'C', 0, '', 1);
        }

        // Data/hora da assinatura
        $this->SetFont('helvetica', 'I', 5.5);
        $this->SetTextColor(130, 135, 140);
        $this->SetXY($x + 2, $yc + 15.5);
        $this->Cell($w - 4, 3, 'Autenticado em ' . $this->assinante_data, 0, 1, 'C');

        // ── Paginação (abaixo do carimbo) ──────────────────────
        $this->SetY(-10);
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(160, 165, 170);
        $this->Cell(0, 10, chr(0xE2).chr(0x80).chr(0x94) . '  Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages() . '  ' . chr(0xE2).chr(0x80).chr(0x94), 0, false, 'C', 0, '', 0, false, 'T', 'M');
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
    
    // Margens super otimizadas
    $pdf->SetMargins(15, 27, 15);
    // Footer maior (carimbo profissional) — reservar 32mm
    $pdf->SetFooterMargin(32);
    $pdf->SetAutoPageBreak(TRUE, 35); 
    
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
    </style>';

    $html_corpo = $css_base . '<div style="text-align: justify; line-height: 1.4;">' . $conteudo_html . '</div>';

    $pdf->writeHTML($html_corpo, true, false, true, false, '');

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
