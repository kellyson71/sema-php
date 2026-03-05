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

    // Header Premium
    public function Header() {
        $image_file = dirname(__DIR__, 2) . '/assets/SEMA/PNG/Azul/Logo SEMA Vertical.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 25, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        $this->SetFont('helvetica', 'B', 12);
        $this->SetXY(45, 12);
        $this->SetTextColor(40, 40, 40);
        $this->Cell(0, 6, 'PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN', 0, 1, 'L', 0, '', 0, false, 'M', 'M');
        
        $this->SetFont('helvetica', 'B', 10);
        $this->SetXY(45, 18);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA', 0, 1, 'L', 0, '', 0, false, 'M', 'M');
        
        // Linha divisória fina verde (Estilo SEMA)
        $this->SetLineStyle(array('width' => 0.5, 'color' => array(45, 134, 97))); 
        $this->Line(15, 28, 195, 28);
    }

    // Footer Premium com Carimbo
    public function Footer() {
        // Posição a 3,5 cm da borda inferior para abrigar o carimbo e a página
        $this->SetY(-35);

        // Bloco de Assinatura Desenhado (Carimbo à direita)
        $x = 120; // Posição horizontal do carimbo
        $y = $this->GetY();
        $w = 75;  // Largura do carimbo

        // Fundo e Borda do Carimbo
        $this->SetLineStyle(array('width' => 0.3, 'color' => array(45, 134, 97))); // Borda Verde
        $this->SetFillColor(250, 255, 250); // Fundo levemente esverdeado
        $this->RoundedRect($x, $y, $w, 22, 2, '1111', 'DF');

        // Título do Carimbo
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor(45, 134, 97);
        $this->SetXY($x, $y + 1);
        $this->Cell($w, 4, 'ASSINATURA DIGITAL SEMA', 0, 1, 'C');

        // Nome do Assinante
        $this->SetFont('helvetica', 'B', 8);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($x + 1, $y + 5);
        $nome = (strlen($this->assinante_nome) > 35) ? substr($this->assinante_nome, 0, 32) . '...' : $this->assinante_nome;
        $this->Cell($w - 2, 4, $nome, 0, 1, 'C', 0, '', 1); 

        // Cargo
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(80, 80, 80);
        $this->SetXY($x + 1, $y + 9);
        $this->Cell($w - 2, 3, $this->assinante_cargo, 0, 1, 'C', 0, '', 1);
        
        // CPF e Matrícula (Opcional, se existirem)
        $this->SetXY($x + 1, $y + 12);
        $cpfMatTxt = '';
        if (!empty($this->assinante_cpf)) $cpfMatTxt .= 'CPF: ' . $this->assinante_cpf;
        if (!empty($this->assinante_matricula)) $cpfMatTxt .= ($cpfMatTxt ? ' | ' : '') . 'Mat: ' . $this->assinante_matricula;
        
        if (!empty($cpfMatTxt)) {
            $this->Cell($w - 2, 3, $cpfMatTxt, 0, 1, 'C', 0, '', 1);
        }

        // Data da Assinatura
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->SetXY($x + 1, $y + 16);
        $this->Cell($w - 2, 4, 'Autenticado em: ' . $this->assinante_data, 0, 1, 'C');

        // Paginação (Centro inferior)
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

/**
 * Função para gerar e baixar/exibir o PDF assinado
 */
function emitirParecerAssinado($conteudo_html, $assinante, $numero_processo) {
    
    // PHP 8+: Strings diretas ('P', 'mm', 'A4') dispensam definições globais faltantes do autoload
    $pdf = new SEMA_PDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Injetar dados no footer dinâmico
    $pdf->assinante_nome = strtoupper($assinante['nome'] ?? '');
    $pdf->assinante_cargo = $assinante['cargo'] ?? '';
    $pdf->assinante_data = $assinante['data_hora'] ?? date('d/m/Y H:i:s');
    $pdf->assinante_cpf = $assinante['cpf'] ?? '';
    $pdf->assinante_matricula = $assinante['matricula'] ?? '';

    // Metadados do Arquivo
    $pdf->SetCreator('SEMA Documentos Digitais');
    $pdf->SetAuthor($pdf->assinante_nome);
    $pdf->SetTitle('Parecer Ambiental - ' . $numero_processo);
    $pdf->SetSubject('Parecer Técnico PMMF/SEMA');
    
    // Estrutura Dimensional A4: Maximizando o uso do papel real
    // Left: 15mm, Top: 35mm (Para passar pela linha verde suavemente), Right: 15mm
    $pdf->SetMargins(15, 35, 15); 
    $pdf->SetHeaderMargin(10);
    
    // Margem inferior de 40mm para o auto break ocorrer antes de encostar no Carimbo
    $pdf->SetFooterMargin(40); 
    $pdf->SetAutoPageBreak(TRUE, 40); 
    
    $pdf->setImageScale(1.25);
    
    $pdf->AddPage();
    
    // Título Principal
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 8, 'PARECER TÉCNICO', 0, 1, 'C', 0, '', 0);
    
    // Subtítulo do Processo
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 8, 'Processo N.º ' . $numero_processo, 0, 1, 'C', 0, '', 0);
    $pdf->Ln(6);

    // Tipografia corporativa para o corpo textual (Pequena melhoria visual e ganho de espaço)
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(50, 50, 50);

    // HTML Rendering com alinhamento justificado orgânico
    $html_corpo = '<div style="text-align: justify; line-height: 1.5;">' . $conteudo_html . '</div>';

    $pdf->writeHTML($html_corpo, true, false, true, false, '');

    if (ob_get_length()) {
       ob_clean();
    }

    $nome_arquivo = 'Parecer_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $numero_processo) . '_' . date('Y_m_d_His') . '.pdf';
    
    // Saída: 'D' forçar Diálogo de Download | 'I' exibir inline para conferência visual
    $pdf->Output($nome_arquivo, 'D');
}
