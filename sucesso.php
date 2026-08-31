<?php
require_once 'includes/config.php';

if (!isset($_SESSION['protocolo'])) {
    header('Location: index.php');
    exit;
}

$protocolo    = (string) $_SESSION['protocolo'];
$sucesso      = $_SESSION['sucesso'] ?? 'Requerimento enviado com sucesso!';
$proprietario = $_SESSION['proprietario_nome'] ?? '';
$emailFalhou  = !empty($_SESSION['email_confirmacao_falhou']);
$emailDestino = $_SESSION['email_confirmacao_destino'] ?? '';

unset($_SESSION['protocolo'], $_SESSION['sucesso'], $_SESSION['proprietario_nome'], $_SESSION['email_confirmacao_falhou'], $_SESSION['email_confirmacao_destino']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Requerimento recebido — SEMA Pau dos Ferros</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/index.css">
    <link rel="stylesheet" href="./css/public-redesign.css?v=<?= (int) filemtime(__DIR__ . '/css/public-redesign.css') ?>">
    <link rel="stylesheet" href="./css/success-page.css?v=<?= (int) filemtime(__DIR__ . '/css/success-page.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body class="success-page">
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
            <a href="./consultar/index.php">Consulte seu processo</a>
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
            <h1 id="titulo-sucesso"><?= htmlspecialchars($sucesso) ?></h1>
            <p class="ds-lead">Seu requerimento foi recebido e seguirá para análise da equipe responsável.</p>

            <div class="ds-protocol">
                <span>Número de registro de entrada</span>
                <strong><?= htmlspecialchars($protocolo) ?></strong>
                <?php if ($proprietario): ?><small><i class="fas fa-user" aria-hidden="true"></i> <?= htmlspecialchars($proprietario) ?></small><?php endif; ?>
            </div>

            <?php if ($emailFalhou): ?>
                <div class="ds-detail ds-detail-error">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>Não foi possível enviar a confirmação por e-mail agora. <strong>Anote o registro acima</strong>; ele continua válido para consulta.</span>
                </div>
            <?php elseif ($emailDestino): ?>
                <div class="ds-detail">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <span>A confirmação foi enviada para <strong><?= htmlspecialchars($emailDestino) ?></strong>. Confira também o spam e o lixo eletrônico.</span>
                </div>
            <?php endif; ?>

            <ol class="ds-steps" aria-label="Próximas etapas">
                <li><span class="ds-step-number">1</span>A equipe fará a análise inicial do requerimento.</li>
                <li><span class="ds-step-number">2</span>Se houver taxa, você receberá o acesso seguro ao boleto.</li>
                <li><span class="ds-step-number">3</span>O protocolo oficial e os documentos serão enviados ao e-mail informado.</li>
            </ol>

            <div class="ds-detail ds-detail-warning">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <span>A SEMA envia mensagens somente por <strong><?= htmlspecialchars(EMAIL_FROM) ?></strong>. Na dúvida, confirme pelo WhatsApp (84) 99668-6413.</span>
            </div>

            <div class="ds-actions">
                <a href="./consultar/index.php" class="ds-button ds-button-primary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Consultar processo</a>
                <a href="https://gestor.tributosmunicipais.com.br/redesim/prefeitura/paudosferros/views/publico/portaldocontribuinte/index.xhtml" target="_blank" rel="noopener" class="ds-button"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Portal do Contribuinte</a>
                <a href="./index.php" class="ds-button"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar ao início</a>
            </div>
        </section>
    </main>

    <div class="ds-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none" style="display:block;width:100%;height:70px;"><path d="M0,35 C360,80 1080,-10 1440,35 L1440,70 L0,70 Z" fill="#0a1a2e"/></svg>
    </div>

    <footer style="background:#0a1a2e;padding:42px 24px 30px;text-align:center;">
        <section style="max-width:1180px;margin:0 auto;">
            <section class="public-footer-actions" aria-label="Atalhos principais">
                <a class="public-action-card public-action-card-green" href="./consultar/index.php"><strong>Consulte seu processo</strong><small>Acompanhe requerimentos protocolados</small></a>
                <a class="public-action-card public-action-card-yellow" href="./index.php"><strong>Novo atendimento</strong><small>Inicie outro protocolo eletrônico</small></a>
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
                    <dl><dt>Endereço</dt><dd>Rua Lafaiete Diógenes, nº 314 — São Judas Tadeu<br>Pau dos Ferros/RN · CEP 59.900-000</dd><dt>Atendimento</dt><dd>Segunda a sexta, das 7h às 17h<span class="public-footer-nota">Alguns canais e plantões atendem das 7h às 13h.</span></dd></dl>
                </div>

                <div class="public-footer-col">
                    <h3>Fale com a SEMA</h3>
                    <dl><dt>Telefone e WhatsApp</dt><dd><a href="https://wa.me/5584996686413" target="_blank" rel="noopener">(84) 99668-6413</a><span class="public-footer-nota">Atendimento e orientações.</span></dd><dt>E-mail</dt><dd><a href="mailto:sema@paudosferros.rn.gov.br">sema@paudosferros.rn.gov.br</a></dd></dl>
                </div>
            </section>

            <div class="public-footer-base">
                <nav class="public-footer-legal" aria-label="Links legais e institucionais"><a href="./acessibilidade.php">Acessibilidade</a><a href="./termos_uso.php">Termos de uso</a><a href="./privacidade.php">Avisos legais e privacidade</a></nav>
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
