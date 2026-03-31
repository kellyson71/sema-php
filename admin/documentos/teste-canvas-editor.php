<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teste Canvas-Editor</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #1e1e1e; color: #eee; height: 100vh; display: flex; flex-direction: column; }

  #topbar {
    background: #2d2d2d;
    border-bottom: 1px solid #444;
    padding: 8px 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    flex-shrink: 0;
  }
  #topbar h1 { font-size: 0.9rem; color: #aaa; margin-right: 8px; white-space: nowrap; }
  #topbar button, #topbar select {
    padding: 4px 10px; font-size: 0.78rem; border-radius: 4px; border: 1px solid #555;
    background: #3a3a3a; color: #eee; cursor: pointer;
  }
  #topbar button:hover { background: #4a4a4a; border-color: #888; }
  #topbar .sep { width: 1px; height: 20px; background: #555; }
  #topbar .label { font-size: 0.7rem; color: #888; }
  #topbar input[type=text] { padding: 3px 8px; font-size: 0.78rem; border-radius: 4px; border: 1px solid #555; background: #1e1e1e; color: #eee; width: 160px; }

  #layout { flex: 1; display: flex; overflow: hidden; }

  #sidebar {
    width: 280px; flex-shrink: 0;
    background: #252525;
    border-right: 1px solid #444;
    display: flex; flex-direction: column;
    overflow: hidden;
  }
  #sidebar h3 { font-size: 0.75rem; color: #888; padding: 8px 10px; border-bottom: 1px solid #333; letter-spacing: 0.5px; text-transform: uppercase; }
  #log { flex: 1; overflow-y: auto; font-size: 0.72rem; font-family: monospace; padding: 6px 8px; }
  .log-ok   { color: #6fdf6f; margin-bottom: 3px; }
  .log-warn { color: #ffcc55; margin-bottom: 3px; }
  .log-err  { color: #ff6b6b; margin-bottom: 3px; }
  .log-info { color: #88bbff; margin-bottom: 3px; }

  #canvas-wrap {
    flex: 1;
    overflow: auto;
    background: #d0d4da;
    display: flex;
    align-items: flex-start;
    justify-content: center;
  }
  #canvas-editor-container {
    width: 100%;
    height: 100%;
    min-height: 600px;
  }

  #html-preview {
    width: 340px; flex-shrink: 0;
    background: #1a1a1a;
    border-left: 1px solid #444;
    display: flex; flex-direction: column;
  }
  #html-preview h3 { font-size: 0.75rem; color: #888; padding: 8px 10px; border-bottom: 1px solid #333; letter-spacing: 0.5px; text-transform: uppercase; }
  #html-out { flex: 1; overflow-y: auto; font-size: 0.68rem; font-family: monospace; padding: 6px 8px; white-space: pre-wrap; word-break: break-all; color: #c5e88a; }
</style>
</head>
<body>

<!-- Toolbar de Debug -->
<div id="topbar">
  <h1>Canvas-Editor Sandbox</h1>
  <div class="sep"></div>

  <div class="label">Init:</div>
  <button onclick="initEditor()">Criar instância</button>
  <button onclick="destroyEditor()">Destruir</button>

  <div class="sep"></div>

  <div class="label">Conteúdo:</div>
  <button onclick="carregarTextoSimples()">Texto simples</button>
  <button onclick="carregarHTMLTemplate()">HTML de template</button>
  <button onclick="carregarJson()">JSON nativo</button>

  <div class="sep"></div>

  <div class="label">Formatar:</div>
  <button onclick="ec('executeBold')">N</button>
  <button onclick="ec('executeItalic')">I</button>
  <button onclick="ec('executeUnderline')">S</button>
  <select onchange="ec('executeFontSize', parseInt(this.value)); this.value=''">
    <option value="">Tam</option>
    <option>10</option><option>12</option><option>14</option>
    <option>16</option><option>18</option><option>24</option>
  </select>
  <button onclick="ec('executeRowFlex', getRowFlex('left'))">←</button>
  <button onclick="ec('executeRowFlex', getRowFlex('center'))">≡</button>
  <button onclick="ec('executeRowFlex', getRowFlex('right'))">→</button>
  <button onclick="ec('executeRowFlex', getRowFlex('alignment'))">⬜</button>

  <div class="sep"></div>

  <button onclick="ec('executeUndo')">↺ Undo</button>
  <button onclick="ec('executeRedo')">↻ Redo</button>
  <button onclick="ec('executePrint')">🖨 Print</button>
  <button onclick="ec('executeInsertTable', 3, 3)">Tabela 3×3</button>

  <div class="sep"></div>

  <div class="label">Export:</div>
  <button onclick="exportHTML()">executeHTML()</button>
  <button onclick="exportValue()">executeGetValue()</button>
  <button onclick="inspecionarGlobal()">Inspecionar global</button>
</div>

<div id="layout">
  <!-- Log lateral esquerdo -->
  <div id="sidebar">
    <h3>Log de Depuração</h3>
    <div id="log"></div>
  </div>

  <!-- Canvas-Editor -->
  <div id="canvas-wrap">
    <div id="canvas-editor-container"></div>
  </div>

  <!-- Preview do HTML exportado -->
  <div id="html-preview">
    <h3>HTML Exportado</h3>
    <div id="html-out">(clique em "executeHTML()")</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@hufe921/canvas-editor@0.9.130/dist/canvas-editor.umd.js"></script>
<script>
/* ═══════════════════════════════════════════════════════════
   SANDBOX: Canvas-Editor Debug Page
   Objetivo: descobrir a API correta antes de integrar
═══════════════════════════════════════════════════════════ */

let instance = null;

// ─── Log helpers ───────────────────────────────────────────
function log(msg, type = 'info') {
    const d = document.getElementById('log');
    const el = document.createElement('div');
    el.className = 'log-' + type;
    el.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
    d.appendChild(el);
    d.scrollTop = d.scrollHeight;
}

// ─── Inspecionar o global exposto pelo UMD ─────────────────
function inspecionarGlobal() {
    const CE = window["canvas-editor"];
    if (!CE) { log('window["canvas-editor"] = undefined!', 'err'); return; }
    const keys = Object.keys(CE);
    log('window["canvas-editor"] keys: ' + keys.join(', '), 'ok');

    // Verificar enums importantes
    if (CE.PageMode)   log('PageMode: ' + JSON.stringify(CE.PageMode), 'info');
    if (CE.RowFlex)    log('RowFlex: ' + JSON.stringify(CE.RowFlex), 'info');
    if (CE.EditorMode) log('EditorMode: ' + JSON.stringify(CE.EditorMode), 'info');
    if (CE.ElementType) log('ElementType (primeiros): ' + Object.keys(CE.ElementType).slice(0,8).join(', '), 'info');
    log('Editor class: ' + (CE.Editor ? 'OK (' + typeof CE.Editor + ')' : 'NÃO ENCONTRADO'), CE.Editor ? 'ok' : 'err');
}

// ─── Criar instância ───────────────────────────────────────
function initEditor(htmlInicial) {
    if (instance) { log('Instância já existe. Destruindo primeiro...', 'warn'); destroyEditor(); }

    const CE = window["canvas-editor"];
    if (!CE || !CE.Editor) {
        log('ERRO: window["canvas-editor"].Editor não existe!', 'err');
        inspecionarGlobal();
        return;
    }

    try {
        // Detectar PageMode enum
        const pageMode = CE.PageMode
            ? (CE.PageMode.PAGING || CE.PageMode.paging || CE.PageMode.Paging || Object.values(CE.PageMode)[0])
            : undefined;

        log('PageMode usado: ' + JSON.stringify(pageMode), 'info');

        instance = new CE.Editor(
            document.getElementById('canvas-editor-container'),
            {
                header: [],
                main: [{ value: '' }],
                footer: []
            },
            {
                width:  794,   // A4 @ 96dpi
                height: 1123,
                pageGap:     20,
                pageMode:    pageMode,
                // Margens: [top, right, bottom, left] conforme docs
                margins:     [100, 120, 100, 120],
                defaultFont: 'Arial',
                defaultSize: 14,
            }
        );

        log('Instância criada com sucesso!', 'ok');
        log('Propriedades: ' + Object.keys(instance).join(', '), 'info');
        log('instance.command keys: ' + Object.getOwnPropertyNames(Object.getPrototypeOf(instance.command)).filter(k => k !== 'constructor').join(', '), 'info');

        // Carrega HTML inicial se fornecido
        if (htmlInicial) {
            setTimeout(() => {
                carregarHTMLNaInstancia(htmlInicial);
            }, 300);
        }

    } catch(e) {
        log('ERRO ao criar instância: ' + e.message, 'err');
        console.error(e);
    }
}

// ─── Destruir instância ────────────────────────────────────
function destroyEditor() {
    if (!instance) { log('Nenhuma instância ativa.', 'warn'); return; }
    try {
        if (typeof instance.destroy === 'function') instance.destroy();
        instance = null;
        document.getElementById('canvas-editor-container').innerHTML = '';
        log('Instância destruída.', 'ok');
    } catch(e) {
        log('Erro ao destruir: ' + e.message, 'err');
        instance = null;
    }
}

// ─── Executar comando na instância ─────────────────────────
function ec(name, ...args) {
    if (!instance) { log('Sem instância ativa.', 'warn'); return; }
    try {
        instance.command[name](...args);
        log('cmd: ' + name + (args.length ? ' (' + args.join(', ') + ')' : ''), 'ok');
    } catch(e) {
        log('ERRO cmd ' + name + ': ' + e.message, 'err');
        console.error(e);
    }
}

// ─── RowFlex helper ────────────────────────────────────────
function getRowFlex(val) {
    const CE = window["canvas-editor"];
    if (CE && CE.RowFlex) {
        return CE.RowFlex[val.toUpperCase()] || val;
    }
    return val;
}

// ─── Carregar texto simples ───────────────────────────────
function carregarTextoSimples() {
    if (!instance) { initEditor(); }
    setTimeout(() => {
        try {
            instance.command.executeSetHTML({
                main: '<p style="text-align:center"><strong>ALVARÁ DE CONSTRUÇÃO</strong></p><p>Texto de teste simples para verificar renderização.</p><p>Segunda linha do documento com mais conteúdo para ver o comportamento do canvas.</p>'
            });
            log('executeSetHTML (texto simples) executado.', 'ok');
        } catch(e) {
            log('ERRO executeSetHTML: ' + e.message, 'err');
            console.error(e);
        }
    }, 500);
}

// ─── Carregar HTML de template complexo ───────────────────
function carregarHTMLTemplate() {
    if (!instance) { initEditor(); }
    const html = `
        <h2 style="text-align:center">ALVARÁ DE CONSTRUÇÃO Nº 001/2026</h2>
        <p style="text-align:justify">
            A <strong>Secretaria Municipal de Meio Ambiente - SEMA</strong> do Município de
            <strong>Pau dos Ferros/RN</strong>, no uso de suas atribuições legais, resolve
            conceder o presente Alvará de Construção.
        </p>
        <h3>1. IDENTIFICAÇÃO DO PROPRIETÁRIO</h3>
        <table border="1" cellpadding="6" style="width:100%; border-collapse:collapse">
            <tr><td><strong>Nome</strong></td><td>KELLYSON RAPHAEL MEDEIROS DA SILVA</td></tr>
            <tr><td><strong>CPF/CNPJ</strong></td><td>000.000.000-00</td></tr>
            <tr><td><strong>Endereço</strong></td><td>Rua Exemplo, 123 - Centro</td></tr>
        </table>
        <h3>2. DADOS DA OBRA</h3>
        <table border="1" cellpadding="6" style="width:100%; border-collapse:collapse">
            <tr><td><strong>Protocolo</strong></td><td>20260327155819779</td></tr>
            <tr><td><strong>Área Construída</strong></td><td>120 m²</td></tr>
        </table>
        <p style="text-align:center; margin-top:60px">Pau dos Ferros/RN, 31 de março de 2026.</p>
        <p style="text-align:center; margin-top:40px">___________________________<br>Assinatura do Responsável</p>
    `;
    setTimeout(() => {
        try {
            instance.command.executeSetHTML({ main: html });
            log('executeSetHTML (template HTML) executado.', 'ok');
        } catch(e) {
            log('ERRO executeSetHTML: ' + e.message, 'err');
            console.error(e);
        }
    }, 500);
}

// ─── Carregar via JSON nativo ──────────────────────────────
function carregarJson() {
    if (!instance) { initEditor(); }
    const CE = window["canvas-editor"];
    setTimeout(() => {
        try {
            // Tentar via setValue com IElement[]
            const data = {
                header: [],
                main: [
                    { value: 'ALVARÁ DE CONSTRUÇÃO Nº 001/2026', size: 18, bold: true, rowFlex: getRowFlex('center') },
                    { value: '\n' },
                    { value: 'Texto do documento via JSON nativo (IElement[]).', size: 14 },
                    { value: '\n' },
                    { value: 'Segunda linha do documento.', size: 14 },
                ],
                footer: []
            };

            if (typeof instance.command.executeSetValue === 'function') {
                instance.command.executeSetValue(data);
                log('executeSetValue(JSON) executado.', 'ok');
            } else {
                log('executeSetValue não existe. Tentando setValue...', 'warn');
                // Alternativa: reiniciar instância com os dados
                destroyEditor();
                const newInstance = new CE.Editor(
                    document.getElementById('canvas-editor-container'),
                    data,
                    { width: 794, height: 1123, pageGap: 20, margins: [100, 120, 100, 120] }
                );
                instance = newInstance;
                log('Nova instância com JSON criada.', 'ok');
            }
        } catch(e) {
            log('ERRO JSON: ' + e.message, 'err');
            console.error(e);
        }
    }, 500);
}

// ─── Carregar HTML na instância (função reutilizável) ──────
function carregarHTMLNaInstancia(html) {
    if (!instance) return;
    try {
        instance.command.executeSetHTML({ main: html });
        log('HTML carregado via executeSetHTML.', 'ok');
    } catch(e) {
        log('executeSetHTML falhou: ' + e.message + ' — tentando executeSetValue...', 'warn');
        try {
            const CE = window["canvas-editor"];
            if (CE && CE.getElementListByHTML) {
                const elements = CE.getElementListByHTML(html, {});
                instance.command.executeSetValue({ main: elements, header: [], footer: [] });
                log('HTML convertido via getElementListByHTML + executeSetValue.', 'ok');
            } else {
                log('getElementListByHTML não disponível.', 'err');
            }
        } catch(e2) {
            log('Fallback também falhou: ' + e2.message, 'err');
        }
    }
}

// ─── Export HTML ──────────────────────────────────────────
function exportHTML() {
    if (!instance) { log('Sem instância.', 'warn'); return; }
    try {
        const html = instance.command.executeHTML();
        document.getElementById('html-out').textContent = html || '(vazio)';
        log('executeHTML() retornou ' + (html ? html.length + ' chars' : 'vazio'), html ? 'ok' : 'warn');
    } catch(e) {
        log('ERRO executeHTML: ' + e.message, 'err');
        console.error(e);
    }
}

// ─── Export JSON value ────────────────────────────────────
function exportValue() {
    if (!instance) { log('Sem instância.', 'warn'); return; }
    try {
        const val = instance.command.executeGetValue();
        const json = JSON.stringify(val, null, 2);
        document.getElementById('html-out').textContent = json || '(vazio)';
        log('executeGetValue() retornou objeto com ' + Object.keys(val).join(', '), 'ok');
    } catch(e) {
        log('ERRO executeGetValue: ' + e.message, 'err');
        console.error(e);
    }
}

// ─── Auto-init na carga ───────────────────────────────────
window.addEventListener('load', function() {
    log('Script carregado. CDN UMD disponível: ' + (!!window["canvas-editor"]), window["canvas-editor"] ? 'ok' : 'err');
    inspecionarGlobal();
    initEditor();
    log('Pronto. Use os botões acima para testar.', 'ok');
});
</script>
</body>
</html>
