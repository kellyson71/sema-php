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

    // Footer Premium com Carimbo Ultramoderno e Pequeno
    public function Footer() {
        $this->SetY(-25); // Posição do rodapé (25mm do fundo)

        // Bloco de Assinatura Desenhado (Canto inferior direito)
        $w = 60;  // Largura do carimbo
        $x = 210 - 15 - $w; 
        $y = $this->GetY();
        
        // Borda do Carimbo ultra fina e cinza suave
        $this->SetLineStyle(array('width' => 0.1, 'color' => array(200, 200, 200))); 
        $this->SetFillColor(253, 255, 253); 
        $this->RoundedRect($x, $y, $w, 15, 1, '1111', 'DF'); 

        // Título do Carimbo
        $this->SetFont('helvetica', 'B', 5);
        $this->SetTextColor(45, 134, 97);
        $this->SetXY($x, $y + 0.5);
        $this->Cell($w, 3, 'ASSINATURA ELETRÔNICA SEMA', 0, 1, 'C');

        // Nome do Assinante
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($x + 1, $y + 3);
        $nome = (strlen($this->assinante_nome) > 35) ? substr($this->assinante_nome, 0, 32) . '...' : $this->assinante_nome;
        $this->Cell($w - 2, 3, $nome, 0, 1, 'C', 0, '', 1); 

        // Cargo e Matrícula/CPF
        $this->SetFont('helvetica', '', 5);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY($x + 1, $y + 6);
        $this->Cell($w - 2, 2.5, $this->assinante_cargo, 0, 1, 'C', 0, '', 1);
        
        $cpfMatTxt = '';
        if (!empty($this->assinante_cpf)) $cpfMatTxt .= 'CPF: ' . $this->assinante_cpf;
        if (!empty($this->assinante_matricula)) $cpfMatTxt .= ($cpfMatTxt ? ' | ' : '') . 'Mat: ' . $this->assinante_matricula;
        
        $this->SetXY($x + 1, $y + 8.5);
        $this->Cell($w - 2, 2.5, $cpfMatTxt, 0, 1, 'C', 0, '', 1);

        // Data da Assinatura
        $this->SetFont('helvetica', 'I', 5);
        $this->SetTextColor(150, 150, 150);
        $this->SetXY($x + 1, $y + 11);
        $this->Cell($w - 2, 3, 'Autenticado em: ' . $this->assinante_data, 0, 1, 'C');

        // Paginação
        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 6);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
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
    // É obrigatório SetFooterMargin para o TCPDF saber o espaço inferior reservado ao rodapé, senão ele esmaga o rodapé.
    $pdf->SetFooterMargin(25);
    $pdf->SetAutoPageBreak(TRUE, 28); 
    
    // Suavizando o VSpace para evitar "espaço duplo" mantendo as propriedades de quebra de bloco
    // Arrays para UL e LI foram removidos para evitar quebra em listas (ficando no padrão do TCPDF)
    // Para P e DIV, usar uma fração minúscula de margin vertical (0.01) ao invés do 0 absoluto
    $pdf->setHtmlVSpace(array(
        'p' => array(0 => array('h' => 0.01, 'n' => 1), 1 => array('h' => 0.01, 'n' => 1)),
        'div' => array(0 => array('h' => 0.01, 'n' => 1), 1 => array('h' => 0.01, 'n' => 1))
    ));
    
    $pdf->AddPage();
    
    // Conteúdo (Título do Documento)
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 7, 'PARECER TÉCNICO', 0, 1, 'C', 0, '', 0);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Processo N.º ' . $numero_processo, 0, 1, 'C', 0, '', 0);
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(50, 50, 50);

    // HTML Rendering com line-height compacto
    $html_corpo = '<div style="text-align: justify; line-height: 1.2;">' . $conteudo_html . '</div>';

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
