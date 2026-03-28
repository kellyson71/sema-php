<?php
/**
 * Components.php — Componentes HTML reutilizáveis para documentos
 *
 * Cada método retorna um bloco de HTML com estilos inline + atributos HTML,
 * otimizado para TCPDF e compatível com o editor Summernote.
 *
 * REGRAS TCPDF:
 *   - Usar atributos HTML (width, border, cellpadding, cellspacing, bgcolor)
 *   - Usar <br> para espaçamento em vez de margin
 *   - Usar strtoupper() em vez de text-transform:uppercase
 *   - Evitar CSS moderno (flex, grid, table-layout, box-sizing)
 *
 * Uso:
 *   echo Components::titulo('ALVARÁ DE CONSTRUÇÃO', 'Nº 001/2026');
 *   echo Components::secao('1. IDENTIFICAÇÃO DO PROPRIETÁRIO');
 *   echo Components::tabela([
 *       ['Nome', '{{nome_proprietario}}'],
 *       ['CPF/CNPJ', '{{cpf_cnpj_proprietario}}'],
 *   ]);
 */

require_once __DIR__ . '/Styles.php';

class Components
{
    /**
     * Título principal do documento (table wrapper para largura 100% no TCPDF)
     */
    public static function titulo(string $texto, string $subtexto = ''): string
    {
        $s = DocumentStyles::TITULO;
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="' . $s . '">';
        $html .= strtoupper($texto);
        if ($subtexto) {
            $html .= '<br><span style="font-size:12pt; font-weight:normal;">' . $subtexto . '</span>';
        }
        $html .= '</td></tr></table>';
        $html .= '<br>';
        return $html;
    }

    /**
     * Subtítulo (fundamentação legal, protocolo, etc.)
     */
    public static function subtitulo(string $texto): string
    {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="' . DocumentStyles::SUBTITULO . '">';
        $html .= $texto;
        $html .= '</td></tr></table>';
        $html .= '<br>';
        return $html;
    }

    /**
     * Cabeçalho de seção numerada (table com bgcolor para TCPDF)
     */
    public static function secao(string $texto): string
    {
        $s = DocumentStyles::SECAO_TITULO;
        $html = '<br>';
        $html .= '<table width="100%" cellpadding="4" cellspacing="0" border="1" style="border-color:#aaa;">';
        $html .= '<tr><td bgcolor="#e8e8e8" style="' . $s . '">' . strtoupper($texto) . '</td></tr>';
        $html .= '</table>';
        return $html;
    }

    /**
     * Tabela de dados label/valor
     *
     * Usa atributos HTML (width, border, bgcolor, cellpadding) para máxima
     * compatibilidade com TCPDF. CSS inline apenas para font e padding.
     *
     * @param array $linhas Array de pares [label, valor] ou [label, valor, opções]
     *                      Opções suportadas: 'colspan' => true (valor ocupa toda a largura)
     * @param int   $labelWidth Largura percentual da coluna de labels (padrão: 30)
     */
    public static function tabela(array $linhas, int $labelWidth = 30): string
    {
        $valorWidth = 100 - $labelWidth;
        $sl = DocumentStyles::TD_LABEL;
        $sv = DocumentStyles::TD_VALOR;

        $html = '<table width="100%" border="1" cellpadding="5" cellspacing="0" style="' . DocumentStyles::TABELA . ' border-color:#aaa;">';

        foreach ($linhas as $linha) {
            $label = $linha[0];
            $valor = $linha[1] ?? '';
            $opts  = $linha[2] ?? [];

            if (!empty($opts['colspan'])) {
                $html .= '<tr>';
                $html .= '<td colspan="2" style="' . DocumentStyles::TD_FULL . '">';
                $html .= '<strong>' . $label . '</strong>' . ($valor ? ' ' . $valor : '');
                $html .= '</td></tr>';
            } else {
                $html .= '<tr>';
                $html .= '<td width="' . $labelWidth . '%" bgcolor="#f0f0f0" style="' . $sl . '">' . $label . '</td>';
                $html .= '<td width="' . $valorWidth . '%" style="' . $sv . '">' . $valor . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Bloco de texto corrido (sem indentação)
     */
    public static function texto(string $conteudo): string
    {
        return '<div style="' . DocumentStyles::TEXTO . '">' . $conteudo . '</div>';
    }

    /**
     * Parágrafo com indentação (estilo parecer técnico)
     */
    public static function paragrafo(string $texto): string
    {
        return '<p style="' . DocumentStyles::TEXTO_INDENT . '">' . $texto . '</p>';
    }

    /**
     * Múltiplos parágrafos com indentação
     */
    public static function paragrafos(array $textos): string
    {
        $html = '<div class="texto-parecer">';
        foreach ($textos as $t) {
            $html .= self::paragrafo($t);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Bloco de condicionantes com lista
     */
    public static function condicionantes(array $itens, string $titulo = 'CONDICIONANTES:'): string
    {
        $html = '<br>';
        $html .= '<div class="condicionantes" style="' . DocumentStyles::CONDICIONANTES . '">';
        $html .= '<strong>' . $titulo . '</strong><ul style="padding-left:20px;">';
        foreach ($itens as $item) {
            $html .= '<li style="line-height:1.4;">' . $item . '</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    /**
     * Data e local
     */
    public static function dataLocal(string $data = '{{data_atual}}'): string
    {
        return '<br><br><div style="' . DocumentStyles::DATA_LOCAL . '">Pau dos Ferros/RN, ' . $data . '.</div>';
    }

    /**
     * Bloco de assinatura
     */
    public static function assinatura(string $nome = 'VICENTE DE PAULA FERNANDES', string $cargo = 'SECRETÁRIO MUNICIPAL DE MEIO AMBIENTE – SEMA.<br>PORTARIA 010/2025'): string
    {
        $html  = '<br><br><br>';
        $html .= '<div style="' . DocumentStyles::ASSINATURA . '">';
        $html .= '<p style="' . DocumentStyles::ASSINATURA_NOME . '">' . strtoupper($nome) . '</p>';
        $html .= '<p style="' . DocumentStyles::ASSINATURA_CARGO . '">' . $cargo . '</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Dados do interessado em formato label: valor (sem tabela)
     */
    public static function dadosInline(array $dados): string
    {
        $html = '<div style="line-height:1.8;">';
        foreach ($dados as [$label, $valor]) {
            $html .= '<div><span style="font-weight:bold;">' . $label . ':</span> ' . $valor . '</div>';
        }
        $html .= '</div><br>';
        return $html;
    }
}
