<?php

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
}
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Gera e emite/salva o PDF assinado usando mPDF.
 */
function emitirParecerAssinado(string $conteudo_html, array $assinante, string $numero_processo, string $modo_saida = 'D', ?string $caminho_salvar = null): ?string {

    $image_file = dirname(__DIR__, 2) . '/assets/SEMA/PNG/Azul/Logo SEMA Vertical.png';

    $mpdf = new Mpdf([
        'mode'           => 'utf-8',
        'format'         => 'A4',
        'margin_left'    => 15,
        'margin_right'   => 15,
        'margin_top'     => 27,
        'margin_bottom'  => 15,
        'margin_header'  => 5,
        'margin_footer'  => 5,
        'default_font_size' => 12,
        'default_font'   => 'dejavuserif',
        'tempDir'        => sys_get_temp_dir(),
    ]);

    $mpdf->SetCreator('SEMA Documentos Digitais');
    $mpdf->SetAuthor(strtoupper($assinante['nome'] ?? ''));
    $mpdf->SetTitle('Parecer Ambiental - ' . $numero_processo);

    // Marca d'água
    if (file_exists($image_file)) {
        $mpdf->SetWatermarkImage($image_file, 0.06, [100, 100]);
        $mpdf->showWatermarkImage = true;
    }

    // Header HTML
    $logo_tag = '';
    if (file_exists($image_file)) {
        $logo_b64 = base64_encode(file_get_contents($image_file));
        $logo_tag = '<img src="data:image/png;base64,' . $logo_b64 . '" style="width:17mm; vertical-align:middle;">';
    }
    $header_html = '
    <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom: 0.3mm solid #2d8661;">
        <tr>
            <td width="20mm">' . $logo_tag . '</td>
            <td style="vertical-align:middle; padding-left:3mm;">
                <span style="font-family:helvetica; font-size:10pt; font-weight:bold; color:#282828;">PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</span><br>
                <span style="font-family:helvetica; font-size:8pt; font-weight:bold; color:#646464;">SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA</span>
            </td>
        </tr>
    </table>';
    $mpdf->SetHTMLHeader($header_html);

    // Footer HTML
    $footer_html = '
    <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 0.1mm solid #d2d2d2;">
        <tr>
            <td style="font-family:helvetica; font-size:6pt; color:#b4b4b4; text-align:center; padding-top:1mm;">
                Pág. {PAGENO} / {nbpg} &nbsp;—&nbsp; Prefeitura Municipal de Pau dos Ferros/RN — SEMA
            </td>
        </tr>
    </table>';
    $mpdf->SetHTMLFooter($footer_html);

    // Dados do assinante
    $nome      = strtoupper($assinante['nome'] ?? '');
    $cargo     = $assinante['cargo'] ?? '';
    $cpf       = $assinante['cpf'] ?? '';
    $data_hora = $assinante['data_hora'] ?? date('d/m/Y \à\s H:i:s');
    $linha2    = $cargo . (!empty($cpf) ? '  |  CPF: ' . $cpf : '');

    // Carimbo de assinatura — fluxo normal no final do conteúdo (última página)
    $stamp = '
    <div style="width:62mm; border:0.25mm solid #a0a0a0; font-family:helvetica; font-size:5pt; margin-left:auto; margin-top:6mm;">
        <div style="background:#dcdcdc; padding:1mm 2mm;">
            <strong>&#9632; ASSINADO DIGITALMENTE</strong>
        </div>
        <div style="padding:1mm 2mm; line-height:1.6;">
            <strong style="font-size:5.5pt;">' . htmlspecialchars($nome) . '</strong><br>
            ' . htmlspecialchars($linha2) . '<br>
            <span style="color:#505050;">' . htmlspecialchars($data_hora) . '</span>
        </div>
    </div>';

    $css = '
        body { font-family: dejavuserif; font-size: 12pt; line-height: 1.4; }
        p { margin-top: 0pt; margin-bottom: 4pt; line-height: 1.4; }
        h1 { font-size: 13pt; font-weight: bold; margin-top: 6pt; margin-bottom: 4pt; }
        h2 { font-size: 12pt; font-weight: bold; margin-top: 5pt; margin-bottom: 3pt; }
        h3 { font-size: 12pt; font-weight: bold; margin-top: 4pt; margin-bottom: 2pt; }
        table { border-collapse: collapse; }
        td, th { vertical-align: middle; line-height: 1.4; }
        .texto-parecer p { margin-bottom: 4pt; text-indent: 25pt; line-height: 1.45; }
        .data-local { text-align: right; }
        .linha-assinatura { text-align: center; padding-top: 3pt; }
        .condicionantes { font-size: 9pt; border: 1px solid #000; padding: 6pt 8pt; }
        ul, ol { margin-top: 2pt; margin-bottom: 5pt; }
        li { margin-bottom: 2pt; line-height: 1.35; }
        .var-field { color: #1e1e1e !important; background: transparent !important;
                     font-weight: bold !important; text-decoration: none !important; }
    ';

    $html_final = '<style>' . $css . '</style>
    <div style="text-align: justify; line-height: 1.4;">'
        . $conteudo_html
        . $stamp
    . '</div>';

    $mpdf->WriteHTML($html_final);

    $nome_arquivo = 'Parecer_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $numero_processo) . '_' . date('His') . '.pdf';

    if ($modo_saida === 'F' && $caminho_salvar) {
        $mpdf->Output($caminho_salvar, Destination::FILE);
        return $nome_arquivo;
    }

    if (ob_get_length()) {
        ob_clean();
    }

    $dest = ($modo_saida === 'D') ? Destination::DOWNLOAD : Destination::INLINE;
    $mpdf->Output($nome_arquivo, $dest);
    return null;
}
