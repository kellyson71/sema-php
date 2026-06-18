<?php
/**
 * Sanitização de HTML do editor antes da renderização no TCPDF.
 * Compartilhado entre processa_assinatura.php e preview_pdf.php para que
 * o preview seja gerado EXATAMENTE como o documento final.
 */
require_once __DIR__ . '/parecer_service.php';

/**
 * Remove CSS que causa overlays visuais (tarjas) no TCPDF:
 *  - position:absolute/fixed/relative → TCPDF renderiza como bloco sobreposto
 *  - background-color em <span> → TCPDF preenche retângulo colorido sobre o texto
 *  - z-index, overflow → sem efeito no TCPDF mas podem confundir o parser
 *  - elementos de UI do editor (page-gap, page-break-indicator)
 */
function sanitizarHtmlParaPdf(string $html): string {
    // 1. Strip spans var-field (highlight do editor)
    $html = ParecerService::stripVarSpans($html);

    // 2. Remove elementos visuais do editor (separadores de página)
    $html = preg_replace('/<div[^>]*class="[^"]*page-(?:cut|gap|break-indicator)[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $html);

    // 3. Remove position absolute/fixed/relative de qualquer style inline
    $html = preg_replace('/\bposition\s*:\s*(absolute|fixed|relative|sticky)\b\s*[;]?/i', '', $html);

    // 4. Remove z-index e overflow
    $html = preg_replace('/\b(z-index|overflow(-[xy])?)\s*:\s*[^;\"]+[;]?/i', '', $html);

    // 5. Remove background-color e color de <span> (Summernote adiciona ao selecionar)
    //    Mantém background em <td>, <th>, <table> pois são usados para layout
    $html = preg_replace_callback(
        '/<span(\s[^>]*)>/i',
        function ($m) {
            $attrs = preg_replace('/\bbackground(-color)?\s*:\s*[^;\"]+[;]?/i', '', $m[1]);
            $attrs = preg_replace('/\bcolor\s*:\s*(#[0-9a-fA-F]{3,8}|rgb[a]?\([^)]+\)|[a-z]+)\s*[;]?/i', '', $attrs);
            $attrs = preg_replace('/\bstyle\s*=\s*["\'][\s;]*["\']/', '', $attrs);
            return '<span' . $attrs . '>';
        },
        $html
    );

    // 6. Remove display:none (evita blocos invisíveis que TCPDF renderiza)
    $html = preg_replace('/\bdisplay\s*:\s*none\b\s*[;]?/i', '', $html);

    return $html;
}
