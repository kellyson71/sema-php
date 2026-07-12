<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte — SEMA</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #009851;
            --primary-600: #007840;
            --primary-700: #0b5d38;
            --primary-50: #e6f7ef;
            --primary-100: #cfeedd;
            --ink: #10233d;
            --ink-2: #475569;
            --ink-3: #64748b;
            --line: #e2e8f0;
            --line-2: #cbd5e1;
            --bg: #f8fafc;
            --card: #ffffff;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow-card: 0 1px 2px rgba(15,23,42,0.04), 0 8px 24px -8px rgba(15,23,42,0.10);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .display { font-family: 'Inter Tight', sans-serif; letter-spacing: -0.02em; }
        .mono    { font-family: 'JetBrains Mono', monospace; }

        /* ── Layout ── */
        .layout {
            flex: 1;
            display: grid;
            grid-template-columns: minmax(380px, 1.1fr) 1fr;
        }

        /* ── Brand Panel ── */
        .brand-panel {
            position: relative;
            background: linear-gradient(160deg, var(--primary-700) 0%, var(--primary) 60%, var(--primary-600) 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 44px 48px;
            overflow: hidden;
        }
        .brand-grid-bg {
            position: absolute; inset: 0; width: 100%; height: 100%;
            opacity: .10; pointer-events: none;
        }
        .brand-glow {
            position: absolute; right: -100px; top: -100px;
            width: 400px; height: 400px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.13), transparent 70%);
            filter: blur(20px);
        }
        .brand-top { position: relative; z-index: 1; }
        .brand-badge {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 5px 12px; border-radius: 999px;
            background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.20);
            font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
        }
        .brand-logo {
            margin-top: 44px; width: 180px; display: block;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,.20));
        }
        .brand-middle { position: relative; z-index: 1; margin-top: 32px; }
        .brand-middle h1 {
            font-size: 40px; font-weight: 700; line-height: 1.07;
            letter-spacing: -.025em; margin-bottom: 14px;
        }
        .brand-middle p {
            font-size: 15px; line-height: 1.6;
            color: rgba(255,255,255,.82);
        }

        /* ── Content Panel ── */
        .content-panel {
            display: flex; align-items: center; justify-content: center;
            padding: 60px 32px; background: var(--bg);
        }
        .content-card {
            width: 100%; max-width: 420px;
            padding: 36px 36px 32px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
        }

        /* ── Card header ── */
        .card-header { margin-bottom: 24px; }
        .card-kicker {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px; letter-spacing: .12em;
            color: var(--ink-3); text-transform: uppercase;
        }
        .card-title {
            margin: 6px 0 0;
            font-size: 28px; font-weight: 700;
        }
        .card-sub {
            margin-top: 8px;
            font-size: 13.5px; line-height: 1.6; color: var(--ink-2);
        }

        /* ── Contact items ── */
        .contact-list { display: flex; flex-direction: column; gap: 12px; margin-top: 4px; }
        .contact-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--bg);
            text-decoration: none;
            color: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        a.contact-item:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
        }
        .contact-icon {
            width: 36px; height: 36px; border-radius: 9px;
            background: var(--primary-50);
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .contact-label {
            font-size: 11px; font-weight: 600; letter-spacing: .06em;
            text-transform: uppercase; color: var(--ink-3); margin-bottom: 3px;
        }
        .contact-value { font-size: 14px; font-weight: 600; color: var(--ink); }
        .contact-desc  { font-size: 12px; color: var(--ink-3); margin-top: 2px; }

        /* ── Divider ── */
        .divider {
            border: none; border-top: 1px solid var(--line);
            margin: 20px 0;
        }

        /* ── Back link ── */
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; font-weight: 500; color: var(--ink-2);
            text-decoration: none; margin-top: 8px;
            background: none; border: none; cursor: pointer; padding: 0;
        }
        .btn-back:hover { color: var(--primary); }

        /* ── Footer ── */
        .login-footer {
            padding: 16px 28px; border-top: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap; font-size: 12px; color: var(--ink-3);
            background: var(--bg);
        }
        .footer-links { display: flex; gap: 20px; }
        .footer-links a { color: var(--ink-2); text-decoration: none; font-weight: 500; font-size: 12px; }
        .footer-links a:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .content-panel { padding: 40px 20px; }
        }
    </style>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body>

<div class="layout">

    <!-- ══ Brand Panel ══ -->
    <aside class="brand-panel">
        <svg class="brand-grid-bg" aria-hidden="true">
            <defs>
                <pattern id="g1" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#fff" stroke-width=".6"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#g1)"/>
        </svg>
        <div class="brand-glow"></div>

        <div class="brand-top">
            <div class="brand-badge">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 3v7c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V5l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
                Acesso Institucional
            </div>
            <img src="./assets/SEMA/PNG/Branca/Logo SEMA Vertical 3.png"
                 alt="SEMA — Secretaria Municipal de Meio Ambiente"
                 class="brand-logo">
        </div>

        <div class="brand-middle">
            <h1 class="display">Secretaria Municipal<br>de Meio Ambiente</h1>
            <p>Painel de gestão de alvarás, pareceres técnicos e licenciamento ambiental do município de Pau dos Ferros/RN.</p>
        </div>
    </aside>

    <!-- ══ Content Panel ══ -->
    <section class="content-panel">
        <div class="content-card">

            <div class="card-header">
                <div class="card-kicker">Central de Atendimento</div>
                <h2 class="display card-title">Suporte institucional</h2>
                <p class="card-sub">O suporte do sistema SEMA é realizado pela equipe interna da Prefeitura de Pau dos Ferros. Para dificuldades de acesso, inconsistências cadastrais ou dúvidas operacionais, utilize um dos canais abaixo.</p>
            </div>

            <div class="contact-list">

                <a href="mailto:pmpfestagio@gmail.com" class="contact-item">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div>
                        <div class="contact-label">E-mail</div>
                        <div class="contact-value">pmpfestagio@gmail.com</div>
                        <div class="contact-desc">Envie sua dúvida a qualquer momento</div>
                    </div>
                </a>

                <a href="https://wa.me/5584981087357" target="_blank" rel="noopener" class="contact-item">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.68A2 2 0 012 .92h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 8.9a16 16 0 006.29 6.29l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    </div>
                    <div>
                        <div class="contact-label">Telefone / WhatsApp</div>
                        <div class="contact-value">+55 84 8108-7357</div>
                        <div class="contact-desc">Chamada ou mensagem via WhatsApp</div>
                    </div>
                </a>

                <div class="contact-item">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <div class="contact-label">Horário de atendimento</div>
                        <div class="contact-value">8h às 14h</div>
                        <div class="contact-desc">Segunda a sexta-feira, dias úteis</div>
                    </div>
                </div>

            </div>

            <hr class="divider">

            <a href="javascript:history.back()" class="btn-back">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg>
                Voltar
            </a>

        </div>
    </section>

</div>

<footer class="login-footer">
    <div style="display:flex;gap:16px;align-items:center">
        <span class="mono" style="letter-spacing:.02em">SEMA · PAU DOS FERROS/RN</span>
        <span style="opacity:.5">•</span>
        <span>© <?= date('Y') ?> Secretaria Municipal de Meio Ambiente</span>
    </div>
    <div class="footer-links">
        <a href="./suporte.php">Suporte</a>
        <a href="./privacidade.php">Privacidade</a>
        <a href="./acessibilidade.php">Acessibilidade</a>
    </div>
</footer>

</body>
</html>
