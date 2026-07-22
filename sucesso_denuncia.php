<?php
require_once 'includes/config.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: http://sema.paudosferros.rn.gov.br' . $requestUri);
    exit;
}

session_start();

if (empty($_SESSION['denuncia_protocolo'])) {
    header('Location: index.php');
    exit;
}

$protocolo = $_SESSION['denuncia_protocolo'];
$anonimo   = !empty($_SESSION['denuncia_anonimo']);

unset($_SESSION['denuncia_protocolo'], $_SESSION['denuncia_anonimo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denúncia Registrada — Secretaria Municipal de Meio Ambiente</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        main {
            min-height: calc(100vh - 48px - 234px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            padding: 32px 32px 24px;
            text-align: center;
        }
        .card-header .icone {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .card-header .icone i { font-size: 2rem; color: #fff; }
        .card-header h1 { color: #fff; font-size: 1.4rem; margin: 0; font-weight: 700; }
        .card-header p { color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: .95rem; }
        .card-body { padding: 28px 32px; }
        .protocolo-box {
            background: #fef2f2;
            border: 2px dashed #dc2626;
            border-radius: 8px;
            padding: 18px 20px;
            text-align: center;
            margin-bottom: 24px;
        }
        .protocolo-box .label {
            font-size: .8rem; color: #6c757d;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
        }
        .protocolo-box .numero {
            font-size: 1.55rem; font-weight: 700;
            color: #991b1b; letter-spacing: 2px; font-family: monospace;
        }
        .passos { list-style: none; padding: 0; margin: 0 0 24px; }
        .passos li {
            display: flex; gap: 12px; align-items: flex-start;
            padding: 10px 0; border-bottom: 1px solid #f0f0f0;
            font-size: .9rem; color: #444; line-height: 1.5;
        }
        .passos li:last-child { border-bottom: none; }
        .passos li .num {
            width: 24px; height: 24px; border-radius: 50%;
            background: #b91c1c; color: #fff; font-size: .75rem;
            font-weight: 700; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0; margin-top: 1px;
        }
        .aviso {
            background: #fff8e1; border-left: 4px solid #f9a825;
            border-radius: 0 6px 6px 0; padding: 12px 16px;
            font-size: .875rem; color: #5d4037; margin-bottom: 24px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .aviso i { color: #f9a825; margin-top: 2px; flex-shrink: 0; }
        .botoes { display: flex; gap: 12px; flex-wrap: wrap; }
        .botao {
            flex: 1; min-width: 140px;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 11px 18px; border-radius: 6px;
            font-size: .9rem; font-weight: 600; text-decoration: none; transition: filter .2s;
        }
        .botao:hover { filter: brightness(.9); }
        .botao-primario { background: #b91c1c; color: #fff; }
        .botao-secundario { background: #e9ecef; color: #495057; }
        @media (max-width: 480px) {
            .card-body { padding: 20px 18px; }
            .card-header { padding: 24px 18px 18px; }
            .protocolo-box .numero { font-size: 1.25rem; }
        }
    </style>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="https://www.instagram.com/prefeituradepaudosferros/"><img src="./assets/img/instagram.png" alt="Instagram"></a></li>
                <li><a href="https://www.facebook.com/prefeituradepaudosferros/"><img src="./assets/img/facebook.png" alt="Facebook"></a></li>
                <li><a href="https://twitter.com/paudosferros"><img src="./assets/img/twitter.png" alt="Twitter"></a></li>
                <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros"><img src="./assets/img/youtube.png" alt="YouTube"></a></li>
            </ul>
        </nav>
        <div class="user-options">
            <p id="alter-font">Tamanho da fonte</p>
            <button onclick="increaseFont()">A+</button>
            <p>|</p>
            <button onclick="decreaseFont()">A-</button>
        </div>
    </header>

    <main>
        <div class="card">
            <div class="card-header">
                <div class="icone"><i class="fas fa-check"></i></div>
                <h1>Denúncia Registrada!</h1>
                <p>Sua denúncia foi recebida pela Secretaria Municipal de Meio Ambiente.</p>
            </div>

            <div class="card-body">
                <div class="protocolo-box">
                    <div class="label">Protocolo da Denúncia</div>
                    <div class="numero"><?= htmlspecialchars($protocolo) ?></div>
                </div>

                <?php if ($anonimo): ?>
                <div class="aviso" style="background:#f0faf4;border-left-color:#009640;color:#14532d;">
                    <i class="fas fa-shield-halved" style="color:#009640;"></i>
                    <span>Sua denúncia foi registrada de forma <strong>anônima</strong>. Sua identidade não foi armazenada no sistema.</span>
                </div>
                <?php endif; ?>

                <ul class="passos">
                    <li>
                        <span class="num">1</span>
                        <span>Anote o protocolo acima para referência futura.</span>
                    </li>
                    <li>
                        <span class="num">2</span>
                        <span>A equipe da SEMA analisará a ocorrência e tomará as providências cabíveis.</span>
                    </li>
                    <li>
                        <span class="num">3</span>
                        <span>Acompanhe o andamento a qualquer momento com este protocolo, no botão abaixo.</span>
                    </li>
                </ul>

                <div class="botoes">
                    <a href="consultar_denuncia.php?protocolo=<?= urlencode($protocolo) ?>" class="botao botao-primario">
                        <i class="fas fa-magnifying-glass-location"></i> Acompanhar Denúncia
                    </a>
                    <a href="index.php" class="botao botao-secundario">
                        <i class="fas fa-arrow-left"></i> Voltar ao Início
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div>
            <div>
                <a href="./consultar/index.php" class="consulta-btn">
                    <i class="fas fa-search"></i>
                    <span>Consulte seu Alvará</span>
                </a>
            </div>
            <div>
                <img src="./assets/img/phone.png" alt="Telefone">
                WhatsApp (84) 99668-6413
            </div>
            <div>
                <img src="./assets/img/email.png" alt="Email">
                fiscalizacaosemapdf@gmail.com
            </div>
        </div>
        <div>
            <span>
                © 2023 - Todos os direitos reservados. Programa da&ensp;<a href="https://www.paudosferros.rn.gov.br/">Prefeitura de Pau dos Ferros</a>
                <p>Desenvolvido por&ensp;<a href="https://github.com/kellyson71" style="text-decoration: none; color: inherit;">Kellyson Raphael</a></p>
            </span>
            <div>
                <img src="./assets/img/Logo.png" alt="SEAD">
            </div>
        </div>
    </footer>

    <script>
        function increaseFont() {
            document.body.style.fontSize = parseInt(window.getComputedStyle(document.body).fontSize) + 1 + 'px';
        }
        function decreaseFont() {
            document.body.style.fontSize = parseInt(window.getComputedStyle(document.body).fontSize) - 1 + 'px';
        }
    </script>
</body>
</html>
