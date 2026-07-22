<?php
/**
 * Acompanhamento público de denúncia por protocolo.
 *
 * O denunciante digita o protocolo (DEN-AAAAMMDD-XXXXX) e vê apenas o que a
 * fiscalização liberou: status, as medidas marcadas como visíveis e as fotos
 * marcadas como visíveis. NUNCA expõe dados do infrator nem do denunciante,
 * observações internas ou anexos internos.
 */
require_once __DIR__ . '/includes/config.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: http://sema.paudosferros.rn.gov.br' . $requestUri);
    exit;
}

$tiposLegiveis = [
    'obstrucao_via'        => 'Obstrução de via',
    'terreno_sujo'         => 'Terreno sujo',
    'terreno_baldio'       => 'Terreno baldio',
    'esgoto_via'           => 'Esgoto em via pública',
    'construcao_irregular' => 'Construção irregular',
    'entulho_construcao'   => 'Entulho em construção civil',
    'entulho_via'          => 'Entulho em via pública',
    'outros'               => 'Outros',
];

$protocolo = strtoupper(trim($_GET['protocolo'] ?? ''));
$denuncia  = null;
$historico = [];
$anexos    = [];
$tipos     = [];
$erro      = '';
$buscou    = ($protocolo !== '');

if ($buscou) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        // Só denúncias com protocolo público (feitas pelo site) são consultáveis.
        $stmt = $pdo->prepare("
            SELECT id, data_registro, tipo_denuncia, status, protocolo_publico
            FROM denuncias
            WHERE protocolo_publico = ?
            LIMIT 1
        ");
        $stmt->execute([$protocolo]);
        $denuncia = $stmt->fetch();

        if ($denuncia) {
            $decodificados = json_decode($denuncia['tipo_denuncia'] ?? '[]', true);
            if (is_array($decodificados)) {
                foreach ($decodificados as $slug) {
                    $tipos[] = $tiposLegiveis[$slug] ?? ucwords(str_replace('_', ' ', $slug));
                }
            }

            // Apenas andamentos liberados ao denunciante.
            $h = $pdo->prepare("
                SELECT detalhes, data_registro
                FROM denuncia_historico
                WHERE denuncia_id = ? AND visivel_denunciante = 1
                ORDER BY data_registro ASC
            ");
            $h->execute([$denuncia['id']]);
            $historico = $h->fetchAll();

            // Apenas anexos liberados ao denunciante.
            $a = $pdo->prepare("
                SELECT id, nome_arquivo, tipo_arquivo, descricao
                FROM denuncia_anexos
                WHERE denuncia_id = ? AND visivel_denunciante = 1
                ORDER BY data_upload ASC
            ");
            $a->execute([$denuncia['id']]);
            $anexos = $a->fetchAll();
        } else {
            $erro = 'Nenhuma denúncia encontrada para este protocolo. Confira o número e tente novamente.';
        }
    } catch (Throwable $e) {
        error_log('[consultar_denuncia] ' . $e->getMessage());
        $erro = 'Não foi possível consultar agora. Tente novamente em instantes.';
    }
}

function corStatus(string $status): array
{
    // [bg, texto, ponto]
    $mapa = [
        'Pendente'   => ['#fff8e1', '#8a6d00', '#f2b705'],
        'Em Análise' => ['#e8f0fe', '#1a56c4', '#1a73e8'],
        'Concluída'  => ['#eafaf0', '#12894b', '#009640'],
    ];
    return $mapa[$status] ?? ['#eef1f5', '#475467', '#98a1af'];
}
$cor = $denuncia ? corStatus($denuncia['status']) : corStatus('');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Acompanhar Denúncia — Secretaria Municipal de Meio Ambiente</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Viga&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tudo escopado em #dc para não colidir com as regras globais de css/index.css */
        #dc { max-width: 640px; margin: 0 auto; padding: 44px 16px 72px; font-family: Roboto, sans-serif; }

        #dc .dc-hero { text-align: center; margin-bottom: 26px; }
        #dc .dc-logo { height: 84px; width: auto; margin: 0 auto 18px; display: block; }
        #dc .dc-title { font-family: Viga, sans-serif; color: #fff; font-size: 1.9rem; margin: 0 0 6px; letter-spacing: .5px; }
        #dc .dc-sub { color: rgba(255,255,255,.72); font-size: .95rem; margin: 0; }

        /* Painel de busca — vidro translúcido sobre o azul, como o resto do site */
        #dc .dc-busca { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
            border-radius: 16px; padding: 20px; backdrop-filter: blur(8px); }
        #dc .dc-busca-row { display: flex; gap: 10px; flex-wrap: wrap; }
        #dc .dc-input { flex: 1; min-width: 200px; padding: 14px 16px; border: 0; border-radius: 10px;
            background: #fff; color: #1e293b; font-size: 1rem; font-family: monospace; letter-spacing: 1px; }
        #dc .dc-input:focus { outline: 3px solid rgba(0,150,64,.4); }
        #dc .dc-input::placeholder { color: #9aa4b2; letter-spacing: .5px; }
        #dc .dc-btn { border: 0; border-radius: 10px; background: #009640; color: #fff; padding: 0 22px;
            font-size: .95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center;
            gap: 8px; transition: background .2s, transform .1s; min-height: 48px; }
        #dc .dc-btn:hover { background: #007a33; }
        #dc .dc-btn:active { transform: translateY(1px); }
        #dc .dc-dica { color: rgba(255,255,255,.6); font-size: .8rem; margin: 12px 2px 0; }
        #dc .dc-dica strong { color: rgba(255,255,255,.85); font-family: monospace; }

        #dc .dc-erro { margin-top: 18px; background: rgba(220,38,38,.14); border: 1px solid rgba(248,113,113,.4);
            color: #ffe1e1; border-radius: 12px; padding: 13px 16px; font-size: .9rem; display: flex; gap: 10px; align-items: flex-start; }

        /* Card branco de resultado */
        #dc .dc-card { margin-top: 22px; background: #fff; border-radius: 20px; overflow: hidden;
            box-shadow: 0 18px 50px rgba(3,20,50,.32); }
        #dc .dc-card-top { padding: 22px 26px; border-bottom: 1px solid #eef1f5;
            display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        #dc .dc-proto { font-family: monospace; font-weight: 700; font-size: 1.1rem; color: #0f172a; letter-spacing: 1px; }
        #dc .dc-status { font-size: .8rem; font-weight: 700; padding: 6px 14px; border-radius: 999px;
            background: <?= $cor[0] ?>; color: <?= $cor[1] ?>; display: inline-flex; align-items: center; gap: 7px; }
        #dc .dc-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: <?= $cor[2] ?>; }

        #dc .dc-card-body { padding: 22px 26px 26px; }
        #dc .dc-meta { display: flex; flex-wrap: wrap; gap: 6px 22px; margin-bottom: 8px; }
        #dc .dc-meta div { font-size: .86rem; color: #475467; }
        #dc .dc-meta strong { color: #0f172a; }

        #dc .dc-sectitle { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: #94a3b8; margin: 24px 0 14px; }

        #dc .dc-tl { position: relative; margin-left: 5px; padding-left: 22px; border-left: 2px solid #e5eaf0; }
        #dc .dc-tl-item { position: relative; padding-bottom: 18px; }
        #dc .dc-tl-item:last-child { padding-bottom: 0; }
        #dc .dc-tl-item::before { content: ''; position: absolute; left: -29px; top: 3px; width: 13px; height: 13px;
            border-radius: 50%; background: #009640; border: 2.5px solid #fff; box-shadow: 0 0 0 2px #cfe9d8; }
        #dc .dc-tl-data { font-size: .74rem; color: #94a3b8; margin-bottom: 3px; }
        #dc .dc-tl-texto { font-size: .92rem; color: #334155; line-height: 1.55; white-space: pre-wrap; }

        #dc .dc-vazio { text-align: center; color: #64748b; font-size: .9rem; padding: 18px;
            background: #f6f9fc; border: 1px dashed #d6dee8; border-radius: 12px; }

        #dc .dc-fotos { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        #dc .dc-foto { display: block; border-radius: 14px; overflow: hidden; border: 1px solid #e6eaf0; aspect-ratio: 4/3; }
        #dc .dc-foto img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .25s; }
        #dc .dc-foto:hover img { transform: scale(1.05); }
        #dc .dc-arquivo { display: flex; align-items: center; gap: 11px; padding: 12px 14px; border: 1px solid #e6eaf0;
            border-radius: 12px; text-decoration: none; color: #334155; font-size: .9rem; margin-bottom: 8px; transition: background .15s; }
        #dc .dc-arquivo:hover { background: #f6f9fc; }
        #dc .dc-arquivo i { color: #009640; font-size: 1.05rem; }

        #dc .dc-priv { margin-top: 22px; padding-top: 16px; border-top: 1px solid #eef1f5;
            font-size: .78rem; color: #94a3b8; display: flex; gap: 9px; align-items: flex-start; }
        #dc .dc-priv i { color: #009640; margin-top: 2px; }

        @media (max-width: 480px) {
            #dc .dc-title { font-size: 1.5rem; }
            #dc .dc-btn { width: 100%; justify-content: center; }
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
        <div id="dc">
            <div class="dc-hero">
                <img class="dc-logo" src="./assets/img/Logo_sema.png" alt="Secretaria Municipal de Meio Ambiente">
                <h1 class="dc-title">Acompanhar Denúncia</h1>
                <p class="dc-sub">Consulte o andamento da sua denúncia pelo número de protocolo.</p>
            </div>

            <form method="GET" action="consultar_denuncia.php" class="dc-busca">
                <div class="dc-busca-row">
                    <input class="dc-input" type="text" name="protocolo" placeholder="DEN-00000000-XXXXX"
                           value="<?= htmlspecialchars($protocolo) ?>" autocomplete="off" autofocus>
                    <button class="dc-btn" type="submit"><i class="fas fa-magnifying-glass"></i> Consultar</button>
                </div>
                <p class="dc-dica">O protocolo foi informado quando você registrou a denúncia — ex.: <strong>DEN-20260722-5E1A0</strong>.</p>
            </form>

            <?php if ($erro): ?>
                <div class="dc-erro"><i class="fas fa-circle-exclamation" style="margin-top:2px;"></i><span><?= htmlspecialchars($erro) ?></span></div>
            <?php endif; ?>

            <?php if ($denuncia): ?>
                <div class="dc-card">
                    <div class="dc-card-top">
                        <span class="dc-proto"><?= htmlspecialchars($denuncia['protocolo_publico']) ?></span>
                        <span class="dc-status"><?= htmlspecialchars($denuncia['status']) ?></span>
                    </div>
                    <div class="dc-card-body">
                        <div class="dc-meta">
                            <?php if ($tipos): ?><div><strong>Tipo:</strong> <?= htmlspecialchars(implode(', ', $tipos)) ?></div><?php endif; ?>
                            <div><strong>Registrada em:</strong> <?= date('d/m/Y', strtotime($denuncia['data_registro'])) ?></div>
                        </div>

                        <div class="dc-sectitle">Andamento</div>
                        <?php if ($historico): ?>
                            <div class="dc-tl">
                                <?php foreach (array_reverse($historico) as $item): ?>
                                    <div class="dc-tl-item">
                                        <div class="dc-tl-data"><?= date('d/m/Y \à\s H:i', strtotime($item['data_registro'])) ?></div>
                                        <div class="dc-tl-texto"><?= htmlspecialchars($item['detalhes']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dc-vazio">A denúncia foi recebida e está na fila de análise. Assim que houver providências, elas aparecerão aqui.</div>
                        <?php endif; ?>

                        <?php if ($anexos): ?>
                            <div class="dc-sectitle">Registros da fiscalização</div>
                            <?php
                                $imagens = array_filter($anexos, fn($x) => in_array(strtolower($x['tipo_arquivo']), ['jpg', 'jpeg', 'png']));
                                $outros  = array_filter($anexos, fn($x) => !in_array(strtolower($x['tipo_arquivo']), ['jpg', 'jpeg', 'png']));
                            ?>
                            <?php if ($imagens): ?>
                                <div class="dc-fotos">
                                    <?php foreach ($imagens as $img): ?>
                                        <a class="dc-foto" href="anexo_denuncia_publico.php?id=<?= (int) $img['id'] ?>" target="_blank" rel="noopener">
                                            <img src="anexo_denuncia_publico.php?id=<?= (int) $img['id'] ?>" alt="<?= htmlspecialchars($img['descricao'] ?: 'Foto da fiscalização') ?>" loading="lazy">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($outros as $arq): ?>
                                <a class="dc-arquivo" href="anexo_denuncia_publico.php?id=<?= (int) $arq['id'] ?>&download=1" target="_blank" rel="noopener">
                                    <i class="fas fa-file-arrow-down"></i>
                                    <?= htmlspecialchars($arq['descricao'] ?: $arq['nome_arquivo']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="dc-priv">
                            <i class="fas fa-shield-halved"></i>
                            <span>Por segurança, esta consulta mostra apenas o andamento e os registros liberados pela fiscalização. Dados de pessoas envolvidas não são exibidos.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
        function increaseFont() { document.body.style.fontSize = parseInt(window.getComputedStyle(document.body).fontSize) + 1 + 'px'; }
        function decreaseFont() { document.body.style.fontSize = parseInt(window.getComputedStyle(document.body).fontSize) - 1 + 'px'; }
    </script>
</body>
</html>
