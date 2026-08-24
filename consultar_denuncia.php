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
    <link rel="stylesheet" href="./css/public-redesign.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="./js/index.js" defer></script>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body class="denuncia-consulta-page">
    <header>
        <nav>
            <ul>
                <li><a href="https://www.instagram.com/prefeituradepaudosferros/"><img src="./assets/img/instagram.png" alt="Instagram"></a></li>
                <li><a href="https://www.facebook.com/prefeituradepaudosferros/"><img src="./assets/img/facebook.png" alt="Facebook"></a></li>
                <li><a href="https://twitter.com/paudosferros"><img src="./assets/img/twitter.png" alt="Twitter"></a></li>
                <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros"><img src="./assets/img/youtube.png" alt="YouTube"></a></li>
            </ul>
        </nav>
        <nav class="public-top-actions" aria-label="Atalhos do serviço público">
            <a href="./index.php">Protocolo eletrônico</a>
            <a href="./consultar/index.php">Consulte seu Alvará</a>
            <a href="./termos_uso.php">Termos de uso</a>
        </nav>
        <div class="user-options">
            <p id="alter-font">Tamanho da fonte</p>
            <button type="button" data-acao="aumentar">A+</button>
            <p>|</p>
            <button type="button" data-acao="diminuir">A-</button>
            <button type="button" title="Alto contraste" aria-label="Alto contraste" data-acao="contraste"><i class="fas fa-circle-half-stroke"></i></button>
        </div>
    </header>

    <main>
        <section id="dc" class="dc-page">
            <div class="dc-hero">
                <img class="dc-logo" src="./assets/img/logo-sema-vertical-redesign.png" alt="Secretaria Municipal de Meio Ambiente">
                <div>
                    <p class="dc-kicker">Canal oficial SEMA</p>
                    <h1 class="dc-title">Acompanhar Denúncia</h1>
                    <p class="dc-sub">Consulte o andamento público pelo número de protocolo informado no registro.</p>
                </div>
            </div>

            <form method="GET" action="consultar_denuncia.php" class="dc-busca">
                <label for="protocolo" class="dc-label">Número do protocolo</label>
                <div class="dc-busca-row">
                    <input class="dc-input" id="protocolo" type="text" name="protocolo" placeholder="DEN-00000000-XXXXX"
                           value="<?= htmlspecialchars($protocolo) ?>" autocomplete="off" autofocus>
                    <button class="dc-btn" type="submit">Consultar protocolo</button>
                </div>
                <p class="dc-dica">O protocolo foi informado quando você registrou a denúncia — ex.: <strong>DEN-20260722-5E1A0</strong>.</p>
            </form>

            <?php if ($erro): ?>
                <div class="dc-erro"><span><?= htmlspecialchars($erro) ?></span></div>
            <?php endif; ?>

            <?php if ($denuncia): ?>
                <div class="dc-card">
                    <div class="dc-card-top">
                        <span class="dc-proto"><?= htmlspecialchars($denuncia['protocolo_publico']) ?></span>
                        <span class="dc-status" style="background: <?= htmlspecialchars($cor[0]) ?>; color: <?= htmlspecialchars($cor[1]) ?>; --dc-status-dot: <?= htmlspecialchars($cor[2]) ?>;"><?= htmlspecialchars($denuncia['status']) ?></span>
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
                            <span>Por segurança, esta consulta mostra apenas o andamento e os registros liberados pela fiscalização. Dados de pessoas envolvidas não são exibidos.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div style="display:block; width:100%; line-height:0; font-size:0;">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none" style="display:block; width:100%; height:70px;">
            <path d="M0,35 C360,80 1080,-10 1440,35 L1440,70 L0,70 Z" fill="#0a1a2e"/>
        </svg>
    </div>

    <footer class="dc-footer">
        <section>
            <img src="./assets/SEMA/PNG/Branca/Logo SEMA Horizontal 3.png" alt="SEMA — Secretaria Municipal de Meio Ambiente">
            <nav class="dc-footer-links" aria-label="Links da consulta pública">
                <a href="./index.php">Protocolo eletrônico</a>
                <a href="./consultar/index.php">Consulte seu Alvará</a>
                <a href="./acessibilidade.php">Acessibilidade</a>
                <a href="./termos_uso.php">Termos de uso</a>
            </nav>
            <p>© <?= date('Y') ?> Prefeitura Municipal de Pau dos Ferros — Secretaria Municipal de Meio Ambiente.</p>
        </section>
    </footer>
</body>
</html>
