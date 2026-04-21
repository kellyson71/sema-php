<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acessibilidade - SEMA</title>
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
            justify-content: center;
            padding: 32px 20px;
        }
        .card {
            width: 100%;
            max-width: 860px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(16, 35, 61, 0.08);
        }
        h1, h2 { color: var(--text); }
        h1 { margin: 0 0 10px; font-size: 2rem; }
        h2 { margin: 24px 0 8px; font-size: 1.1rem; }
        p {
            margin: 0 0 14px;
            line-height: 1.7;
            color: var(--muted);
        }
        ul { margin: 0 0 14px 18px; color: var(--muted); line-height: 1.7; }
        a { color: var(--primary); }
    </style>
</head>
<body>
    <main>
        <section class="card">
            <h1>Acessibilidade</h1>
            <p>A Secretaria Municipal de Meio Ambiente busca manter o sistema com navegação clara, contraste adequado e uso compatível com teclado e leitores de tela nas principais interações.</p>

            <h2>Medidas adotadas</h2>
            <ul>
                <li>Estrutura semântica nas telas principais.</li>
                <li>Rótulos visíveis nos campos de autenticação.</li>
                <li>Feedback textual para erros de validação e autenticação.</li>
                <li>Compatibilidade com navegação por teclado nos fluxos de login e verificação.</li>
            </ul>

            <h2>Relato de dificuldade</h2>
            <p>Se você encontrar uma barreira de acessibilidade, informe o problema para a equipe de suporte pelo e-mail <a href="mailto:ti@paudosferros.rn.gov.br">ti@paudosferros.rn.gov.br</a> ou pelo ramal 2104.</p>
        </section>
    </main>
</body>
</html>
