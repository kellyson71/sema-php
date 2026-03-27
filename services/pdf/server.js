#!/usr/bin/env node
/**
 * server.js — Microserviço HTTP de geração de PDF
 *
 * Mantém uma instância do Chrome aberta para respostas rápidas (~0.5s).
 *
 * Endpoints:
 *   POST /generate  — Recebe HTML no body, retorna PDF binário
 *   GET  /health    — Healthcheck
 *
 * Variáveis de ambiente:
 *   PORT           — Porta (padrão: 3001)
 *   PDF_SECRET     — Token de autenticação (opcional, recomendado em produção)
 */

const http = require('http');
const puppeteer = require('puppeteer');

const PORT = parseInt(process.env.PORT || '3001', 10);
const SECRET = process.env.PDF_SECRET || '';

let browser = null;

// ── Configuração de página ───────────────────────────────────────────
const PAGE_CONFIG = {
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: false,
    margin: { top: '28mm', right: '15mm', bottom: '28mm', left: '15mm' },
};

const DEFAULT_HEADER = `
<div style="width:100%; font-family:Helvetica,Arial,sans-serif; padding:0 15mm; box-sizing:border-box;">
    <div style="display:flex; align-items:center; gap:10px; padding-bottom:6px; border-bottom:2px solid #2d8661;">
        <div>
            <div style="font-size:10px; font-weight:bold; color:#282828;">PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</div>
            <div style="font-size:8px; font-weight:bold; color:#646464;">SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA</div>
        </div>
    </div>
</div>`;

const DEFAULT_FOOTER = `
<div style="width:100%; font-family:Helvetica,Arial,sans-serif; padding:0 15mm; box-sizing:border-box;">
    <div style="text-align:center; font-size:7px; color:#b4b4b4; font-style:italic;">
        Página <span class="pageNumber"></span> de <span class="totalPages"></span>
    </div>
</div>`;

// ── Template A4 ──────────────────────────────────────────────────────
function wrapInTemplate(fragment) {
    // Reutiliza os mesmos estilos do generate.js
    const templatePath = require('path').join(__dirname, 'generate.js');
    // Lê o arquivo de estilos inline — porém para simplicidade, incluímos direto
    return `<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: "Times New Roman", Times, serif;
        font-size: 12pt; line-height: 1.4; color: #1e1e1e;
        text-align: justify;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .titulo, .titulo-licenca { font-size:14pt; font-weight:bold; text-align:center; text-transform:uppercase; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:20px; }
    .subtitulo { font-size:10pt; text-align:center; margin-bottom:20px; font-style:italic; }
    .secao-titulo { font-weight:bold; text-transform:uppercase; background-color:#e8e8e8; padding:5px 6px; margin-top:15px; margin-bottom:4px; font-size:10pt; border:1px solid #aaa; }
    table, .tabela-dados { width:100%; border-collapse:collapse; margin-bottom:10px; }
    .tabela-dados td, table td, table th { padding:6px 8px; border:1px solid #aaa; vertical-align:middle; font-size:11pt; line-height:1.4; }
    .tabela-dados .label, td.label { font-weight:bold; background-color:#f0f0f0; width:30%; }
    p { margin-bottom:8px; line-height:1.5; }
    .texto-parecer p { margin-bottom:12px; text-indent:50px; line-height:1.7; }
    .especificacao { margin-top:6px; margin-bottom:6px; line-height:1.5; }
    .condicionantes { font-size:9pt; margin-top:10px; border:1px solid #000; padding:8px 10px; }
    .condicionantes ul { margin:5px 0; padding-left:20px; }
    .condicionantes li { margin-bottom:3px; line-height:1.4; }
    .data-local { margin-top:30px; text-align:right; font-size:11pt; }
    .linha-assinatura { border-top:1px solid #000; margin-top:50px; padding-top:5px; text-align:center; }
    .nome-assinante { font-weight:bold; font-size:10pt; text-transform:uppercase; }
    .cargo-assinante { font-size:9pt; }
    .dados-interessado { margin-bottom:20px; line-height:1.8; }
    .dados-interessado .label { font-weight:bold; }
    .page-break { page-break-before:always; }
    .avoid-break { page-break-inside:avoid; }
    .carimbo-assinatura { margin-top:20px; float:right; width:240px; border:1px solid #ddd; border-radius:4px; padding:8px 12px; background:#fdfffe; font-family:Helvetica,Arial,sans-serif; }
    .carimbo-assinatura .titulo-carimbo { font-size:6pt; font-weight:bold; color:#2d8661; text-align:center; margin-bottom:4px; text-transform:uppercase; border:none; padding:0; }
    .carimbo-assinatura .nome-carimbo { font-size:8pt; font-weight:bold; text-align:center; color:#1e1e1e; }
    .carimbo-assinatura .info-carimbo { font-size:6pt; color:#646464; text-align:center; line-height:1.5; }
</style>
</head>
<body>
${fragment}
</body>
</html>`;
}

// ── Gerar PDF ────────────────────────────────────────────────────────
async function generatePdf(html, options = {}) {
    if (!browser || !browser.isConnected()) {
        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--font-render-hinting=none'],
        });
    }

    const page = await browser.newPage();

    try {
        // Envolver no template se for fragmento
        if (!html.trim().toLowerCase().startsWith('<!doctype') && !html.trim().toLowerCase().startsWith('<html')) {
            html = wrapInTemplate(html);
        }

        await page.setContent(html, { waitUntil: 'networkidle0', timeout: 15000 });

        return await page.pdf({
            ...PAGE_CONFIG,
            landscape: options.landscape || false,
            displayHeaderFooter: true,
            headerTemplate: options.headerHtml || DEFAULT_HEADER,
            footerTemplate: options.footerHtml || DEFAULT_FOOTER,
        });
    } finally {
        await page.close();
    }
}

// ── Ler body da request ──────────────────────────────────────────────
function readBody(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        req.on('data', (c) => chunks.push(c));
        req.on('end', () => resolve(Buffer.concat(chunks).toString('utf-8')));
        req.on('error', reject);
    });
}

// ── HTTP Server ──────────────────────────────────────────────────────
const server = http.createServer(async (req, res) => {
    // CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    // Healthcheck
    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', browser: browser?.isConnected() ?? false }));
        return;
    }

    // Gerar PDF
    if (req.method === 'POST' && req.url === '/generate') {
        // Autenticação
        if (SECRET) {
            const auth = req.headers['authorization'] || '';
            if (auth !== `Bearer ${SECRET}`) {
                res.writeHead(401, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'Unauthorized' }));
                return;
            }
        }

        try {
            const body = await readBody(req);
            let payload;

            // Aceita JSON { html, options } ou HTML puro no body
            const contentType = req.headers['content-type'] || '';
            if (contentType.includes('application/json')) {
                payload = JSON.parse(body);
            } else {
                payload = { html: body };
            }

            const pdfBuffer = await generatePdf(payload.html, payload.options || {});

            res.writeHead(200, {
                'Content-Type': 'application/pdf',
                'Content-Length': pdfBuffer.length,
            });
            res.end(pdfBuffer);
        } catch (err) {
            console.error('Erro ao gerar PDF:', err);
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: err.message }));
        }
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not found' }));
});

// ── Inicializar ──────────────────────────────────────────────────────
async function start() {
    browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
    });

    const HOST = process.env.DOCKER_ENV ? '0.0.0.0' : '127.0.0.1';
    server.listen(PORT, HOST, () => {
        console.log(`PDF Service rodando em http://${HOST}:${PORT}`);
        console.log(`Healthcheck: GET /health`);
        console.log(`Gerar PDF:   POST /generate`);
    });
}

// Graceful shutdown
process.on('SIGTERM', async () => { if (browser) await browser.close(); process.exit(0); });
process.on('SIGINT', async () => { if (browser) await browser.close(); process.exit(0); });

start().catch((err) => {
    console.error('Falha ao iniciar:', err);
    process.exit(1);
});
