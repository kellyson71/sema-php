<?php
/**
 * Components.php — Componentes HTML reutilizáveis para documentos
 *
 * Cada método retorna um bloco de HTML com estilos inline,
 * compatível com TCPDF e com o editor Summernote.
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
     * Título principal do documento
     */
    public static function titulo(string $texto, string $subtexto = ''): string
    {
        $s = DocumentStyles::TITULO;
        $html = '<div style="' . $s . '">' . $texto;
        if ($subtexto) {
            $html .= '<br><span style="font-size:12pt; font-weight:normal;">' . $subtexto . '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Subtítulo (fundamentação legal, protocolo, etc.)
     */
    public static function subtitulo(string $texto): string
    {
        return '<div style="' . DocumentStyles::SUBTITULO . '">' . $texto . '</div>';
    }

    /**
     * Cabeçalho de seção numerada
     */
    public static function secao(string $texto): string
    {
        return '<div class="secao-titulo" style="' . DocumentStyles::SECAO_TITULO . '">' . $texto . '</div>';
    }

    /**
     * Tabela de dados label/valor
     *
     * @param array $linhas Array de pares [label, valor] ou [label, valor, opções]
     *                      Opções suportadas: 'colspan' => true (valor ocupa toda a largura)
     * @param int   $labelWidth Largura percentual da coluna de labels (padrão: 30)
     */
    public static function tabela(array $linhas, int $labelWidth = 30): string
    {
        $s = DocumentStyles::TABELA;
        $sl = DocumentStyles::TD_LABEL;
        $sv = DocumentStyles::TD_VALOR;

        $html = '<table class="tabela-dados" width="100%" cellpadding="6" cellspacing="0" style="' . $s . '">';

        foreach ($linhas as $linha) {
            $label = $linha[0];
            $valor = $linha[1] ?? '';
            $opts  = $linha[2] ?? [];

            if (!empty($opts['colspan'])) {
                // Linha com valor ocupando toda a largura
                $html .= '<tr>';
                $html .= '<td colspan="2" style="' . DocumentStyles::TD_FULL . '">';
                $html .= '<strong>' . $label . '</strong>' . ($valor ? ' ' . $valor : '');
                $html .= '</td></tr>';
            } else {
                $html .= '<tr>';
                $html .= '<td class="label" width="' . $labelWidth . '%" style="' . $sl . ' width:' . $labelWidth . '%;">' . $label . '</td>';
                $html .= '<td style="' . $sv . '">' . $valor . '</td>';
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
        $html = '<div class="condicionantes" style="' . DocumentStyles::CONDICIONANTES . '">';
        $html .= '<strong>' . $titulo . '</strong><ul style="margin:5px 0; padding-left:20px;">';
        foreach ($itens as $item) {
            $html .= '<li style="margin-bottom:3px; line-height:1.4;">' . $item . '</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    /**
     * Data e local
     */
    public static function dataLocal(string $data = '{{data_atual}}'): string
    {
        return '<div class="data-local" style="' . DocumentStyles::DATA_LOCAL . '">Pau dos Ferros/RN, ' . $data . '.</div>';
    }

    /**
     * Bloco de assinatura
     */
    public static function assinatura(string $nome = 'VICENTE DE PAULA FERNANDES', string $cargo = 'SECRETÁRIO MUNICIPAL DE MEIO AMBIENTE – SEMA.<br>PORTARIA 010/2025'): string
    {
        $html  = '<div class="linha-assinatura" style="' . DocumentStyles::ASSINATURA . '">';
        $html .= '<p class="nome-assinante" style="' . DocumentStyles::ASSINATURA_NOME . '">' . $nome . '</p>';
        $html .= '<p class="cargo-assinante" style="' . DocumentStyles::ASSINATURA_CARGO . '">' . $cargo . '</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Dados do interessado em formato label: valor (sem tabela)
     */
    public static function dadosInline(array $dados): string
    {
        $html = '<div style="margin-bottom:20px; line-height:1.8;">';
        foreach ($dados as [$label, $valor]) {
            $html .= '<div style="margin-bottom:4px;"><span style="font-weight:bold;">' . $label . ':</span> ' . $valor . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
