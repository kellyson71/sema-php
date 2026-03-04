<?php
// Certifique-se de que este script seja chamado a partir do processa_assinatura.php
if (!isset($requerimento_id) || !isset($conteudo_documento)) {
    die("Acesso direto a este arquivo não é permitido.");
}

// Carregar TCPDF
require_once '../../includes/tcpdf/tcpdf.php';

// Estendendo TCPDF para criar Header/Footer customizados
class MYPDF extends TCPDF {
    // Cabeçalho
    public function Header() {
        // Logo (Ajuste o caminho e medidas conforme a sua logo real)
        $image_file = '../../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png';
        if(file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 50, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        $this->SetFont('helvetica', 'B', 12);
        // Título centralizado
        $this->SetY(15);
        $this->Cell(0, 15, 'Secretaria Municipal de Meio Ambiente', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->SetFont('helvetica', 'I', 10);
        $this->SetY(22);
        $this->Cell(0, 15, 'Prefeitura de Pau dos Ferros / RN', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Linha verde separadora
        $this->SetDrawColor(0, 152, 81); // #009851
        $this->Line(15, 32, 195, 32);
    }

    // Rodapé
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128,128,128);
        // Número da página
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buscar dados completos
$stmtR = $pdo->prepare("SELECT * FROM requerimentos WHERE id = ?");
$stmtR->execute([$requerimento_id]);
$req = $stmtR->fetch();

$nome_requerente = $req['requerente_nome'] ?? 'Desconhecido';
$protocolo = $req['protocolo'] ?? 'N/A';
$data_hoje = date('d/m/Y H:i:s');
$admin_nome = $_SESSION['admin_nome_completo'] ?? $_SESSION['admin_nome'];
$admin_cargo = $_SESSION['admin_cargo'] ?? 'Analista Técnico';
$admin_matricula = $_SESSION['admin_matricula_portaria'] ?? '';

// Configurando o documento PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($admin_nome);
$pdf->SetTitle('Parecer Técnico - ' . $protocolo);
$pdf->SetSubject('Assinatura Digital SEMA');
$pdf->SetKeywords('SEMA, Parecer, Assinatura, ' . $protocolo);

// Configurar margens: Header e Footer são processados automaticamente
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Definir auto page break
$pdf->SetAutoPageBreak(TRUE, 40); // 40mm no rodapé para garantir espaço para assinatura

// Adicionar uma página
$pdf->AddPage();

// Conteúdo Redigido //
$pdf->SetFont('helvetica', '', 11);

// Vamos limpar qualquer HTML perigoso ou apenas usar formatação nativa
// TCPDF suporta subconjunto básico de HTML (p, b, i, u, h1-h6, table, etc)
$html_content = '
<h3 style="text-align: center;">PARECER TÉCNICO / INFORMATIVO</h3>
<p><strong>Protocolo:</strong> ' . htmlspecialchars($protocolo) . '<br>
<strong>Requerente:</strong> ' . htmlspecialchars($nome_requerente) . '<br>
<strong>Data/Hora de Emissão:</strong> ' . $data_hoje . '</p>
<hr>
<br>
' . nl2br($conteudo_documento);

// Imprime o texto
$pdf->writeHTML($html_content, true, false, true, false, '');

// ========= BLOCO DE ASSINATURA =========
// Vamos forçar o bloco ir para o final da página ou dar uma quebra se não couber
$y_atual = $pdf->GetY();
$espaco_necessario = 40; // Aproximadamente o tamanho da caixa de assinatura
if (($pdf->getPageHeight() - $y_atual - $pdf->getBreakMargin()) < $espaco_necessario) {
    $pdf->AddPage();
}

$pdf->SetY(-55); // Posiciona a 55mm de baixo pra cima

// Configuração da caixa de assinatura fixada à direita
$box_width = 85; // Largura da caixa
$box_x = $pdf->getPageWidth() - $box_width - 15; // Alinhado à direita com 15mm de margem

$pdf->SetDrawColor(0, 152, 81); // Verde SEMA
$pdf->SetLineWidth(0.5);
$pdf->SetFillColor(245, 255, 250); // Verde bem clarinho de fundo

// Desenha a caixa
$pdf->Rect($box_x, $pdf->GetY(), $box_width, 35, 'DF');

// Texto de Assinatura
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 100, 50);
$pdf->SetXY($box_x + 5, $pdf->GetY() + 5);
$pdf->Cell($box_width - 10, 6, 'ASSINADO DIGITALMENTE POR:', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX($box_x + 5);
$pdf->Cell($box_width - 10, 6, mb_strtoupper($admin_nome, 'UTF-8'), 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX($box_x + 5);
$pdf->Cell($box_width - 10, 5, 'Cargo: ' . $admin_cargo, 0, 1, 'L');

if (!empty($admin_matricula)) {
    $pdf->SetX($box_x + 5);
    $pdf->Cell($box_width - 10, 5, 'Matrícula/Portaria: ' . $admin_matricula, 0, 1, 'L');
}

$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetX($box_x + 5);
$pdf->Cell($box_width - 10, 5, 'Em: ' . $data_hoje, 0, 1, 'L');

// Envia o PDF para o navegador forçando o Download (D)
$nome_arquivo = 'Parecer_' . preg_replace('/[^a-zA-Z0-9]/', '', $protocolo) . '_' . date('Ymd_His') . '.pdf';
// Limpar qualquer buffer que possa corromper o PDF
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($nome_arquivo, 'D');
exit;
