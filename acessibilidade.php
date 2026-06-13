<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acessibilidade — SEMA · Pau dos Ferros</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #f0f4fa;
            --card: #ffffff;
            --border: #dce6f0;
            --text: #0f1f36;
            --muted: #56697e;
            --blue: #1457a8;
            --blue-light: #e8f0fb;
            --radius: 16px;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── top bar ── */
        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 56px;
            display: flex; align-items: center; gap: 10px;
        }
        .topbar-logo {
            width: 30px; height: 30px;
            background: var(--blue);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .topbar-logo svg { width: 18px; height: 18px; fill: #fff; }
        .topbar-name { font-weight: 700; font-size: 15px; color: var(--blue); }
        .topbar-sep { color: var(--border); margin: 0 4px; }
        .topbar-page { font-size: 14px; color: var(--muted); }
        .topbar-back {
            margin-left: auto; font-size: 13px; color: var(--blue);
            text-decoration: none; display: flex; align-items: center; gap: 5px; font-weight: 500;
        }
        .topbar-back:hover { text-decoration: underline; }

        /* ── hero ── */
        .hero {
            background: linear-gradient(135deg, #1a5cb8 0%, #0f3c7a 100%);
            color: #fff;
            padding: 48px 24px 56px;
            text-align: center;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 100px;
            font-size: 12px; font-weight: 600; letter-spacing: .06em;
            padding: 4px 14px; text-transform: uppercase; margin-bottom: 18px;
        }
        .hero h1 { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; letter-spacing: -.02em; margin-bottom: 12px; }
        .hero p { font-size: 15px; color: rgba(255,255,255,.75); max-width: 560px; margin: 0 auto; line-height: 1.65; }

        /* ── layout ── */
        .container { max-width: 860px; margin: 0 auto; padding: 0 20px; }
        .content-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(15,31,54,.07);
            margin-top: -28px;
            margin-bottom: 40px;
            overflow: hidden;
        }

        /* ── section ── */
        .section { padding: 28px 32px; border-bottom: 1px solid var(--border); }
        .section:last-child { border-bottom: none; }
        .section-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 15px; font-weight: 700; color: var(--text);
            margin-bottom: 16px;
        }
        .section-title .icon {
            width: 32px; height: 32px; border-radius: 9px;
            background: var(--blue-light);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .section-title .icon svg { width: 16px; height: 16px; color: var(--blue); }

        /* ── feature list ── */
        .feature-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .feature-list li {
            display: flex; gap: 10px; align-items: flex-start;
            font-size: 14px; color: var(--muted); line-height: 1.6;
        }
        .feature-list li::before {
            content: '';
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--blue); flex-shrink: 0; margin-top: 8px;
        }
        .feature-list li strong { color: var(--text); }

        /* ── contact strip ── */
        .contact-strip {
            display: flex; flex-wrap: wrap; gap: 12px;
            margin-top: 16px;
        }
        .contact-chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--blue-light);
            border: 1px solid #c5d8f5;
            border-radius: 100px;
            padding: 7px 16px;
            font-size: 13.5px; font-weight: 600; color: #1a3a6b;
            text-decoration: none;
            transition: background .15s;
        }
        .contact-chip:hover { background: #d5e5f9; }
        .contact-chip svg { width: 15px; height: 15px; }

        /* ── law note ── */
        .law-note {
            font-size: 12.5px; color: var(--muted); line-height: 1.6; margin-top: 12px;
        }
        .law-note strong { color: var(--text); }

        /* ── footer ── */
        footer {
            border-top: 1px solid var(--border);
            text-align: center;
            padding: 20px;
            font-size: 12px; color: var(--muted);
        }
    </style>
</head>
<body>

<nav class="topbar">
    <div class="topbar-logo">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
    </div>
    <span class="topbar-name">SEMA</span>
    <span class="topbar-sep">·</span>
    <span class="topbar-page">Acessibilidade</span>
    <a href="javascript:history.back()" class="topbar-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
    </a>
</nav>

<header class="hero">
    <div class="hero-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 18a8 8 0 110-16 8 8 0 010 16zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
        Compromisso
    </div>
    <h1>Acessibilidade</h1>
    <p>A Secretaria Municipal de Meio Ambiente está comprometida em garantir que o sistema SEMA seja utilizável por todos os servidores, independentemente de limitações físicas ou tecnológicas.</p>
</header>

<div class="container">
    <div class="content-wrap">

        <div class="section">
            <div class="section-title">
                <div class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </div>
                Medidas adotadas
            </div>
            <ul class="feature-list">
                <li><span><strong>Estrutura semântica:</strong> uso de HTML semântico (landmarks, headings, listas) nas telas principais para compatibilidade com leitores de tela.</span></li>
                <li><span><strong>Contraste adequado:</strong> textos e elementos interativos seguem as proporções de contraste recomendadas pelas WCAG 2.1.</span></li>
                <li><span><strong>Rótulos visíveis:</strong> todos os campos de formulário possuem label associado e descrição acessível.</span></li>
                <li><span><strong>Feedback textual:</strong> erros de validação, autenticação e envio são comunicados por texto, não somente por cor.</span></li>
                <li><span><strong>Navegação por teclado:</strong> os fluxos de login, verificação de documentos e consulta de protocolo são operáveis via teclado.</span></li>
                <li><span><strong>Foco visível:</strong> indicadores de foco seguem o padrão do navegador e são preservados nos componentes customizados.</span></li>
            </ul>
        </div>

        <div class="section">
            <div class="section-title">
                <div class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                Relato de dificuldade
            </div>
            <p style="font-size:14px;color:var(--muted);line-height:1.65;margin-bottom:14px;">
                Se você encontrar uma barreira de acessibilidade no sistema, informe a equipe responsável pelos canais abaixo. O retorno ocorre em até dois dias úteis durante o horário de atendimento.
            </p>
            <div class="contact-strip">
                <a href="mailto:pmpfestagio@gmail.com" class="contact-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    pmpfestagio@gmail.com
                </a>
                <a href="https://wa.me/5584981087357" target="_blank" rel="noopener" class="contact-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.68A2 2 0 012 .92h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 8.9a16 16 0 006.29 6.29l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    +55 84 8108-7357
                </a>
            </div>
            <p class="law-note" style="margin-top:16px;">
                <strong>Horário de atendimento:</strong> segunda a sexta-feira, das 8h às 14h, em dias úteis.
            </p>
        </div>

        <div class="section">
            <div class="section-title">
                <div class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                Base legal
            </div>
            <p class="law-note">
                Este sistema segue as diretrizes da <strong>Lei Brasileira de Inclusão (Lei n.º 13.146/2015)</strong> e da <strong>eMAG — Modelo de Acessibilidade em Governo Eletrônico</strong>, que orientam o desenvolvimento de soluções digitais acessíveis no âmbito do serviço público brasileiro.
            </p>
        </div>

    </div>
</div>

<footer>
    SEMA &middot; Secretaria Municipal de Meio Ambiente &middot; Pau dos Ferros/RN &middot; &copy; <?php echo date('Y'); ?>
</footer>

</body>
</html>
