<?php

if (!defined('BASE_URL')) {
    // Caso seja chamado diretamente sem autoload/configurações, tentar instanciar o config do SEM
    require_once dirname(__DIR__, 2) . '/includes/config.php';
}
// Ajustar path do autoload baseando-se na raiz
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Estendendo TCPDF para criar Header/Footer personalizados
class SEMA_PDF extends TCPDF {
    
    public $assinante_nome = '';
    public $assinante_cargo = '';
    public $assinante_data = '';

    // Header Personalizado
    public function Header() {
        // Obter URL base ou path absoluto da logo
        // Assumindo que a logo está em assets/SEMA/PNG/Azul/Logo SEMA Vertical.png
        $image_file = dirname(__DIR__, 2) . '/assets/SEMA/PNG/Azul/Logo SEMA Vertical.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Configurar a fonte
        $this->SetFont('helvetica', 'B', 14);
        
        // Título alinhado ao centro e um pouco pra direita devido a logo
        $this->SetXY(50, 15);
        $this->Cell(0, 10, 'PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN', 0, 1, 'L', 0, '', 0, false, 'M', 'M');
        
        $this->SetFont('helvetica', 'B', 12);
        $this->SetXY(50, 22);
        $this->Cell(0, 10, 'SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA', 0, 1, 'L', 0, '', 0, false, 'M', 'M');
        
        // Linha divisória
        $this->Line(15, 35, 195, 35);
    }

    // Footer Personalizado (Antes estava fora da classe)
    public function Footer() {
        // Posição a 3,5 cm da borda inferior
        $this->SetY(-35);

        // Configurar a exibição da assinatura no canto inferior direito
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5, 'Documento assinado digitalmente nos termos da lei.', 0, 1, 'R', 0, '', 0, false, 'T', 'M');

        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 5, $this->assinante_nome, 0, 1, 'R', 0, '', 0, false, 'T', 'M');
        
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, $this->assinante_cargo, 0, 1, 'R', 0, '', 0, false, 'T', 'M');
        
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5, 'Data/Hora: ' . $this->assinante_data, 0, 1, 'R', 0, '', 0, false, 'T', 'M');

        // Número da página a 1,5 cm da borda inferior no centro
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

/**
 * Função para gerar e baixar/exibir o PDF assinado
 */
function emitirParecerAssinado($conteudo_html, $assinante, $numero_processo) {
    
    // Instanciar o PDF
    $pdf = new SEMA_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Preencher as infos para rodapé
    $pdf->assinante_nome = mb_strtoupper($assinante['nome'], 'UTF-8');
    $pdf->assinante_cargo = $assinante['cargo'];
    $pdf->assinante_data = $assinante['data_hora'];

    // Definições do documento
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdf->assinante_nome);
    $pdf->SetTitle('Parecer Ambiental - ' . $numero_processo);
    $pdf->SetSubject('Parecer Técnico SEMA');
    
    // Margens e Header/Footer
    $pdf->SetMargins(15, 45, 15); // Esquerda, Topo (abrir pra caber o Header), Direita
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(40); // Espaço grande pro bloco de assinatura no Footer

    // Quebra de página automática
    $pdf->SetAutoPageBreak(TRUE, 45); // Quebrar página antes de tocar na assinatura
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Fontes
    $pdf->SetFont('helvetica', '', 12);
    
    // Adiciona Página
    $pdf->AddPage();
    
    // Título do documento
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'PARECER TÉCNICO', 0, 1, 'C', 0, '', 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Processo N.º ' . $numero_processo, 0, 1, 'C', 0, '', 0);
    $pdf->Ln(5);

    // Corpo (Processa formatação e espaçamento HTML do conteúdo que veio do form)
    // Opcionalmente podemos tratar nl2br, porém vamos assumir html básico
    $conteudo = nl2br(htmlspecialchars($conteudo_html));
    $html_corpo = <<<EOF
    <div style="text-align: justify; line-height: 1.6;">
        {$conteudo}
    </div>
EOF;

    $pdf->writeHTML($html_corpo, true, false, true, false, '');

    // Limpar o buffer antes de enviar cabeçalhos
    if (ob_get_length()) {
       ob_clean();
    }

    // Saída final: 'D' força o download imediato do PDF ('I' exibe in-browser)
    $nome_arquivo = 'Parecer_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $numero_processo) . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output($nome_arquivo, 'D');
}
