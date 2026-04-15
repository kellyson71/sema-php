<?php
/**
 * Styles.php — Estilos inline centralizados para TCPDF
 *
 * O Summernote remove blocos <style>, então os templates precisam de inline styles.
 * TCPDF tem suporte limitado a CSS, então usamos atributos HTML + inline styles.
 *
 * REGRAS:
 *   - width, bgcolor, border → atributos HTML nos Components (não aqui)
 *   - Aqui ficam apenas: font, padding, text-align, line-height
 *   - Evitar margin (TCPDF ignora parcialmente) — usar <br> para espaçamento
 *   - Evitar text-transform — usar strtoupper() no PHP
 */

class DocumentStyles
{
    public const BASE_CSS = <<<'CSS'
<style>
    body {
        font-family: "Times New Roman", Times, serif;
        font-size: 12pt;
        line-height: 1.6;
        color: #000;
    }
    .titulo, .titulo-licenca {
        font-size: 15pt;
        font-weight: bold;
        text-align: center;
        margin-bottom: 24px;
        text-transform: uppercase;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        letter-spacing: 1px;
    }
    .subtitulo-doc {
        font-size: 10pt;
        text-align: center;
        font-style: italic;
        margin-bottom: 20px;
        color: #555;
    }
    .dados-interessado {
        margin-bottom: 24px;
        line-height: 1.8;
    }
    .dados-interessado .linha {
        margin-bottom: 4px;
    }
    .dados-interessado .label {
        font-weight: bold;
    }
    .secao-titulo {
        font-weight: bold;
        margin: 20px 0 12px 0;
        text-transform: uppercase;
    }
    .texto-parecer {
        margin-bottom: 30px;
    }
    .texto-parecer p {
        margin-bottom: 18px;
        text-indent: 2.5cm;
        line-height: 1.7;
        text-align: justify;
        font-size: 12pt;
    }
    .data-local {
        margin-top: 40px;
        margin-bottom: 60px;
        text-align: right;
        font-weight: normal;
    }
    .linha-assinatura {
        border-top: 1px solid #000;
        margin-top: 40px;
        padding-top: 8px;
        text-align: center;
        width: 60%;
        margin-left: auto;
        margin-right: auto;
    }
    .nome-assinante {
        font-weight: bold;
        font-size: 11pt;
        text-transform: uppercase;
    }
    .cargo-assinante {
        font-size: 10pt;
        display: block;
    }
    .condicionantes {
        font-size: 9pt;
        border: 1px solid #000;
        padding: 8px 10px;
    }
</style>
CSS;

    // ── Título do documento ──────────────────────────────────
    const TITULO = 'font-family:Times New Roman,Times,serif; font-size:15pt; font-weight:bold; text-align:center; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:24px; letter-spacing:1px;';

    // ── Subtítulo (fundamentação legal, protocolo) ───────────
    const SUBTITULO = 'font-size:10pt; text-align:center; font-style:italic; margin-bottom:20px; color:#555;';

    // ── Cabeçalho de seção (ex: "1. IDENTIFICAÇÃO") ──────────
    // bgcolor e border são atributos HTML no Components::secao()
    const SECAO_TITULO = 'font-weight:bold; font-size:10.5pt; text-transform:uppercase; margin:20px 0 12px 0;';

    // ── Tabela ───────────────────────────────────────────────
    // width e border são atributos HTML no Components::tabela()
    const TABELA = 'border-collapse:collapse;';

    // ── Célula label (coluna esquerda) ────────────────────────
    // width e bgcolor são atributos HTML
    const TD_LABEL = 'font-weight:bold; padding:6px 8px; font-size:11pt; vertical-align:middle;';

    // ── Célula valor (coluna direita) ────────────────────────
    const TD_VALOR = 'padding:6px 8px; font-size:11pt; vertical-align:middle;';

    // ── Célula valor inteira (colspan=2) ─────────────────────
    const TD_FULL = 'padding:6px 8px; font-size:11pt;';

    // ── Parágrafo de texto corrido ───────────────────────────
    const TEXTO = 'text-align:justify; font-size:12pt; line-height:1.6;';

    // ── Parágrafo com indentação (parecer técnico) ───────────
    const TEXTO_INDENT = 'text-align:justify; font-size:12pt; line-height:1.7; text-indent:2.5cm; margin:0 0 18px 0;';

    // ── Bloco de condicionantes ──────────────────────────────
    const CONDICIONANTES = 'font-size:9pt; border:1px solid #000; padding:8px 10px;';

    // ── Data e local ─────────────────────────────────────────
    const DATA_LOCAL = 'text-align:right; font-size:11pt; margin-top:40px; margin-bottom:60px;';

    // ── Bloco de assinatura ──────────────────────────────────
    const ASSINATURA = 'border-top:1px solid #000; padding-top:8px; margin-top:40px; text-align:center; width:60%; margin-left:auto; margin-right:auto;';
    const ASSINATURA_NOME = 'font-weight:bold; font-size:11pt; text-transform:uppercase;';
    const ASSINATURA_CARGO = 'font-size:10pt;';

    public static function styleTag(): string
    {
        return self::BASE_CSS;
    }
}
