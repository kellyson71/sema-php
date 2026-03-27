#!/usr/bin/env node
/**
 * generate.js — Gerador de PDF via Puppeteer (Headless Chrome)
 *
 * Modos de uso:
 *   1. CLI:  node generate.js input.html output.pdf
 *   2. STDIN: echo "<html>...</html>" | node generate.js - output.pdf
 *   3. Pipe:  node generate.js input.html -   (PDF vai para stdout)
 *
 * O HTML de entrada pode ser:
 *   - Um documento completo (<html>...) — renderizado diretamente
 *   - Um fragmento (conteúdo do editor) — envolvido no template A4
 *
 * Opções via variáveis de ambiente:
 *   PDF_HEADER_HTML  — HTML customizado para o header de cada página
 *   PDF_FOOTER_HTML  — HTML customizado para o footer de cada página
 *   PDF_LANDSCAPE    — "1" para paisagem (padrão: retrato)
 *   PDF_NO_TEMPLATE  — "1" para não envolver no template A4
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

// ── Configuração da página A4 ────────────────────────────────────────
const PAGE_CONFIG = {
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: false,
    margin: {
        top: '28mm',     // Espaço para o header institucional
        right: '15mm',
        bottom: '28mm',  // Espaço para o footer/assinatura
        left: '15mm',
    },
};

// ── Header institucional (logo + título) ─────────────────────────────
const DEFAULT_HEADER = `
<div style="width:100%; font-family:Helvetica,Arial,sans-serif; padding:0 15mm; box-sizing:border-box;">
    <div style="display:flex; align-items:center; gap:10px; padding-bottom:6px; border-bottom:2px solid #2d8661;">
        <div>
            <div style="font-size:10px; font-weight:bold; color:#282828;">PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</div>
            <div style="font-size:8px; font-weight:bold; color:#646464;">SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA</div>
        </div>
    </div>
</div>`;

// ── Footer (paginação) ───────────────────────────────────────────────
const DEFAULT_FOOTER = `
<div style="width:100%; font-family:Helvetica,Arial,sans-serif; padding:0 15mm; box-sizing:border-box;">
    <div style="text-align:center; font-size:7px; color:#b4b4b4; font-style:italic;">
        Página <span class="pageNumber"></span> de <span class="totalPages"></span>
    </div>
</div>`;

// ── Template A4 que envolve fragmentos do editor ─────────────────────
function wrapInTemplate(fragmentHtml) {
    return `<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<style>
    /* Reset */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: "Times New Roman", Times, serif;
        font-size: 12pt;
        line-height: 1.4;
        color: #1e1e1e;
        text-align: justify;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ── Títulos ───────────────────────────────────────────── */
    .titulo, .titulo-licenca {
        font-size: 14pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .subtitulo {
        font-size: 10pt;
        text-align: center;
        margin-bottom: 20px;
        font-style: italic;
    }

    /* ── Seções ────────────────────────────────────────────── */
    .secao-titulo {
        font-weight: bold;
        text-transform: uppercase;
        background-color: #e8e8e8;
        padding: 5px 6px;
        margin-top: 15px;
        margin-bottom: 4px;
        font-size: 10pt;
        border: 1px solid #aaa;
    }

    /* ── Tabelas ───────────────────────────────────────────── */
    table, .tabela-dados {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }

    .tabela-dados td, table td, table th {
        padding: 6px 8px;
        border: 1px solid #aaa;
        vertical-align: middle;
        font-size: 11pt;
        line-height: 1.4;
    }

    .tabela-dados .label, td.label {
        font-weight: bold;
        background-color: #f0f0f0;
        width: 30%;
    }

    /* ── Texto ─────────────────────────────────────────────── */
    p { margin-bottom: 8px; line-height: 1.5; }

    .texto-parecer p {
        margin-bottom: 12px;
        text-indent: 50px;
        line-height: 1.7;
    }

    .especificacao {
        margin-top: 6px;
        margin-bottom: 6px;
        line-height: 1.5;
    }

    /* ── Condicionantes ────────────────────────────────────── */
    .condicionantes {
        font-size: 9pt;
        margin-top: 10px;
        border: 1px solid #000;
        padding: 8px 10px;
    }

    .condicionantes ul { margin: 5px 0; padding-left: 20px; }
    .condicionantes li { margin-bottom: 3px; line-height: 1.4; }

    /* ── Data e assinatura ─────────────────────────────────── */
    .data-local {
        margin-top: 30px;
        text-align: right;
        font-size: 11pt;
    }

    .linha-assinatura {
        border-top: 1px solid #000;
        margin-top: 50px;
        padding-top: 5px;
        text-align: center;
    }

    .nome-assinante { font-weight: bold; font-size: 10pt; text-transform: uppercase; }
    .cargo-assinante { font-size: 9pt; }

    /* ── Dados inline ──────────────────────────────────────── */
    .dados-interessado { margin-bottom: 20px; line-height: 1.8; }
    .dados-interessado .label { font-weight: bold; }

    /* ── Controle de paginação ─────────────────────────────── */
    .page-break { page-break-before: always; }
    .avoid-break { page-break-inside: avoid; }

    /* ── Carimbo de assinatura eletrônica ──────────────────── */
    .carimbo-assinatura {
        margin-top: 20px;
        float: right;
        width: 240px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px 12px;
        background: #fdfffe;
        font-family: Helvetica, Arial, sans-serif;
    }
    .carimbo-assinatura .titulo-carimbo {
        font-size: 6pt;
        font-weight: bold;
        color: #2d8661;
        text-align: center;
        margin-bottom: 4px;
        text-transform: uppercase;
        border: none;
        padding: 0;
    }
    .carimbo-assinatura .nome-carimbo {
        font-size: 8pt;
        font-weight: bold;
        text-align: center;
        color: #1e1e1e;
    }
    .carimbo-assinatura .info-carimbo {
        font-size: 6pt;
        color: #646464;
        text-align: center;
        line-height: 1.5;
    }
</style>
</head>
<body>
${fragmentHtml}
</body>
</html>`;
}

// ── Main ─────────────────────────────────────────────────────────────
async function main() {
    const args = process.argv.slice(2);
    const inputPath = args[0];
    const outputPath = args[1];

    if (!inputPath || !outputPath) {
        console.error('Uso: node generate.js <input.html|--> <output.pdf|-->');
        process.exit(1);
    }

    // Ler HTML de entrada
    let html;
    if (inputPath === '-') {
        html = await readStdin();
    } else {
        html = fs.readFileSync(inputPath, 'utf-8');
    }

    // Envolver no template A4 se for fragmento (sem <html> tag)
    const noTemplate = process.env.PDF_NO_TEMPLATE === '1';
    if (!noTemplate && !html.trim().toLowerCase().startsWith('<!doctype') && !html.trim().toLowerCase().startsWith('<html')) {
        html = wrapInTemplate(html);
    }

    // Header/footer customizados ou padrão
    const headerHtml = process.env.PDF_HEADER_HTML || DEFAULT_HEADER;
    const footerHtml = process.env.PDF_FOOTER_HTML || DEFAULT_FOOTER;
    const landscape = process.env.PDF_LANDSCAPE === '1';

    // Gerar PDF
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--font-render-hinting=none',
        ],
    });

    try {
        const page = await browser.newPage();

        await page.setContent(html, { waitUntil: 'networkidle0', timeout: 15000 });

        const pdfBuffer = await page.pdf({
            ...PAGE_CONFIG,
            landscape,
            displayHeaderFooter: true,
            headerTemplate: headerHtml,
            footerTemplate: footerHtml,
        });

        // Saída
        if (outputPath === '-') {
            process.stdout.write(pdfBuffer);
        } else {
            fs.writeFileSync(outputPath, pdfBuffer);
        }
    } finally {
        await browser.close();
    }
}

function readStdin() {
    return new Promise((resolve) => {
        const chunks = [];
        process.stdin.on('data', (chunk) => chunks.push(chunk));
        process.stdin.on('end', () => resolve(Buffer.concat(chunks).toString('utf-8')));
    });
}

main().catch((err) => {
    console.error('Erro ao gerar PDF:', err.message);
    process.exit(1);
});
