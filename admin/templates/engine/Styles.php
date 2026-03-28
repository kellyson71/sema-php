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
    // ── Título do documento ──────────────────────────────────
    const TITULO = 'font-family:Times New Roman,Times,serif; font-size:14pt; font-weight:bold; text-align:center; border-bottom:2px solid #000; padding-bottom:10px;';

    // ── Subtítulo (fundamentação legal, protocolo) ───────────
    const SUBTITULO = 'font-size:10pt; text-align:center; font-style:italic;';

    // ── Cabeçalho de seção (ex: "1. IDENTIFICAÇÃO") ──────────
    // bgcolor e border são atributos HTML no Components::secao()
    const SECAO_TITULO = 'font-weight:bold; padding:5px 6px; font-size:10pt;';

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
    const TEXTO = 'text-align:justify; font-size:11pt; line-height:1.5;';

    // ── Parágrafo com indentação (parecer técnico) ───────────
    const TEXTO_INDENT = 'text-align:justify; font-size:12pt; line-height:1.7; text-indent:50px;';

    // ── Bloco de condicionantes ──────────────────────────────
    const CONDICIONANTES = 'font-size:9pt; border:1px solid #000; padding:8px 10px;';

    // ── Data e local ─────────────────────────────────────────
    const DATA_LOCAL = 'text-align:right; font-size:11pt;';

    // ── Bloco de assinatura ──────────────────────────────────
    const ASSINATURA = 'border-top:1px solid #000; padding-top:5px; text-align:center;';
    const ASSINATURA_NOME = 'font-weight:bold; font-size:10pt;';
    const ASSINATURA_CARGO = 'font-size:9pt;';
}
