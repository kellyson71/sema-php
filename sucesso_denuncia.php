<?php
require_once 'includes/config.php';

if (empty($_SESSION['denuncia_enviada'])) {
    header('Location: index.php');
    exit;
}

$protocolo = (string) ($_SESSION['denuncia_protocolo'] ?? '');
$anonimo   = !empty($_SESSION['denuncia_anonimo']);
$consultaUrl = rtrim(BASE_URL, '/') . '/consultar_denuncia.php';

unset($_SESSION['denuncia_enviada'], $_SESSION['denuncia_protocolo'], $_SESSION['denuncia_anonimo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Denúncia registrada — SEMA Pau dos Ferros</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/index.css">
    <link rel="stylesheet" href="./css/public-redesign.css?v=<?= (int) filemtime(__DIR__ . '/css/public-redesign.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .denuncia-sucesso-page { min-height: 100vh; display: flex; flex-direction: column; background: #f8f8f8; }
        .ds-brand { padding: 28px 20px 24px; border-bottom: 3px solid #009640; background: #fff; text-align: center; }
        .ds-brand img { width: min(480px, 78vw); height: auto; }
        .ds-brand p { margin: 8px 0 0; color: #009640; font-size: .8rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .ds-main { flex: 1; display: grid; place-items: center; width: 100%; padding: 54px 20px 70px; }
        .ds-card { width: min(720px, 100%); padding: 42px; border: 1px solid #d9dedb; border-top: 5px solid #009640; border-radius: 16px; background: #fff; box-shadow: 0 10px 32px rgba(15, 23, 42, .07); text-align: center; }
        .ds-success-icon { display: grid; place-items: center; width: 68px; height: 68px; margin: 0 auto 18px; border-radius: 50%; background: #eaf8f0; color: #009640; font-size: 1.7rem; }
        .ds-eyebrow { display: block; margin-bottom: 7px; color: #007a33; font-size: .72rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .ds-card h1 { margin: 0; color: #26322c; font-size: clamp(1.65rem, 4vw, 2.2rem); line-height: 1.2; }
        .ds-lead { max-width: 52ch; margin: 12px auto 28px; color: #6b7280; font-size: .98rem; line-height: 1.6; }
        .ds-detail { display: flex; align-items: flex-start; gap: 13px; margin: 0 0 28px; padding: 16px 18px; border: 1px solid #cbe8d8; border-radius: 12px; background: #f0fdf4; color: #315c43; text-align: left; font-size: .9rem; line-height: 1.55; }
        .ds-detail i { margin-top: 3px; color: #009640; font-size: 1.05rem; }
        .ds-protocol { margin: 0 0 24px; padding: 17px 20px; border: 1px dashed #70bd91; border-radius: 12px; background: #f7fcf9; }
        .ds-protocol span { display: block; margin-bottom: 5px; color: #6b7280; font-size: .7rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .ds-protocol strong { color: #007a33; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: clamp(1.15rem, 4vw, 1.55rem); letter-spacing: .06em; word-break: break-word; }
        .ds-actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .ds-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 11px 18px; border: 1px solid #d9dedb; border-radius: 9px; color: #33413a; font-size: .88rem; font-weight: 700; text-decoration: none; transition: border-color .18s ease, background .18s ease; }
        .ds-button:hover, .ds-button:focus-visible { border-color: #009640; background: #f5fbf7; outline: none; }
        .ds-button-primary { border-color: #009640; background: #009640; color: #fff; }
        .ds-button-primary:hover, .ds-button-primary:focus-visible { background: #007a33; color: #fff; }
        .ds-wave { display: block; width: 100%; margin-top: -35px; line-height: 0; }
        .ds-footer-grid { grid-template-columns: minmax(0, .9fr) minmax(0, 1fr) minmax(0, 1fr); }
        @media (max-width: 720px) {
            .ds-main { padding: 36px 16px 64px; }
            .ds-card { padding: 30px 20px; }
            .ds-brand { padding: 22px 16px 19px; }
            .ds-actions { align-items: stretch; flex-direction: column; }
            .ds-button { width: 100%; }
            .ds-footer-grid { grid-template-columns: 1fr; }
        }
    </style>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body class="denuncia-sucesso-page">
    <header>
        <nav aria-label="Redes sociais">
            <ul>
                <li><a href="https://www.instagram.com/prefeituradepaudosferros/" target="_blank" rel="noopener"><img src="./assets/img/instagram.png" alt="Instagram"></a></li>
                <li><a href="https://www.facebook.com/prefeituradepaudosferros/" target="_blank" rel="noopener"><img src="./assets/img/facebook.png" alt="Facebook"></a></li>
                <li><a href="https://twitter.com/paudosferros" target="_blank" rel="noopener"><img src="./assets/img/twitter.png" alt="Twitter"></a></li>
                <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros" target="_blank" rel="noopener"><img src="./assets/img/youtube.png" alt="YouTube"></a></li>
                <li><a href="https://paudosferros.rn.gov.br" target="_blank" rel="noopener"><img src="./assets/img/copy-url.png" alt="Portal da Prefeitura"></a></li>
            </ul>
        </nav>

        <nav class="public-top-actions" aria-label="Atalhos do serviço público">
            <a href="./index.php">Novo atendimento</a>
            <?php if (!$anonimo): ?><a href="./consultar_denuncia.php">Acompanhe sua denúncia</a><?php endif; ?>
            <a href="./termos_uso.php">Termos de uso</a>
        </nav>

        <div class="user-options">
            <p id="alter-font">Tamanho da fonte</p>
            <button type="button" onclick="alterarFonte(1)" aria-label="Aumentar fonte">A+</button>
            <button type="button" onclick="alterarFonte(-1)" aria-label="Diminuir fonte">A-</button>
        </div>
    </header>

    <section class="ds-brand" aria-label="Identificação institucional">
        <img src="./assets/img/logo-prefeitura-sema-horizontal.png" alt="Prefeitura de Pau dos Ferros — Secretaria Municipal do Meio Ambiente">
        <p>Protocolo eletrônico · Alvarás · Denúncias · Autorizações</p>
    </section>

    <main class="ds-main">
        <section class="ds-card" aria-labelledby="titulo-sucesso">
            <div class="ds-success-icon" aria-hidden="true"><i class="fas fa-check"></i></div>
            <span class="ds-eyebrow">Envio concluído</span>
            <h1 id="titulo-sucesso">Denúncia registrada</h1>
            <p class="ds-lead">A Secretaria Municipal do Meio Ambiente recebeu as informações e fará a análise da ocorrência.</p>

            <?php if ($anonimo): ?>
                <div class="ds-detail">
                    <i class="fas fa-user-secret" aria-hidden="true"></i>
                    <span>A denúncia foi enviada de forma <strong>anônima</strong>. Nenhum dado pessoal foi armazenado e não será possível acompanhar o andamento.</span>
                </div>
            <?php else: ?>
                <div class="ds-protocol">
                    <span>Protocolo da denúncia</span>
                    <strong><?= htmlspecialchars($protocolo) ?></strong>
                </div>
                <div class="ds-detail">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    <span>Guarde o protocolo. Consulte o andamento em <a href="<?= htmlspecialchars($consultaUrl) ?>"><strong><?= htmlspecialchars($consultaUrl) ?></strong></a>.</span>
                </div>
            <?php endif; ?>

            <div class="ds-actions">
                <?php if (!$anonimo): ?>
                    <a href="consultar_denuncia.php?protocolo=<?= urlencode($protocolo) ?>" class="ds-button ds-button-primary">
                        <i class="fas fa-magnifying-glass-location" aria-hidden="true"></i> Acompanhar denúncia
                    </a>
                <?php endif; ?>
                <a href="index.php" class="ds-button<?= $anonimo ? ' ds-button-primary' : '' ?>">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar ao início
                </a>
            </div>
        </section>
    </main>

    <div class="ds-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none" style="display:block;width:100%;height:70px;">
            <path d="M0,35 C360,80 1080,-10 1440,35 L1440,70 L0,70 Z" fill="#0a1a2e"/>
        </svg>
    </div>

    <footer style="background:#0a1a2e;padding:42px 24px 30px;text-align:center;">
        <section style="max-width:1180px;margin:0 auto;">
            <section class="public-footer-actions" aria-label="Atalhos principais">
                <a class="public-action-card public-action-card-green" href="./index.php"><strong>Novo atendimento</strong><small>Inicie outro protocolo eletrônico</small></a>
                <a class="public-action-card public-action-card-yellow" href="./consultar/index.php"><strong>Consulte seu Alvará</strong><small>Acompanhe processos protocolados</small></a>
                <a class="public-action-card public-action-card-blue" href="./termos_uso.php"><strong>Termos de uso</strong><small>Consulte regras e responsabilidades</small></a>
                <a class="public-action-card public-action-card-cyan" href="./privacidade.php"><strong>Privacidade</strong><small>Saiba como os dados são tratados</small></a>
            </section>

            <section class="public-footer-grid ds-footer-grid" aria-label="Endereço e contatos da SEMA">
                <div class="public-footer-marca">
                    <img src="./assets/SEMA/PNG/Branca/Logo SEMA Horizontal 3.png" alt="SEMA — Secretaria Municipal de Meio Ambiente">
                    <p>Canal oficial de protocolo eletrônico de alvarás, licenças, autorizações e denúncias ambientais de Pau dos Ferros/RN.</p>
                    <p class="public-footer-remetente"><i class="fas fa-shield-alt" aria-hidden="true"></i>Avisos oficiais partem só de <a href="mailto:<?= htmlspecialchars(EMAIL_FROM) ?>"><?= htmlspecialchars(EMAIL_FROM) ?></a>. <a href="./termos_uso.php">Como identificar golpes</a></p>
                </div>

                <div class="public-footer-col">
                    <h3>Onde nos encontrar</h3>
                    <dl>
                        <dt>Endereço</dt><dd>Rua Lafaiete Diógenes, nº 314 — São Judas Tadeu<br>Pau dos Ferros/RN · CEP 59.900-000</dd>
                        <dt>Atendimento</dt><dd>Segunda a sexta, das 7h às 17h<span class="public-footer-nota">Alguns canais e plantões atendem das 7h às 13h.</span></dd>
                    </dl>
                </div>

                <div class="public-footer-col">
                    <h3>Fale com a SEMA</h3>
                    <dl>
                        <dt>Telefone e WhatsApp</dt><dd><a href="https://wa.me/5584996686413" target="_blank" rel="noopener">(84) 99668-6413</a><span class="public-footer-nota">Denúncias e fiscalização.</span></dd>
                        <dt>E-mail</dt><dd><a href="mailto:sema@paudosferros.rn.gov.br">sema@paudosferros.rn.gov.br</a><span class="public-footer-nota">Fiscalização: <a href="mailto:fiscalizacaosemapdf@gmail.com">fiscalizacaosemapdf@gmail.com</a></span></dd>
                    </dl>
                </div>
            </section>

            <div class="public-footer-base">
                <nav class="public-footer-legal" aria-label="Links legais e institucionais">
                    <a href="./acessibilidade.php">Acessibilidade</a><a href="./termos_uso.php">Termos de uso</a><a href="./privacidade.php">Avisos legais e privacidade</a>
                </nav>
                <p class="public-footer-copy">&copy; <?= date('Y') ?> <strong>Prefeitura Municipal de Pau dos Ferros</strong> — Todos os direitos reservados.<span>Desenvolvido por <a href="https://github.com/kellyson71" target="_blank" rel="noopener">Kellyson Raphael</a></span></p>
            </div>
        </section>
    </footer>

    <div style="width:100%;height:50px;background:url('./assets/img/faixa.png') repeat-x center / auto 100%;line-height:0;font-size:0;"></div>

    <script>
        function alterarFonte(delta) {
            const atual = parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;
            document.documentElement.style.fontSize = Math.max(13, Math.min(22, atual + delta)) + 'px';
        }
    </script>
</body>
</html>
