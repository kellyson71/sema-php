<?php
/**
 * Styles.php — Estilos inline centralizados para TCPDF
 *
 * O Summernote remove blocos <style>, então os templates precisam de inline styles.
 * TCPDF tem suporte limitado a CSS, então usamos atributos HTML + inline styles.
 *
 * TODOS os estilos vivem aqui. Para alterar a aparência de todos os documentos
 * de uma vez, basta editar este arquivo.
 */

class DocumentStyles
{
    // ── Título do documento ──────────────────────────────────
    const TITULO = 'font-family:Times New Roman,Times,serif; font-size:14pt; font-weight:bold; text-align:center; text-transform:uppercase; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:20px;';

    // ── Subtítulo (fundamentação legal, protocolo) ───────────
    const SUBTITULO = 'font-size:10pt; text-align:center; margin-bottom:20px; font-style:italic;';

    // ── Cabeçalho de seção (ex: "1. IDENTIFICAÇÃO") ──────────
    const SECAO_TITULO = 'font-weight:bold; text-transform:uppercase; background-color:#e8e8e8; padding:5px 6px; margin-top:15px; margin-bottom:4px; font-size:10pt; border:1px solid #aaa;';

    // ── Tabela ───────────────────────────────────────────────
    const TABELA = 'width:100%; border-collapse:collapse; margin-bottom:10px;';

    // ── Célula label (coluna esquerda) ────────────────────────
    const TD_LABEL = 'font-weight:bold; background-color:#f0f0f0; border:1px solid #aaa; padding:6px 8px; width:30%; vertical-align:middle; font-size:11pt;';

    // ── Célula valor (coluna direita) ────────────────────────
    const TD_VALOR = 'border:1px solid #aaa; padding:6px 8px; vertical-align:middle; font-size:11pt;';

    // ── Célula valor inteira (colspan=2) ─────────────────────
    const TD_FULL = 'border:1px solid #aaa; padding:6px 8px; font-size:11pt;';

    // ── Parágrafo de texto corrido ───────────────────────────
    const TEXTO = 'text-align:justify; font-size:11pt; line-height:1.5; margin-top:6px; margin-bottom:6px;';

    // ── Parágrafo com indentação (parecer técnico) ───────────
    const TEXTO_INDENT = 'text-align:justify; font-size:12pt; line-height:1.7; margin-bottom:12px; text-indent:50px;';

    // ── Bloco de condicionantes ──────────────────────────────
    const CONDICIONANTES = 'font-size:9pt; margin-top:10px; border:1px solid #000; padding:8px 10px;';

    // ── Data e local ─────────────────────────────────────────
    const DATA_LOCAL = 'margin-top:30px; text-align:right; font-size:11pt;';

    // ── Bloco de assinatura ──────────────────────────────────
    const ASSINATURA = 'border-top:1px solid #000; margin-top:50px; padding-top:5px; text-align:center;';
    const ASSINATURA_NOME = 'font-weight:bold; font-size:10pt; text-transform:uppercase;';
    const ASSINATURA_CARGO = 'font-size:9pt;';
}
