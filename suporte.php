<?php
$paginaTitulo = 'Suporte';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte - SEMA</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #10233d;
            --muted: #5f6f84;
            --line: #d8e2ef;
            --primary: #165d96;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, sans-serif;
            background: linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
            color: var(--text);
        }
        main {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
        }
        .card {
            width: 100%;
            max-width: 760px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(16, 35, 61, 0.08);
        }
        h1 { margin: 0 0 10px; font-size: 2rem; }
        p { margin: 0 0 16px; line-height: 1.65; color: var(--muted); }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .item {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
            background: #fbfdff;
        }
        .label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 8px;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main>
        <section class="card">
            <h1>Suporte institucional</h1>
            <p>O suporte do sistema SEMA é realizado pela equipe interna da Prefeitura de Pau dos Ferros. Para dificuldades de acesso, inconsistências cadastrais ou dúvidas operacionais, utilize um dos canais abaixo.</p>
            <div class="grid">
                <article class="item">
                    <div class="label">Atendimento interno</div>
                    <p>Setor de TI<br><strong>Ramal 2104</strong></p>
                </article>
                <article class="item">
                    <div class="label">E-mail</div>
                    <p><a href="mailto:ti@paudosferros.rn.gov.br">ti@paudosferros.rn.gov.br</a></p>
                </article>
                <article class="item">
                    <div class="label">Horário</div>
                    <p>Segunda a sexta<br>das 8h às 14h</p>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
