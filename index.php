<?php
// Inclui configurações antes de qualquer redirecionamento
include_once 'includes/config.php';
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Inclui o arquivo com os tipos de alvará
include_once 'tipos_alvara.php';
// Inclui tabela de enquadramento CONEMA para licenciamento ambiental
include_once 'enquadramento_conema.php';
include_once 'includes/public_form_components.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tipoRules = [];
foreach ($tipos_alvara as $slug => $tipo) {
    $isAmbiental = ($tipo['categoria'] ?? '') === 'ambiental';
    // Os documentos são a fonte de verdade: quando a lista obrigatória
    // menciona ART/RRT, o cadastro do responsável técnico é obrigatório.
    $documentosObrigatoriosTipo = array_values(array_filter((array) ($tipo['documentos'] ?? []), 'is_string'));
    $exigeResponsavelTecnico = (bool) preg_match('/\bART(?:s)?\b|\bRRT(?:s)?\b|respons[aá]vel t[eé]cnico/i', implode(' ', $documentosObrigatoriosTipo));
    $tipoRules[$slug] = [
        'categoria' => $tipo['categoria'] ?? '',
        'ambiental' => $isAmbiental,
        'exige_diario_oficial' => $isAmbiental && ($tipo['exige_diario_oficial'] ?? true),
        'exige_ctf' => (bool) ($tipo['exige_ctf'] ?? false),
        'exige_licenca_anterior' => (bool) ($tipo['exige_licenca_anterior'] ?? false),
        'exige_responsavel_tecnico' => $exigeResponsavelTecnico,
        'limite_upload' => $isAmbiental ? MAX_FILE_SIZE_AMBIENTAL : MAX_FILE_SIZE,
        'limite_upload_label' => '100MB',
    ];
}

$categoriasPublicas = [
    'obras'              => 'Obras e Construção',
    'ambiental'          => 'Licenças Ambientais',
    'outro'              => 'Outros Serviços',
    'denuncia_autoriza'  => 'Denúncias e Autorizações',
];

$tiposAlvaraPublicos = [];
foreach ($categoriasPublicas as $catSlug => $catNome) {
    $tiposDaCategoria = [];
    foreach ($tipos_alvara as $slug => $tipo) {
        if (($tipo['categoria'] ?? '') !== $catSlug || !empty($tipo['oculto'])) {
            continue;
        }
        $tiposDaCategoria[] = [
            'slug' => $slug,
            'nome' => $tipo['nome'],
            'desabilitado' => !empty($tipo['desabilitado']),
        ];
    }
    if ($tiposDaCategoria) {
        $tiposAlvaraPublicos[] = [
            'slug' => $catSlug,
            'nome' => $catNome,
            'tipos' => $tiposDaCategoria,
        ];
    }
}

ob_start();
foreach ($enquadramento_conema as $cat): ?>
<optgroup label="<?= htmlspecialchars($cat['titulo'], ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($cat['atividades'] as $slug => $ativ): ?>
    <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ativ['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($ativ['potencial'], ENT_QUOTES, 'UTF-8') ?>)</option>
    <?php endforeach; ?>
</optgroup>
<?php endforeach;
$enquadramentoOptionsHtml = ob_get_clean();

$locationTemplates = [
    'denunciante_endereco' => renderLocationComposer('denunciante_endereco', 'Seu endereço', false),
    'proprietario_endereco' => renderLocationComposer('proprietario_endereco', 'Endereço / local da ocorrência', false),
    'localizacao_area' => renderLocationComposer('localizacao_area', 'Localização da área'),
    'endereco_imovel' => renderLocationComposer('endereco_imovel', 'Endereço do imóvel'),
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Requerimento de Alvará - Secretaria Municipal de Meio Ambiente</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="./assets/SEMA/PNG/Branca/Logo SEMA Vertical 3.png">

    <meta name="description"
        content="Requerimento de Alvará Ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta name="keywords"
        content="alvará ambiental, meio ambiente, Pau dos Ferros, prefeitura, licenciamento ambiental, SEMA, requerimento">
    <meta name="author" content="Prefeitura de Pau dos Ferros">

    <meta property="og:title" content="Requerimento de Alvará - SEMA Pau dos Ferros">
    <meta property="og:description"
        content="Requerimento de Alvará Ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta property="og:image" content="./assets/img/prefeitura-logo.png">
    <meta property="og:url" content="https://www.paudosferros.rn.gov.br/sema">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Requerimento de Alvará - Secretaria Municipal de Meio Ambiente">
    <meta name="twitter:description"
        content="Requerimento de Alvará Ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta name="twitter:image" content="./assets/img/prefeitura-logo.png">

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-W3WFKPD3BN"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-W3WFKPD3BN');
    </script>

    <!-- CSS -->
    <link rel="stylesheet" href="./css/index.css">
    <link rel="stylesheet" href="./css/public-redesign.css?v=<?= (int) filemtime(__DIR__ . '/css/public-redesign.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="./js/index.js" defer></script>
    <script src="./js/public-form.js?v=<?= (int) filemtime(__DIR__ . '/js/public-form.js') ?>" defer></script>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>

<body>
    <div class="feedback" id="feedback"></div>

    <?php if (defined('MODO_HOMOLOG') && MODO_HOMOLOG): ?>
    <!-- Banner de Homologação -->
    <div style="
        background: #f59e0b;
        color: #1f2937;
        text-align: center;
        padding: 4px 12px;
        font-weight: 600;
        font-size: 0.72rem;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 9999;
        text-transform: uppercase;
        letter-spacing: 1px;
        pointer-events: none;
    ">
        Ambiente de Homologação
    </div>
    <div style="height: 22px;"></div> <!-- Espaçador para o banner fixo -->
    <?php endif; ?>
    <header>
        <nav>
            <ul>
                <li><a href="https://www.instagram.com/prefeituradepaudosferros/">
                        <img src="./assets/img/instagram.png" alt="Instagram">
                    </a>
                </li>
                <li><a href="https://www.facebook.com/prefeituradepaudosferros/">
                        <img src="./assets/img/facebook.png" alt="Facebook">
                    </a>
                </li>
                <li><a href="https://twitter.com/paudosferros">
                        <img src="./assets/img/twitter.png" alt="Twitter">
                    </a>
                </li>
                <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros">
                        <img src="./assets/img/youtube.png" alt="YouTube">
                    </a>
                </li>
                <li><a href="https://instagram.com">
                        <img src="./assets/img/copy-url.png" alt="URL">
                    </a>
                </li>
            </ul>
        </nav>

        <nav class="public-top-actions" aria-label="Atalhos do serviço público">
            <a href="./consultar_denuncia.php">Acompanhe sua Denúncia</a>
            <button type="button" onclick="document.getElementById('modal-legislacao').style.display='flex'">Legislação Municipal</button>
            <button type="button" onclick="document.getElementById('modal-estudos').style.display='flex'">Estudos Ambientais</button>
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
        <section>
            <form id="form" enctype="multipart/form-data" method="post" action="processar_formulario.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="form_loaded_at" value="<?= time() ?>">
                <div class="hp-field" aria-hidden="true">
                    <label for="site_empresa">Site</label>
                    <input type="text" id="site_empresa" name="site_empresa" tabindex="-1" autocomplete="off">
                </div>
                <div class="form-header">
                    <!-- A logo já traz o nome da secretaria; o h1 fica só para
                         leitores de tela e busca, sem repetir na tela. -->
                    <h1 class="public-visually-hidden">Secretaria Municipal de Meio Ambiente de Pau dos Ferros</h1>
                    <picture>
                        <source media="(min-width: 861px)" srcset="./assets/img/logo-prefeitura-sema-horizontal.png">
                        <img src="./assets/img/logo-sema-vertical-redesign.png" alt="Prefeitura de Pau dos Ferros — Secretaria Municipal do Meio Ambiente">
                    </picture>
                    <p>PROTOCOLO ELETRÔNICO · ALVARÁS · DENÚNCIAS · AUTORIZAÇÕES</p>
                </div>

                <div class="public-info-banner" style="max-width:800px;margin:14px auto 0;padding:8px 14px;border-radius:8px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.5);display:flex;gap:10px;align-items:center;font-size:0.8rem;">
                    <i class="fas fa-info-circle" style="font-size:0.78rem;flex-shrink:0;"></i>
                    <span>O boleto será enviado por email após a análise inicial. Pagamento e comprovante são enviados depois por link seguro da equipe.</span>
                </div>
                <?php $adminUrl = 'admin/index.php'; include __DIR__ . '/includes/admin_session_badge.php'; ?>

                <nav class="public-step-nav" aria-label="Etapas do requerimento">
                    <button type="button" class="public-step is-active" data-public-step="1"><span>1</span> Serviço e identificação</button>
                    <button type="button" class="public-step" data-public-step="2"><span>2</span> Dados do serviço</button>
                    <button type="button" class="public-step" data-public-step="3"><span>3</span> Documentos e envio</button>
                    <small class="public-step-counter" aria-live="polite" role="status">Etapa 1 de 3</small>
                </nav>

                <?php
                // DEBUG: Verificar conteúdo da sessão
                if (MODO_TESTE) {
                    error_log("DEBUG SESSION: " . print_r($_SESSION, true));
                }
                
                // Exibir mensagens de erro ou sucesso
                if (isset($_SESSION['mensagem']) && is_array($_SESSION['mensagem'])):
                    $mensagem = $_SESSION['mensagem'];
                    $tipo = $mensagem['tipo'] ?? 'erro';
                    $texto = $mensagem['texto'] ?? ''; // CORRIGIDO: era 'mensagem', agora é 'texto'
                    unset($_SESSION['mensagem']);
                    
                    if (!empty($texto)):
                ?>
                <div class="alert alert-<?php echo htmlspecialchars($tipo); ?>" style="
                    padding: 15px 20px;
                    margin: 20px auto;
                    max-width: 800px;
                    border-radius: 8px;
                    background-color: <?php echo $tipo === 'erro' ? '#f8d7da' : '#d4edda'; ?>;
                    border: 1px solid <?php echo $tipo === 'erro' ? '#f5c6cb' : '#c3e6cb'; ?>;
                    color: <?php echo $tipo === 'erro' ? '#721c24' : '#155724'; ?>;
                    font-weight: 500;
                    text-align: center;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                ">
                    <i class="fas fa-<?php echo $tipo === 'erro' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($texto); ?>
                </div>
                <?php 
                    endif;
                endif;
                
                // Preparar dados do formulário para restauração
                $formData = [];
                if (isset($_SESSION['form_data'])) {
                    $formData = $_SESSION['form_data'];
                    unset($_SESSION['form_data']);
                }
                ?>

                <!-- Seção 1: Dados do Proprietário (oculta em modo denúncia) -->
                <div class="form-section secao-alvara">
                    <div class="form-section-label">Dados do Proprietário do Imóvel</div>
                    <input type="hidden" name="mesmo_requerente" value="false">
                    <p class="public-field-note">Caso o requerente seja o próprio proprietário, repita aqui os mesmos dados informados em Dados do Requerente.</p>
                    <div class="form-part-2" id="proprietario-fields">
                        <input id="proprietario_nome" name="proprietario[nome]"
                            placeholder="Nome Completo do Proprietário *" autocomplete="name">
                        <input oninput="mascara(this)" type="text" name="proprietario[cpf_cnpj]"
                            id="proprietario_cpf_cnpj"
                            placeholder="CPF ou CNPJ do Proprietário" maxlength="18" autocomplete="off" data-type="cpf-cnpj">
                    </div>
                </div>

                <!-- Seção 2: Identificação — comum a todos os serviços, sempre visível -->
                <div class="form-section public-secao-comum" id="secao-identificacao">
                    <div class="form-section-label">Seus dados</div>
                    <p class="public-field-note" data-identificacao-nota>Use um e-mail que você acessa. A confirmação, o boleto e os documentos finais serão enviados para esse endereço.</p>
                    <div class="public-anonimo-aviso" data-identificacao-anonimo hidden>
                        <i class="fas fa-user-secret" aria-hidden="true"></i>
                        <span>Você escolheu denúncia anônima na etapa 2. Estes dados não serão registrados nem enviados.</span>
                    </div>
                    <div class="form-part-2">
                        <input data-required="true" id="name" name="requerente[nome]" placeholder="Nome Completo *" autocomplete="name">
                        <input oninput="mascara(this)" type="text" data-required="true" name="requerente[cpf_cnpj]" id="cpf"
                            placeholder="CPF ou CNPJ" maxlength="18" autocomplete="off" data-type="cpf-cnpj">
                        <input data-required="true" type="email" id="requerente_email" name="requerente[email]" placeholder="E-mail para receber as comunicações *" autocomplete="email" inputmode="email" maxlength="191">
                        <input data-required="true" type="email" id="requerente_email_confirmacao" name="requerente[email_confirmacao]" placeholder="Confirme o e-mail *" autocomplete="email" inputmode="email" maxlength="191">
                        <input class="public-field-wide" type="tel" maxlength="15" onkeyup="handlePhone(event)" data-required="true"
                            name="requerente[telefone]" id="phone" placeholder="Digite seu Telefone *" autocomplete="tel">
                    </div>
                </div>

                <div class="form-section secao-alvara public-responsavel-comum" id="responsavel-tecnico-comum" hidden>
                    <!-- O bloco é preenchido pelo fluxo selecionado e aparece uma única vez na etapa 1. -->
                </div>

                <!-- Seção 3: Endereço do Objetivo (oculta em modo denúncia) -->
                <div class="form-section secao-alvara">
                    <?= renderLocationComposer('endereco_objetivo', 'Localização da obra ou objetivo', true, $formData['endereco_objetivo'] ?? '') ?>
                    <div style="margin-top: 24px;">
                        <div class="form-section-label">Notificado pelo Fiscal de Obras? *</div>
                        <div class="public-radio-row" style="display: flex; gap: 16px; margin-top: 8px;">
                            <label>
                                <input type="radio" name="notificado_fiscal_obras" value="1" data-required="true" style="width:16px;height:16px;accent-color:#22c55e;cursor:pointer;"> Sim
                            </label>
                            <label>
                                <input type="radio" name="notificado_fiscal_obras" value="0" style="width:16px;height:16px;accent-color:#22c55e;cursor:pointer;"> Não
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section public-denuncia-location-section" hidden>
                    <?= renderLocationComposer('proprietario_endereco', 'Local da ocorrência', true, $formData['proprietario_endereco'] ?? '') ?>
                    <p class="public-field-note">Informe onde a situação denunciada está acontecendo. O município será sempre Pau dos Ferros/RN.</p>
                </div>

                <!-- Seção 4: Tipo de Solicitação -->
                <div class="form-section form-section-alvara">
                    <div class="tipo-alvara-container">
                        <div class="tipo-alvara-titulo">
                            SELECIONE O TIPO DE SOLICITAÇÃO
                        </div>
                        <div class="tipo-alvara-content">
                            <div class="tipo-alvara-left">
                                <div class="public-service-picker">
                                    <label for="tipo_alvara_busca">Selecione a solicitação</label>
                                    <div id="combobox-tipo" class="combobox-tipo">
                                        <input type="text" id="tipo_alvara_busca" autocomplete="off"
                                            placeholder="Busque por construção, denúncia, licença..."
                                            role="combobox" aria-expanded="false" aria-controls="tipo_alvara_lista">
                                        <i class="fas fa-search" aria-hidden="true"></i>
                                        <ul id="tipo_alvara_lista" class="tipo-alvara-lista" role="listbox" hidden></ul>
                                    </div>
                                    <div id="categoria-atalhos" class="categoria-atalhos" aria-label="Categorias de solicitação">
                                        <?php foreach ($categoriasPublicas as $catSlug => $catNome): ?>
                                        <button type="button" class="categoria-btn" data-categoria="<?= htmlspecialchars($catSlug) ?>">
                                            <?= htmlspecialchars($catNome) ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p id="mudar-tipo-link" class="mudar-tipo-link" style="display:none;">
                                        <button type="button" id="btn-mudar-tipo">Trocar serviço</button>
                                    </p>
                                </div>

                                <select required name="tipo_alvara" id="tipo_alvara" title="Tipo de Solicitação">
                                    <option value="" hidden>Selecione o tipo de solicitação...</option>
                                    <?php
                                    foreach ($categoriasPublicas as $catSlug => $catNome):
                                        $tiposDaCategoria = array_filter($tipos_alvara, fn($t) => ($t['categoria'] ?? '') === $catSlug && empty($t['oculto']));
                                        if (empty($tiposDaCategoria)) continue;
                                    ?>
                                    <optgroup label="<?= htmlspecialchars($catNome) ?>">
                                        <?php foreach ($tiposDaCategoria as $slug => $tipo): ?>
                                        <?php if (!empty($tipo['desabilitado'])): ?>
                                        <option value="<?= $slug ?>" disabled style="color:#aaa;"><?= htmlspecialchars($tipo['nome']) ?></option>
                                        <?php else: ?>
                                        <option value="<?= $slug ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section public-dynamic-section" hidden>
                    <div class="form-section-label" id="public-dynamic-title">Dados da solicitação</div>
                    <div id="campos_dinamicos">
                        <!-- Os campos específicos serão carregados aqui -->
                    </div>
                </div>

                <div class="form-section public-docs-section" hidden>
                    <div class="form-section-label">Documentos e envio</div>
                    <div id="documentos_necessarios" class="documentos-container" hidden>
                        <!-- A lista de documentos necessários será exibida aqui -->
                    </div>
                </div>

                <div class="form-part-4">
                    <div>
                        <input required type="checkbox" id="declaracao_veracidade" name="declaracao_veracidade"
                            value="1">
                        <label for="declaracao_veracidade">
                            Li e aceito o
                            <a href="#" onclick="event.preventDefault(); document.getElementById('modal-termo').style.display='flex';"
                               style="color:#009640; font-weight:600; text-decoration:underline;">
                                Termo de Ciência e Responsabilidade
                            </a>
                            — declaro que todas as informações e documentos são verdadeiros, estando ciente das sanções previstas na legislação.
                        </label>
                    </div>
                </div>

                <div class="captcha"></div>

                <div class="public-wizard-actions" aria-label="Navegação das etapas">
                    <button type="button" id="public-step-back"><i class="fas fa-arrow-left"></i> Voltar</button>
                    <button type="button" id="public-step-next">Continuar <i class="fas fa-arrow-right"></i></button>
                </div>

                <button type="submit" id="botao">
                    <i class="fas fa-paper-plane"></i> Enviar Requerimento
                </button>
            </form>

            <script>
                window.SEMA_TIPOS_ALVARA = <?= json_encode($tiposAlvaraPublicos, JSON_UNESCAPED_UNICODE) ?>;
                window.SEMA_FORM_CONFIG = {
                    csrfToken: <?= json_encode($_SESSION['csrf_token']) ?>,
                    tipoRules: <?= json_encode($tipoRules, JSON_UNESCAPED_UNICODE) ?>,
                    formData: <?= json_encode($formData, JSON_UNESCAPED_UNICODE) ?>,
                    hasServerFormData: <?= json_encode(!empty($formData)) ?>,
                    enquadramentoOptionsHtml: <?= json_encode($enquadramentoOptionsHtml, JSON_UNESCAPED_UNICODE) ?>,
                    locationTemplates: <?= json_encode($locationTemplates, JSON_UNESCAPED_UNICODE) ?>,
                    denunciaUpload: {
                        maxBytes: 100 * 1024 * 1024,
                        maxLabel: '100MB',
                        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov'],
                        allowedTypes: ['image/jpeg', 'image/png', 'application/pdf', 'video/mp4', 'video/quicktime']
                    }
                };
            </script>
        </section>
    </main>

    <!-- Modal — Termo de Ciência e Responsabilidade -->
    <div id="modal-termo" onclick="if(event.target===this)this.style.display='none'"
         style="display:none; position:fixed; inset:0; z-index:9100; background:rgba(0,0,0,0.65); overflow-y:auto; align-items:flex-start; justify-content:center;">
        <div style="background:#fff; max-width:720px; width:95%; margin:40px auto; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.45);">

            <!-- Cabeçalho -->
            <div style="background:#1a472a; padding:22px 28px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <h2 style="color:#fff; margin:0; font-size:1.15rem; letter-spacing:0.3px;">
                        <i class="fas fa-file-contract" style="margin-right:10px; opacity:0.9;"></i>
                        Termo de Ciência e Responsabilidade
                    </h2>
                    <p style="color:rgba(255,255,255,0.75); margin:4px 0 0; font-size:0.82rem;">
                        Licenciamento Ambiental — Secretaria Municipal de Meio Ambiente de Pau dos Ferros/RN
                    </p>
                </div>
                <button onclick="document.getElementById('modal-termo').style.display='none'"
                        style="background:none; border:none; color:#fff; font-size:1.6rem; cursor:pointer; line-height:1; opacity:0.8;">&times;</button>
            </div>

            <!-- Corpo -->
            <div style="padding:28px 32px; font-size:0.92rem; line-height:1.75; color:#222;">

                <p style="margin:0 0 18px;">
                    Ao marcar a caixa de declaração neste formulário, o requerente <strong>declara, sob as penas da lei</strong>, que:
                </p>

                <!-- Item 1 -->
                <div style="display:flex; gap:14px; margin-bottom:16px; padding:14px 16px; background:#f8fdf9; border-left:4px solid #009640; border-radius:0 6px 6px 0;">
                    <div style="flex-shrink:0; width:28px; height:28px; background:#009640; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem;">I</div>
                    <div>
                        <strong>Veracidade das Informações</strong><br>
                        Todas as informações prestadas neste sistema, bem como os documentos anexados, são verdadeiras, completas e fiéis à realidade, assumindo total responsabilidade por sua veracidade.
                    </div>
                </div>

                <!-- Item 2 -->
                <div style="display:flex; gap:14px; margin-bottom:16px; padding:14px 16px; background:#fff8f8; border-left:4px solid #c0392b; border-radius:0 6px 6px 0;">
                    <div style="flex-shrink:0; width:28px; height:28px; background:#c0392b; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem;">II</div>
                    <div>
                        <strong>Penalidades por Falsidade ou Omissão</strong><br>
                        Está ciente de que a falsidade ou omissão de informações configura <strong>crime de falsidade ideológica (art. 299 do Código Penal Brasileiro)</strong> e <strong>infração ambiental (Lei Federal nº 9.605/1998 — Lei de Crimes Ambientais)</strong>, sujeitando-se às sanções administrativas, civis e penais cabíveis, inclusive cassação da licença, multa e responsabilização por eventuais danos ao meio ambiente.
                    </div>
                </div>

                <!-- Item 3 -->
                <div style="display:flex; gap:14px; margin-bottom:16px; padding:14px 16px; background:#f8fdf9; border-left:4px solid #009640; border-radius:0 6px 6px 0;">
                    <div style="flex-shrink:0; width:28px; height:28px; background:#009640; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem;">III</div>
                    <div>
                        <strong>Autorização para Fiscalização</strong><br>
                        Autoriza a Secretaria Municipal de Meio Ambiente de Pau dos Ferros/RN a realizar, a qualquer tempo, vistorias, fiscalizações e solicitações de documentos comprobatórios relacionados ao empreendimento ou atividade requerida.
                    </div>
                </div>

                <!-- Item 4 -->
                <div style="display:flex; gap:14px; margin-bottom:16px; padding:14px 16px; background:#f8fdf9; border-left:4px solid #009640; border-radius:0 6px 6px 0;">
                    <div style="flex-shrink:0; width:28px; height:28px; background:#009640; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem;">IV</div>
                    <div>
                        <strong>Comunicação de Alterações</strong><br>
                        Compromete-se a comunicar imediatamente à SEMA qualquer alteração nas informações prestadas ou nos dados do empreendimento, sob pena de cassação da licença concedida.
                    </div>
                </div>

                <!-- Item 5 -->
                <div style="display:flex; gap:14px; margin-bottom:16px; padding:14px 16px; background:#f8fdf9; border-left:4px solid #009640; border-radius:0 6px 6px 0;">
                    <div style="flex-shrink:0; width:28px; height:28px; background:#009640; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem;">V</div>
                    <div>
                        <strong>Responsabilidade por Danos Ambientais</strong><br>
                        Assume a responsabilidade objetiva — independentemente de culpa — por eventuais danos ambientais decorrentes da atividade, obrigando-se a adotar todas as medidas de prevenção, mitigação e reparação necessárias, nos termos da legislação ambiental vigente.
                    </div>
                </div>

                <!-- Item 6 -->
                <div style="display:flex; gap:14px; margin-bottom:24px; padding:14px 16px; background:#fffbf0; border-left:4px solid #e67e22; border-radius:0 6px 6px 0;">
                    <div style="flex-shrink:0; width:28px; height:28px; background:#e67e22; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem;">VI</div>
                    <div>
                        <strong>Licença Não Substitui Outras Obrigações</strong><br>
                        A concessão da licença ambiental pela SEMA não isenta o requerente de obter outras autorizações, alvarás ou licenças exigidas por órgãos federais, estaduais ou municipais, tais como IBAMA, IDEMA-RN, Vigilância Sanitária, Corpo de Bombeiros, entre outros.
                    </div>
                </div>

                <!-- Referências legais -->
                <div style="background:#f5f5f5; border-radius:8px; padding:14px 16px; font-size:0.82rem; color:#555;">
                    <strong style="display:block; margin-bottom:6px; color:#333;">Base Legal:</strong>
                    Art. 299 do Código Penal Brasileiro &nbsp;·&nbsp;
                    Lei Federal nº 9.605/1998 (Crimes Ambientais) &nbsp;·&nbsp;
                    Lei Federal nº 6.938/1981 (Política Nacional do Meio Ambiente) &nbsp;·&nbsp;
                    Lei Municipal nº 017/2022 (Plano Diretor)
                </div>
            </div>

            <!-- Rodapé -->
            <div style="padding:16px 32px 24px; text-align:center;">
                <button onclick="
                    document.getElementById('modal-termo').style.display='none';
                    document.getElementById('declaracao_veracidade').checked=true;"
                    style="background:#009640; color:#fff; border:none; border-radius:8px; padding:12px 32px; font-size:0.95rem; font-weight:600; cursor:pointer; letter-spacing:0.3px;">
                    <i class="fas fa-check" style="margin-right:8px;"></i>Li e aceito os termos
                </button>
                <button onclick="document.getElementById('modal-termo').style.display='none'"
                        style="background:none; border:none; color:#888; font-size:0.85rem; cursor:pointer; margin-left:16px;">
                    Fechar
                </button>
            </div>

        </div>
    </div>

    <!-- Modal de Legislação Municipal -->
    <div id="modal-legislacao" onclick="if(event.target===this)this.style.display='none'" style="display:none; position:fixed; inset:0; z-index:9000; background:rgba(0,0,0,0.6); overflow-y:auto; padding:24px 16px;">
        <div style="background:#fff; max-width:700px; max-height:min(85vh, 760px); margin:0 auto; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.4); display:flex; flex-direction:column;">
            <div style="background:#009640; padding:24px 28px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <h2 style="color:#fff; margin:0; font-size:1.3rem;"><i class="fas fa-book-open" style="margin-right:10px;"></i>Legislação Municipal</h2>
                    <p style="color:rgba(255,255,255,0.85); margin:4px 0 0; font-size:0.9rem;">Pau dos Ferros / RN — leis vigentes relacionadas ao licenciamento ambiental</p>
                </div>
                <button onclick="document.getElementById('modal-legislacao').style.display='none'" style="background:none; border:none; color:#fff; font-size:1.6rem; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div style="padding:24px 28px; overflow-y:auto; flex:1 1 auto;">
                <?php
                $leis = [
                    [
                        'titulo'    => 'Código de Obras — Lei nº 2.117/2025',
                        'descricao' => 'Regula as obras e edificações no município, incluindo licenças de construção e habite-se.',
                        'icone'     => 'fa-hard-hat',
                        'cor'       => '#e67e22',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/4632/LEIS_2117_2025_0000001.pdf',
                    ],
                    [
                        'titulo'    => 'Código de Meio Ambiente — Lei nº 2.116/2025',
                        'descricao' => 'Estabelece normas de proteção ambiental e regula o licenciamento ambiental municipal.',
                        'icone'     => 'fa-leaf',
                        'cor'       => '#27ae60',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/4631/LEIS_2116_2025_0000001.pdf',
                    ],
                    [
                        'titulo'    => 'Código de Posturas — Lei nº 2.118/2025',
                        'descricao' => 'Define as normas de postura municipal sobre uso do solo, higiene e funcionamento de atividades.',
                        'icone'     => 'fa-city',
                        'cor'       => '#2980b9',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/4633/LEIS_2118_2025_0000001.pdf',
                    ],
                    [
                        'titulo'    => 'Emenda ao Código de Posturas — Lei nº 2.120/2025',
                        'descricao' => 'Altera e complementa disposições do Código de Posturas Municipal.',
                        'icone'     => 'fa-file-contract',
                        'cor'       => '#8e44ad',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/4635/LEIS_2120_2025_0000001.pdf',
                    ],
                    [
                        'titulo'    => 'Emenda ao Código de Meio Ambiente — Lei nº 2.119/2025',
                        'descricao' => 'Altera e complementa disposições do Código de Meio Ambiente Municipal.',
                        'icone'     => 'fa-seedling',
                        'cor'       => '#16a085',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/4634/LEIS_2119_2025_0000001.pdf',
                    ],
                    [
                        'titulo'    => 'Política Municipal de Resíduos Sólidos — LC nº 020/2023',
                        'descricao' => 'Institui a política de gestão de resíduos sólidos no município de Pau dos Ferros.',
                        'icone'     => 'fa-recycle',
                        'cor'       => '#d35400',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/3414/LC%20%20LEI%20COMPLEMENTAR_020_2023_0000001.pdf',
                    ],
                    [
                        'titulo'    => 'Plano Diretor — LC nº 017/2022',
                        'descricao' => 'Define o planejamento urbano e ambiental do município, incluindo zoneamento e uso do solo.',
                        'icone'     => 'fa-map',
                        'cor'       => '#c0392b',
                        'url'       => 'https://paudosferros.rn.gov.br/arquivos/2678/LC%20%20LEI%20COMPLEMENTAR_017_2022_0000001.pdf',
                    ],
                ];
                foreach ($leis as $lei): ?>
                <a href="<?= $lei['url'] ?>" target="_blank" rel="noopener" style="display:flex; align-items:center; gap:16px; padding:14px 16px; margin-bottom:10px; border-radius:8px; border:1px solid #e9ecef; text-decoration:none; color:#333; transition:background .15s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='#fff'">
                    <div style="width:44px; height:44px; border-radius:50%; background:<?= $lei['cor'] ?>1a; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas <?= $lei['icone'] ?>" style="color:<?= $lei['cor'] ?>; font-size:1.1rem;"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <p style="font-weight:600; margin:0; font-size:0.95rem; color:#212529;"><?= $lei['titulo'] ?></p>
                        <p style="margin:2px 0 0; font-size:0.82rem; color:#6c757d;"><?= $lei['descricao'] ?></p>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#adb5bd; font-size:0.85rem; flex-shrink:0;"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <div style="padding:14px 28px; background:#f8f9fa; border-top:1px solid #e9ecef; text-align:center;">
                <a href="https://www.paudosferros.rn.gov.br/" target="_blank" rel="noopener" style="color:#009640; font-size:0.88rem; text-decoration:none;"><i class="fas fa-globe" style="margin-right:6px;"></i>Portal da Prefeitura de Pau dos Ferros</a>
            </div>
        </div>
    </div>

    <!-- Modal de Estudos Ambientais -->
    <div id="modal-estudos" onclick="if(event.target===this)this.style.display='none'" style="display:none; position:fixed; inset:0; z-index:9000; background:rgba(0,0,0,0.6); overflow-y:auto; padding:24px 16px;">
        <div style="background:#fff; max-width:700px; max-height:min(85vh, 760px); margin:0 auto; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.4); display:flex; flex-direction:column;">
            <div style="background:#009640; padding:24px 28px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <h2 style="color:#fff; margin:0; font-size:1.3rem;"><i class="fas fa-magnifying-glass-chart" style="margin-right:10px;"></i>Estudos Ambientais</h2>
                    <p style="color:rgba(255,255,255,0.85); margin:4px 0 0; font-size:0.9rem;">Diagnósticos e levantamentos técnicos produzidos pela SEMA</p>
                </div>
                <button onclick="document.getElementById('modal-estudos').style.display='none'" style="background:none; border:none; color:#fff; font-size:1.6rem; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div style="padding:24px 28px; overflow-y:auto; flex:1 1 auto;">
                <?php
                $estudos = [
                    [
                        'titulo'    => 'Diagnóstico das APPs Urbanas de Pau dos Ferros — 2026',
                        'descricao' => 'Mapeamento e diagnóstico ambiental das Áreas de Preservação Permanente na malha urbana do município.',
                        'icone'     => 'fa-map-location-dot',
                        'cor'       => '#16a085',
                        'url'       => './assets/estudos/diagnostico-apps-urbanas-2026.pdf',
                    ],
                ];
                foreach ($estudos as $estudo): ?>
                <a href="<?= $estudo['url'] ?>" target="_blank" rel="noopener" style="display:flex; align-items:center; gap:16px; padding:14px 16px; margin-bottom:10px; border-radius:8px; border:1px solid #e9ecef; text-decoration:none; color:#333; transition:background .15s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='#fff'">
                    <div style="width:44px; height:44px; border-radius:50%; background:<?= $estudo['cor'] ?>1a; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas <?= $estudo['icone'] ?>" style="color:<?= $estudo['cor'] ?>; font-size:1.1rem;"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <p style="font-weight:600; margin:0; font-size:0.95rem; color:#212529;"><?= $estudo['titulo'] ?></p>
                        <p style="margin:2px 0 0; font-size:0.82rem; color:#6c757d;"><?= $estudo['descricao'] ?></p>
                    </div>
                    <i class="fas fa-file-pdf" style="color:#adb5bd; font-size:0.85rem; flex-shrink:0;"></i>
                </a>
                <?php endforeach; ?>
                <p style="margin:18px 0 0; font-size:0.82rem; color:#94a3b8; text-align:center;">Novos estudos serão adicionados aqui conforme forem produzidos.</p>
            </div>
        </div>
    </div>

    <!-- Onda de transição para o rodapé -->
    <div style="display:block; width:100%; line-height:0; font-size:0;">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none" style="display:block; width:100%; height:70px;">
            <path d="M0,35 C360,80 1080,-10 1440,35 L1440,70 L0,70 Z" fill="#0a1a2e"/>
        </svg>
    </div>

    <footer style="background:#0a1a2e; padding:48px 24px 32px; text-align:center;">
        <!-- wrapper único para isolar do CSS legado footer > div:nth-child -->
        <section style="max-width:1180px; margin:0 auto;">

            <!-- Botões de ação -->
            <section class="public-footer-actions" aria-label="Atalhos principais">
                <a class="public-action-card public-action-card-green" href="./consultar/index.php">
                    <strong>Consulte seu Alvará</strong>
                    <small>Acompanhe processos protocolados</small>
                </a>
                <a class="public-action-card public-action-card-yellow" href="./consultar_denuncia.php">
                    <strong>Acompanhe sua Denúncia</strong>
                    <small>Consulte o andamento pelo protocolo</small>
                </a>
                <button class="public-action-card public-action-card-blue" type="button" onclick="document.getElementById('modal-legislacao').style.display='flex'">
                    <strong>Legislação Municipal</strong>
                    <small>Consulte normas e códigos vigentes</small>
                </button>
                <button class="public-action-card public-action-card-cyan" type="button" onclick="document.getElementById('modal-estudos').style.display='flex'">
                    <strong>Estudos Ambientais</strong>
                    <small>Acesse diagnósticos e materiais técnicos</small>
                </button>
            </section>


            <!-- Bloco institucional: quem somos, onde ficamos, como falar -->
            <section class="public-footer-grid" aria-label="Endereço e contatos da SEMA">
                <div class="public-footer-marca">
                    <img src="./assets/SEMA/PNG/Branca/Logo SEMA Horizontal 3.png"
                         alt="SEMA — Secretaria Municipal de Meio Ambiente">
                    <p>Canal oficial de protocolo eletrônico de alvarás, licenças, autorizações e denúncias ambientais de Pau dos Ferros/RN.</p>
                    <p class="public-footer-remetente">
                        <strong>Só enviamos avisos por este e-mail:</strong>
                        <a href="mailto:<?= htmlspecialchars(EMAIL_FROM) ?>"><?= htmlspecialchars(EMAIL_FROM) ?></a>
                        Mensagens de qualquer outro remetente pedindo pagamento ou dados não são nossas.
                    </p>
                </div>

                <div class="public-footer-col">
                    <h3>Onde nos encontrar</h3>
                    <dl>
                        <dt>Endereço</dt>
                        <dd>
                            Rua Lafaiete Diógenes, nº 314 — São Judas Tadeu<br>
                            Pau dos Ferros/RN · CEP 59.900-000
                            <span class="public-footer-nota"><a href="https://maps.app.goo.gl/yr3RqUGGJLgzQtt39" target="_blank" rel="noopener">Ver rota no Google Maps</a></span>
                        </dd>
                        <dt>Atendimento</dt>
                        <dd>
                            Segunda a sexta, das 7h às 17h
                            <span class="public-footer-nota">Alguns canais e plantões atendem das 7h às 13h.</span>
                        </dd>
                    </dl>
                </div>

                <div class="public-footer-col">
                    <h3>Fale com a SEMA</h3>
                    <dl>
                        <dt>Telefone e WhatsApp</dt>
                        <dd>
                            <a href="https://wa.me/5584996686413" target="_blank" rel="noopener">(84) 99668-6413</a>
                            <span class="public-footer-nota">Denúncias e fiscalização.</span>
                        </dd>
                        <dt>E-mail</dt>
                        <dd>
                            <a href="mailto:sema@paudosferros.rn.gov.br">sema@paudosferros.rn.gov.br</a>
                            <span class="public-footer-nota">Fiscalização: <a href="mailto:fiscalizacaosemapdf@gmail.com">fiscalizacaosemapdf@gmail.com</a></span>
                        </dd>
                        <dt>Site oficial</dt>
                        <dd><a href="https://paudosferros.rn.gov.br" target="_blank" rel="noopener">paudosferros.rn.gov.br</a></dd>
                    </dl>
                </div>

                <div class="public-footer-mapa">
                    <iframe
                        title="Mapa com a localização da Secretaria Municipal do Meio Ambiente, na Rua Lafaiete Diógenes, 314, Pau dos Ferros/RN"
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3967.133482028977!2d-38.2072109!3d-6.1127256!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7bb333ee501f2a9%3A0x9d96138646b305a4!2sSecretaria%20Municipal%20do%20Meio%20Ambiente!5e0!3m2!1spt-BR!2sbr!4v1787855999922!5m2!1spt-BR!2sbr"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen></iframe>
                </div>
            </section>

            <!-- Barra inferior: links legais e assinatura -->
            <div class="public-footer-base">
                <nav class="public-footer-legal" aria-label="Links legais e institucionais">
                    <a href="./acessibilidade.php">Acessibilidade</a>
                    <a href="./termos_uso.php">Termos de uso</a>
                    <button type="button" onclick="document.getElementById('modal-privacidade').style.display='flex'">Avisos legais e privacidade</button>
                </nav>
                <p class="public-footer-copy">
                    &copy; <?= date('Y') ?> <strong>Prefeitura Municipal de Pau dos Ferros</strong> — Todos os direitos reservados.
                    <span>Desenvolvido por <a href="https://github.com/kellyson71" target="_blank" rel="noopener">Kellyson Raphael</a></span>
                </p>
            </div>

        </section>
    </footer>

    <!-- Faixa gráfica institucional -->
    <div style="width:100%; height:50px; background:url('./assets/img/faixa.png') repeat-x center / auto 100%; line-height:0; font-size:0;"></div>

    <div id="modal-privacidade" onclick="if(event.target===this)this.style.display='none'"
         style="display:none; position:fixed; inset:0; z-index:9200; background:rgba(0,0,0,0.62); overflow-y:auto; padding:24px 16px; align-items:flex-start; justify-content:center;">
        <div style="width:min(720px, 96vw); margin:30px auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 24px 70px rgba(0,0,0,0.35);">
            <div style="padding:22px 26px; background:#009640; color:#fff; display:flex; align-items:center; justify-content:space-between; gap:16px;">
                <h2 style="margin:0; font-size:1.15rem;">Avisos legais e privacidade</h2>
                <button type="button" onclick="document.getElementById('modal-privacidade').style.display='none'"
                        style="border:0; background:transparent; color:#fff; font-size:1.6rem; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div style="padding:24px 28px; color:#333; font-size:0.94rem; line-height:1.7;">
                <p>Este formulário eletrônico é canal oficial da Secretaria Municipal de Meio Ambiente de Pau dos Ferros/RN para protocolo de solicitações, denúncias e documentos administrativos.</p>
                <p>Os dados informados são tratados para identificação do interessado, instrução processual, análise técnica, fiscalização, comunicação oficial, cumprimento de obrigações legais e exercício de políticas públicas municipais, observadas a Lei Geral de Proteção de Dados Pessoais (Lei Federal nº 13.709/2018), a legislação de acesso à informação e as normas de arquivo público.</p>
                <p>O sistema utiliza cookies e tecnologias similares necessários à segurança, manutenção de sessão, acessibilidade, prevenção de abuso e medição estatística de uso. Quando houver ferramenta de análise, os dados são usados de forma agregada para melhoria do serviço público digital.</p>
                <p>O envio do formulário implica ciência de que informações falsas, incompletas ou documentos inverídicos podem gerar responsabilização administrativa, civil e penal.</p>
            </div>
        </div>
    </div>

    <div class="public-cookie-notice" id="public-cookie-notice" hidden>
        <div class="public-cookie-text">
            <strong>Uso de cookies</strong>
            <p>Usamos cookies necessários à segurança e ao funcionamento do serviço, além de medição estatística para melhorar o atendimento digital. <a href="./termos_uso.php">Saiba mais</a></p>
        </div>
        <button type="button" id="public-cookie-accept">Entendi</button>
    </div>

    <!-- Loading Spinner -->
    <div id="loading" class="loading" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <style>
        .hp-field {
            position: absolute !important;
            left: -10000px !important;
            top: auto !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
        }

        .field-invalid {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.18) !important;
        }

        .field-error,
        .upload-feedback-error {
            margin-top: 6px;
            color: #b91c1c;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .upload-feedback-ok {
            margin-top: 6px;
            color: #15803d;
            font-size: 0.76rem;
            line-height: 1.35;
            word-break: break-word;
        }

        .documentos-container .upload-feedback-error,
        .documentos-container .field-error {
            color: #b91c1c;
        }

        .documentos-container .upload-feedback-ok {
            color: #15803d;
        }

        /* Estilo para a mensagem de formato de arquivo */
        .formato-arquivo {
            display: block;
            color: #6c757d;
            font-size: 12px;
            margin-top: 4px;
        }

        /* Estilo para o container do input de arquivo */
        .file-input-container {
            margin-bottom: 20px;
        }

        .file-input-container label {
            display: block;
            margin-bottom: 8px;
            color: #024287;
            font-weight: 500;
        }

        .file-input-container input[type="file"] {
            display: block;
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: #fff;
            font-size: 14px;
        }

        .file-input-container input[type="file"]:hover {
            border-color: #009640;
        }

        .file-input-container input[type="file"]:focus {
            border-color: #009640;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 150, 64, 0.1);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            width: 100%;
            margin-bottom: 10px;
        }

        /* Estilo para labels dos campos dinâmicos */
        .form-grid-2 > label {
            color: #6b7280 !important;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Estilo para o toggle de estudo ambiental */
        .form-toggle {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #6b7280 !important;
            font-weight: 500;
        }

        .form-toggle > span {
            color: #6b7280 !important;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .form-toggle .toggle-options {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        /* Estilo para os radio buttons - VISÍVEIS E GRANDES */
        .form-toggle .toggle-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #333 !important;
            font-size: 14px;
            cursor: pointer;
            padding: 8px 16px;
            border: 1px solid #d9dedb;
            border-radius: 8px;
            background-color: #fff;
            transition: all 0.3s ease;
        }

        .form-toggle .toggle-options label:hover {
            background-color: #f0fdf4;
            border-color: #009640;
        }

        .form-toggle .toggle-options input[type="radio"] {
            width: 20px !important;
            height: 20px !important;
            margin: 0 !important;
            cursor: pointer;
            accent-color: #009640;
            flex-shrink: 0;
        }

        .form-toggle .toggle-options label:has(input:checked) {
            background-color: #f0fdf4;
            border-color: #009640;
        }

        /* Estilo para input de data */
        .form-grid-2 input[type="date"] {
            padding: 12px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background-color: white;
            color: #024287;
            font-size: 14px;
            cursor: pointer;
        }

        .form-grid-2 input[type="date"]:focus {
            border-color: #0dcaf0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 202, 240, 0.25);
        }

        /* Estilo para a lista de documentos */
        .documentos-lista {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .documentos-lista h3 {
            color: #024287;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .documentos-section {
            margin-bottom: 30px;
        }

        .documentos-section h4 {
            color: #009640;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .observacoes-lista {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .observacoes-lista li {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .observacoes-lista li:before {
            content: "•";
            color: #009640;
            position: absolute;
            left: 0;
        }

        /* Mensagens de feedback */
        .mensagem-inicial,
        .mensagem-erro,
        .mensagem-carregando {
            text-align: center;
            padding: 30px;
            border-radius: 8px;
            background-color: #fff;
        }

        .mensagem-inicial i,
        .mensagem-erro i,
        .mensagem-carregando i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #009640;
        }

        .mensagem-erro i {
            color: #dc3545;
        }

        .spinner-border {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
        }

        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
    </style>

    <!-- ── Widget de Sugestões ─────────────────────────────────────────── -->
    <style>
        /* Botão tab lateral — padrão UserVoice/Canny */
        #sg-tab {
            position: fixed;
            bottom: 120px;
            right: 0;
            z-index: 1050;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px 10px 12px;
            background: #1c4b36;
            color: #fff;
            border: none;
            border-radius: 8px 0 0 8px;
            cursor: pointer;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .02em;
            box-shadow: -2px 2px 12px rgba(0,0,0,.18);
            transition: background .15s, padding-right .15s;
            writing-mode: horizontal-tb;
        }
        #sg-tab:hover { background: #163d2b; padding-right: 18px; }
        #sg-tab i { font-size: .8rem; opacity: .85; }

        /* Painel */
        #sg-panel {
            position: fixed;
            bottom: 0;
            right: -380px;
            width: 360px;
            max-width: 100vw;
            height: 100dvh;
            max-height: 520px;
            bottom: 60px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px 0 0 12px;
            box-shadow: -4px 0 32px rgba(0,0,0,.12);
            z-index: 1049;
            transition: right .25s cubic-bezier(.4,0,.2,1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #sg-panel.open { right: 0; }

        .sg-panel-head {
            padding: 16px 18px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .sg-panel-head-title { font-size: .88rem; font-weight: 700; color: #0f172a; }
        .sg-panel-head-sub   { font-size: .72rem; color: #94a3b8; margin-top: 1px; }
        .sg-panel-close {
            background: none; border: none; color: #94a3b8;
            cursor: pointer; padding: 4px 6px; border-radius: 6px;
            font-size: .85rem; line-height: 1; transition: background .12s, color .12s;
        }
        .sg-panel-close:hover { background: #f1f5f9; color: #334155; }

        .sg-panel-body { padding: 16px 18px 18px; overflow-y: auto; flex: 1; }

        /* Seletor de tipo — segmented control limpo */
        .sg-seg {
            display: flex;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .sg-seg label {
            flex: 1;
            text-align: center;
            padding: 7px 4px;
            font-size: .73rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-right: 1px solid #e2e8f0;
            transition: background .12s, color .12s;
            user-select: none;
        }
        .sg-seg label:last-child { border-right: none; }
        .sg-seg input[type="radio"] { display: none; }
        .sg-seg label:has(input:checked) {
            background: #1c4b36;
            color: #fff;
        }

        /* Campo de texto */
        .sg-label {
            font-size: .72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 5px;
        }
        .sg-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: .83rem;
            color: #0f172a;
            font-family: inherit;
            resize: vertical;
            min-height: 96px;
            outline: none;
            transition: border-color .15s;
            margin-bottom: 4px;
        }
        .sg-textarea:focus { border-color: #1c4b36; }
        .sg-textarea.error { border-color: #ef4444; }
        .sg-err { font-size: .72rem; color: #ef4444; margin-bottom: 8px; display: none; }

        /* Campos opcionais */
        .sg-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 10px 0 14px; }
        .sg-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: .8rem;
            color: #0f172a;
            font-family: inherit;
            outline: none;
            transition: border-color .15s;
        }
        .sg-input:focus { border-color: #1c4b36; }

        .sg-btn {
            width: 100%;
            padding: 10px;
            background: #1c4b36;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .sg-btn:hover { background: #163d2b; }
        .sg-btn:disabled { opacity: .55; cursor: not-allowed; }

        /* Estado de sucesso */
        .sg-success {
            display: none;
            text-align: center;
            padding: 32px 16px;
        }
        .sg-success-icon {
            width: 44px; height: 44px; border-radius: 50%;
            background: #f0fdf4; border: 1.5px solid #bbf7d0;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            color: #15803d; font-size: 1.1rem;
        }
        .sg-success-title { font-size: .9rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .sg-success-text  { font-size: .78rem; color: #64748b; line-height: 1.5; }
        .sg-reset-btn {
            margin-top: 16px;
            padding: 7px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #fff;
            font-size: .78rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: background .12s;
        }
        .sg-reset-btn:hover { background: #f8fafc; }
    </style>

    <!-- Tab lateral -->
    <button id="sg-tab" onclick="sgToggle()" aria-label="Enviar feedback" aria-controls="sg-panel" aria-expanded="false">
        <i class="fas fa-comment-dots"></i>
        Feedback
    </button>

    <!-- Painel deslizante -->
    <div id="sg-panel" role="dialog" aria-modal="true" aria-label="Enviar feedback">
        <div class="sg-panel-head">
            <div>
                <div class="sg-panel-head-title">Envie seu feedback</div>
                <div class="sg-panel-head-sub">Sua opinião ajuda a melhorar o portal</div>
            </div>
            <button class="sg-panel-close" onclick="sgToggle()" aria-label="Fechar">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="sg-panel-body">
            <div id="sg-form-state">
                <!-- Tipo: segmented control -->
                <div class="sg-label">Categoria</div>
                <div class="sg-seg">
                    <label><input type="radio" name="sg_tipo" value="melhoria" checked>Melhoria</label>
                    <label><input type="radio" name="sg_tipo" value="dificuldade">Dificuldade</label>
                    <label><input type="radio" name="sg_tipo" value="elogio">Elogio</label>
                    <label><input type="radio" name="sg_tipo" value="outro">Outro</label>
                </div>

                <!-- Mensagem -->
                <div class="sg-label">Mensagem</div>
                <textarea id="sg-texto" class="sg-textarea"
                          placeholder="Descreva com detalhes…"
                          maxlength="2000"></textarea>
                <div class="sg-err" id="sg-texto-err">Descreva com pelo menos 10 caracteres.</div>

                <!-- Identificação opcional -->
                <div class="sg-label" style="margin-top:2px;">Identificação <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></div>
                <div class="sg-row">
                    <input id="sg-nome"  type="text"  class="sg-input" placeholder="Nome" maxlength="120">
                    <input id="sg-email" type="email" class="sg-input" placeholder="E-mail" maxlength="120">
                </div>
                <div class="hp-field" aria-hidden="true">
                    <label for="sg-site">Site</label>
                    <input type="text" id="sg-site" tabindex="-1" autocomplete="off">
                </div>

                <button class="sg-btn" id="sg-submit-btn" onclick="sgEnviar()">Enviar</button>
            </div>

            <div class="sg-success" id="sg-success-state">
                <div class="sg-success-icon"><i class="fas fa-check"></i></div>
                <div class="sg-success-title">Recebemos seu feedback</div>
                <div class="sg-success-text">Agradecemos pela contribuição. Sua mensagem será analisada pela equipe da SEMA de Pau dos Ferros.</div>
                <button class="sg-reset-btn" onclick="sgReset()">Enviar outro</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        let _open = false;

        window.sgToggle = function() {
            _open = !_open;
            document.getElementById('sg-panel').classList.toggle('open', _open);
            document.getElementById('sg-tab').setAttribute('aria-expanded', _open ? 'true' : 'false');
            if (_open) setTimeout(() => document.getElementById('sg-texto').focus(), 260);
        };

        function sgClose() {
            _open = false;
            document.getElementById('sg-panel').classList.remove('open');
            document.getElementById('sg-tab').setAttribute('aria-expanded', 'false');
        }

        document.addEventListener('click', function(e) {
            if (!_open) return;
            const panel = document.getElementById('sg-panel');
            const tab   = document.getElementById('sg-tab');
            if (!panel.contains(e.target) && !tab.contains(e.target)) {
                sgClose();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (_open && e.key === 'Escape') sgClose();
        });

        window.sgEnviar = function() {
            const texto = document.getElementById('sg-texto').value.trim();
            const errEl = document.getElementById('sg-texto-err');
            const field = document.getElementById('sg-texto');
            if (texto.length < 10) {
                field.classList.add('error');
                errEl.style.display = 'block';
                field.focus();
                return;
            }
            field.classList.remove('error');
            errEl.style.display = 'none';

            const btn = document.getElementById('sg-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Enviando…';

            const fd = new FormData();
            fd.append('csrf_token', window.SEMA_FORM_CONFIG?.csrfToken || '');
            fd.append('site_empresa', document.getElementById('sg-site').value.trim());
            fd.append('tipo',  document.querySelector('input[name="sg_tipo"]:checked')?.value ?? 'melhoria');
            fd.append('texto', texto);
            fd.append('nome',  document.getElementById('sg-nome').value.trim());
            fd.append('email', document.getElementById('sg-email').value.trim());

            fetch('sugestao_handler.php', { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('sg-form-state').style.display    = 'none';
                        document.getElementById('sg-success-state').style.display = 'block';
                    } else {
                        alert(d.error || 'Erro ao enviar.');
                        btn.disabled = false;
                        btn.textContent = 'Enviar';
                    }
                })
                .catch(() => {
                    alert('Falha de comunicação.');
                    btn.disabled = false;
                    btn.textContent = 'Enviar';
                });
        };

        window.sgReset = function() {
            document.getElementById('sg-texto').value  = '';
            document.getElementById('sg-nome').value   = '';
            document.getElementById('sg-email').value  = '';
            document.querySelector('input[name="sg_tipo"][value="melhoria"]').checked = true;
            document.getElementById('sg-form-state').style.display    = 'block';
            document.getElementById('sg-success-state').style.display = 'none';
            const btn = document.getElementById('sg-submit-btn');
            btn.disabled = false;
            btn.textContent = 'Enviar';
        };
    })();
    </script>
</body>

</html>
