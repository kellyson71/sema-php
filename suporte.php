<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte — SEMA · Pau dos Ferros</title>
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
            --green: #0e7a50;
            --green-light: #e6f4ed;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-logo {
            width: 30px; height: 30px;
            background: var(--blue);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .topbar-logo svg { width: 18px; height: 18px; fill: #fff; }
        .topbar-name { font-weight: 700; font-size: 15px; color: var(--blue); letter-spacing: -.01em; }
        .topbar-sep { color: var(--border); margin: 0 4px; }
        .topbar-page { font-size: 14px; color: var(--muted); }
        .topbar-back {
            margin-left: auto;
            font-size: 13px;
            color: var(--blue);
            text-decoration: none;
            display: flex; align-items: center; gap: 5px;
            font-weight: 500;
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
            font-size: 12px; font-weight: 600;
            letter-spacing: .06em;
            padding: 4px 14px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .hero h1 { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; letter-spacing: -.02em; margin-bottom: 12px; }
        .hero p { font-size: 15px; color: rgba(255,255,255,.75); max-width: 560px; margin: 0 auto; line-height: 1.65; }

        /* ── layout ── */
        .container { max-width: 880px; margin: 0 auto; padding: 0 20px; }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-top: -28px;
            padding-bottom: 40px;
        }

        /* ── contact card ── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 4px 20px rgba(15,31,54,.06);
            transition: box-shadow .18s, transform .18s;
        }
        .card:hover { box-shadow: 0 8px 32px rgba(15,31,54,.11); transform: translateY(-2px); }
        .card-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .card-icon.blue { background: var(--blue-light); }
        .card-icon.green { background: var(--green-light); }
        .card-icon.orange { background: #fff4e5; }
        .card-icon svg { width: 22px; height: 22px; }
        .card-icon.blue svg { color: var(--blue); }
        .card-icon.green svg { color: var(--green); }
        .card-icon.orange svg { color: #b35f00; }
        .card-label {
            font-size: 11px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--muted);
            margin-bottom: 6px;
        }
        .card-value { font-size: 16px; font-weight: 700; color: var(--text); line-height: 1.3; }
        .card-sub { font-size: 13px; color: var(--muted); margin-top: 4px; line-height: 1.5; }
        .card a { color: var(--blue); text-decoration: none; font-weight: 600; }
        .card a:hover { text-decoration: underline; }

        /* ── note ── */
        .note {
            background: var(--blue-light);
            border: 1px solid #c5d8f5;
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 13.5px;
            color: #1a3a6b;
            margin-bottom: 40px;
            display: flex; gap: 10px; align-items: flex-start;
            line-height: 1.6;
        }
        .note svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; }

        /* ── footer ── */
        footer {
            border-top: 1px solid var(--border);
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: var(--muted);
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
    <span class="topbar-page">Suporte</span>
    <a href="javascript:history.back()" class="topbar-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
    </a>
</nav>

<header class="hero">
    <div class="hero-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        Atendimento
    </div>
    <h1>Suporte institucional</h1>
    <p>O suporte do sistema SEMA é realizado pela equipe interna da Prefeitura de Pau dos Ferros. Para dificuldades de acesso, inconsistências cadastrais ou dúvidas operacionais, utilize um dos canais abaixo.</p>
</header>

<div class="container">
    <div class="cards-grid">

        <div class="card">
            <div class="card-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div class="card-label">E-mail</div>
            <div class="card-value"><a href="mailto:pmpfestagio@gmail.com">pmpfestagio@gmail.com</a></div>
            <div class="card-sub">Envie sua dúvida ou solicitação a qualquer momento.</div>
        </div>

        <div class="card">
            <div class="card-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.68A2 2 0 012 .92h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 8.9a16 16 0 006.29 6.29l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            </div>
            <div class="card-label">Telefone / WhatsApp</div>
            <div class="card-value"><a href="https://wa.me/5584981087357" target="_blank" rel="noopener">+55 84 8108-7357</a></div>
            <div class="card-sub">Atendimento por chamada ou mensagem via WhatsApp.</div>
        </div>

        <div class="card">
            <div class="card-icon orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="card-label">Horário de atendimento</div>
            <div class="card-value">8h às 14h</div>
            <div class="card-sub">Segunda a sexta-feira, em dias úteis.</div>
        </div>

    </div>

    <div class="note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Este sistema é de uso exclusivo de servidores autorizados da Secretaria Municipal de Meio Ambiente. Em caso de esquecimento de senha ou bloqueio de acesso, entre em contato pelos canais acima durante o horário de atendimento.</span>
    </div>
</div>

<footer>
    SEMA &middot; Secretaria Municipal de Meio Ambiente &middot; Pau dos Ferros/RN &middot; &copy; <?php echo date('Y'); ?>
</footer>

</body>
</html>
