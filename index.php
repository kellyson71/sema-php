<?php
// Inclui configurações antes de qualquer redirecionamento
include_once 'includes/config.php';

// Redireciona apenas o ambiente principal; homologação deve permanecer local.
$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $requestUri;
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}

// Inclui o arquivo com os tipos de alvará
include_once 'tipos_alvara.php';
// Inclui tabela de enquadramento CONEMA para licenciamento ambiental
include_once 'enquadramento_conema.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tipoRules = [];
foreach ($tipos_alvara as $slug => $tipo) {
    $isAmbiental = ($tipo['categoria'] ?? '') === 'ambiental';
    $tipoRules[$slug] = [
        'categoria' => $tipo['categoria'] ?? '',
        'ambiental' => $isAmbiental,
        'exige_diario_oficial' => $isAmbiental && ($tipo['exige_diario_oficial'] ?? true),
        'exige_ctf' => (bool) ($tipo['exige_ctf'] ?? false),
        'exige_licenca_anterior' => (bool) ($tipo['exige_licenca_anterior'] ?? false),
        'limite_upload' => $isAmbiental ? MAX_FILE_SIZE_AMBIENTAL : MAX_FILE_SIZE,
        'limite_upload_label' => $isAmbiental ? '40MB' : '10MB',
    ];
}
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

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="./js/index.js" defer></script>
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

        <div class="user-options">
            <p id="alter-font">Tamanho da fonte</p>
            <button onclick="increaseFont()">A+</button>
            <p>|</p>
            <button onclick="decreaseFont()">A-</button>
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
                    <img src="./assets/img/Logo_sema.png" alt="Secretaria Municipal de Meio Ambiente">
                    <h1>SECRETARIA MUNICIPAL DE MEIO AMBIENTE</h1>
                    <p>PROTOCOLO ELETRÔNICO — ALVARÁS, DENÚNCIAS E AUTORIZAÇÕES</p>
                </div>

                <div style="max-width:800px;margin:14px auto 0;padding:8px 14px;border-radius:8px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.5);display:flex;gap:10px;align-items:center;font-size:0.8rem;">
                    <i class="fas fa-info-circle" style="font-size:0.78rem;flex-shrink:0;"></i>
                    <span>O boleto será enviado por email após a análise inicial. Pagamento e comprovante são enviados depois por link seguro da equipe.</span>
                </div>

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

                <?php if (!empty($formData)): ?>
                <script>
                // Restaurar dados do formulário
                document.addEventListener('DOMContentLoaded', function() {
                    const formData = <?php echo json_encode($formData); ?>;
                    
                    // Restaurar campos do requerente
                    if (formData.requerente) {
                        if (formData.requerente.nome) document.querySelector('input[name="requerente[nome]"]').value = formData.requerente.nome;
                        if (formData.requerente.email) document.querySelector('input[name="requerente[email]"]').value = formData.requerente.email;
                        if (formData.requerente.cpf_cnpj) document.querySelector('input[name="requerente[cpf_cnpj]"]').value = formData.requerente.cpf_cnpj;
                        if (formData.requerente.telefone) document.querySelector('input[name="requerente[telefone]"]').value = formData.requerente.telefone;
                    }
                    
                    // Restaurar endereço
                    if (formData.endereco_objetivo) {
                        document.querySelector('input[name="endereco_objetivo"]').value = formData.endereco_objetivo;
                    }
                    
                    // Restaurar tipo de alvará
                    if (formData.tipo_alvara) {
                        const selectAlvara = document.getElementById('tipo_alvara');
                        selectAlvara.value = formData.tipo_alvara;
                        selectAlvara.dispatchEvent(new Event('change'));
                    }
                    
                    // Restaurar campos do proprietário
                    if (formData.proprietario) {
                        setTimeout(() => {
                            if (formData.proprietario.nome) {
                                const nomeInput = document.querySelector('input[name="proprietario[nome]"]');
                                if (nomeInput) nomeInput.value = formData.proprietario.nome;
                            }
                            if (formData.proprietario.cpf_cnpj) {
                                const cpfInput = document.querySelector('input[name="proprietario[cpf_cnpj]"]');
                                if (cpfInput) cpfInput.value = formData.proprietario.cpf_cnpj;
                            }
                        }, 300);
                    }
                    
                    // Restaurar campos dinâmicos (CTF, licença anterior, etc)
                    setTimeout(() => {
                        Object.keys(formData).forEach(key => {
                            const input = document.querySelector(`input[name="${key}"], textarea[name="${key}"]`);
                            if (input && formData[key]) {
                                input.value = formData[key];
                            }
                        });
                    }, 500);
                    
                    console.log('Dados do formulário restaurados');
                });
                </script>
                <?php endif; ?>

                <!-- Seção 1: Dados do Proprietário (oculta em modo denúncia) -->
                <div class="form-section secao-alvara">
                    <div class="form-section-label">Dados do Proprietário do Imóvel</div>
                    <input type="hidden" name="mesmo_requerente" value="false">
                    <div class="form-part-2" id="proprietario-fields">
                        <input id="proprietario_nome" name="proprietario[nome]"
                            placeholder="Nome Completo do Proprietário *" autocomplete="name">
                        <input oninput="mascara(this)" type="text" name="proprietario[cpf_cnpj]"
                            id="proprietario_cpf_cnpj"
                            placeholder="CPF ou CNPJ do Proprietário" maxlength="18" autocomplete="off" data-type="cpf-cnpj">
                    </div>
                </div>

                <!-- Seção 2: Dados do Requerente (oculta em modo denúncia) -->
                <div class="form-section secao-alvara">
                    <div class="form-section-label">Dados do Requerente</div>
                    <div class="form-part-2">
                        <input data-required="true" id="name" name="requerente[nome]" placeholder="Nome Completo do Requerente *" autocomplete="name">
                        <input data-required="true" type="email" name="requerente[email]" placeholder="Digite seu email *" autocomplete="email">
                        <input oninput="mascara(this)" type="text" data-required="true" name="requerente[cpf_cnpj]" id="cpf"
                            placeholder="CPF ou CNPJ do Requerente" maxlength="18" autocomplete="off" data-type="cpf-cnpj">
                        <input type="tel" maxlength="15" onkeyup="handlePhone(event)" data-required="true"
                            name="requerente[telefone]" id="phone" placeholder="Digite seu Telefone *" autocomplete="tel">
                    </div>
                </div>

                <!-- Seção 3: Endereço do Objetivo (oculta em modo denúncia) -->
                <div class="form-section secao-alvara">
                    <div class="form-part-2">
                        <input data-required="true" name="endereco_objetivo"
                            placeholder="Localização de Obras (Rua, número, bairro, CEP) *" autocomplete="street-address">
                    </div>
                    <div style="margin-top: 24px;">
                        <div class="form-section-label">Notificado pelo Fiscal de Obras? *</div>
                        <div style="display: flex; gap: 16px; margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: rgba(255,255,255,0.85); font-size: 0.95rem;">
                                <input type="radio" name="notificado_fiscal_obras" value="1" data-required="true" style="width:16px;height:16px;accent-color:#22c55e;cursor:pointer;"> Sim
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: rgba(255,255,255,0.85); font-size: 0.95rem;">
                                <input type="radio" name="notificado_fiscal_obras" value="0" style="width:16px;height:16px;accent-color:#22c55e;cursor:pointer;"> Não
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Seção 4: Tipo de Solicitação -->
                <div class="form-section form-section-alvara">
                    <div class="tipo-alvara-container">
                        <div class="tipo-alvara-titulo">
                            <i class="fas fa-clipboard-list"></i>
                            SELECIONE O TIPO DE SOLICITAÇÃO
                        </div>
                        <div class="tipo-alvara-content">
                            <div class="tipo-alvara-left">
                                <select required name="tipo_alvara" id="tipo_alvara" title="Tipo de Solicitação">
                                    <option value="" hidden>Selecione o tipo de solicitação...</option>
                                    <?php
                                    $categorias = [
                                        'obras'              => 'Obras e Construção',
                                        'ambiental'          => 'Licenças Ambientais',
                                        'outro'              => 'Outros Serviços',
                                        'denuncia_autoriza'  => 'Denúncias e Autorizações',
                                    ];
                                    foreach ($categorias as $catSlug => $catNome):
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

                                <div id="campos_dinamicos">
                                    <!-- Os campos específicos serão carregados aqui -->
                                </div>
                            </div>

                            <div class="tipo-alvara-right">
                                <div id="documentos_necessarios" class="documentos-container">
                                    <!-- A lista de documentos necessários será exibida aqui -->
                                </div>
                            </div>
                        </div>
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

                <button type="submit" id="botao">
                    <i class="fas fa-paper-plane"></i> Enviar Requerimento
                </button>
            </form>

            <script>
                window.SEMA_FORM_CONFIG = {
                    csrfToken: <?= json_encode($_SESSION['csrf_token']) ?>,
                    tipoRules: <?= json_encode($tipoRules, JSON_UNESCAPED_UNICODE) ?>,
                    denunciaUpload: {
                        maxBytes: 20 * 1024 * 1024,
                        maxLabel: '20MB',
                        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov'],
                        allowedTypes: ['image/jpeg', 'image/png', 'application/pdf', 'video/mp4', 'video/quicktime']
                    }
                };
            </script>
            
            <script>
            document.getElementById('form').addEventListener('submit', function(e) {
                clearFormErrors();

                const tipoAlvara = document.getElementById('tipo_alvara').value;
                let firstInvalid = null;

                function markInvalid(field, message) {
                    if (!field) return;
                    field.classList.add('field-invalid');
                    field.setAttribute('aria-invalid', 'true');
                    const host = field.closest('.form-toggle') || field.closest('.form-part-4') || field.parentElement;
                    let error = host.querySelector(':scope > .field-error');
                    if (!error) {
                        error = document.createElement('div');
                        error.className = 'field-error';
                        host.appendChild(error);
                    }
                    error.textContent = message;
                    if (!firstInvalid) firstInvalid = field;
                }

                function requireValue(selector, message) {
                    const field = document.querySelector(selector);
                    if (!field || !field.value.trim()) {
                        markInvalid(field, message);
                        return false;
                    }
                    return true;
                }

                function requireChecked(selector, hostSelector, message) {
                    if (document.querySelector(selector)) return true;
                    const host = document.querySelector(hostSelector);
                    markInvalid(host?.querySelector('input, select, textarea') || host, message);
                    return false;
                }

                if (!tipoAlvara) {
                    markInvalid(document.getElementById('tipo_alvara'), 'Selecione o tipo de solicitação.');
                }

                if (tipoAlvara === 'denuncia') {
                    const anonimo = document.getElementById('chk_anonimo')?.checked;
                    if (!anonimo) {
                        requireValue('input[name="denunciante_nome"]', 'Informe seu nome ou marque denúncia anônima.');
                    }
                    requireChecked('input[name="tipos_denuncia[]"]:checked', '#tipos_denuncia_grid', 'Selecione pelo menos um tipo de ocorrência.');
                    if (document.querySelector('input[name="tipos_denuncia[]"][value="outros"]')?.checked) {
                        requireValue('input[name="outros_descricao"]', 'Descreva a ocorrência marcada como Outros.');
                    }
                } else {
                    requireValue('input[name="requerente[nome]"]', 'Informe o nome do requerente.');
                    requireValue('input[name="requerente[email]"]', 'Informe o e-mail do requerente.');
                    requireValue('input[name="requerente[cpf_cnpj]"]', 'Informe o CPF ou CNPJ do requerente.');
                    requireValue('input[name="requerente[telefone]"]', 'Informe o telefone do requerente.');
                    requireValue('input[name="endereco_objetivo"]', 'Informe a localização da obra ou objetivo.');
                    requireChecked('input[name="notificado_fiscal_obras"]:checked', 'input[name="notificado_fiscal_obras"]', 'Informe se houve notificação pelo fiscal de obras.');

                    const email = document.querySelector('input[name="requerente[email]"]');
                    if (email?.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                        markInvalid(email, 'Informe um e-mail válido.');
                    }

                    const nomeProprietario = document.querySelector('input[name="proprietario[nome]"]');
                    const cpfProprietario = document.querySelector('input[name="proprietario[cpf_cnpj]"]');
                    if (nomeProprietario?.value.trim() || cpfProprietario?.value.trim()) {
                        if (!nomeProprietario.value.trim()) markInvalid(nomeProprietario, 'Informe o nome do proprietário.');
                        if (!cpfProprietario.value.trim()) markInvalid(cpfProprietario, 'Informe o CPF ou CNPJ do proprietário.');
                    }

                    const rules = window.SEMA_FORM_CONFIG?.tipoRules?.[tipoAlvara] || {};
                    if (rules.ambiental) {
                        if (rules.exige_diario_oficial) {
                            requireValue('input[name="publicacao_diario_oficial"]', 'Informe os dados da publicação em Diário Oficial.');
                        }
                        if (rules.exige_ctf) {
                            requireValue('input[name="ctf_numero"]', 'Informe o número do Cadastro Técnico Federal.');
                        }
                        if (rules.exige_licenca_anterior) {
                            requireValue('input[name="licenca_anterior_numero"]', 'Informe o número da licença anterior.');
                        }
                        const possuiEstudo = document.querySelector('input[name="possui_estudo_ambiental"]:checked');
                        if (!possuiEstudo) {
                            requireChecked('input[name="possui_estudo_ambiental"]:checked', 'input[name="possui_estudo_ambiental"]', 'Informe se há estudo ambiental.');
                        } else if (possuiEstudo.value === '1') {
                            requireValue('input[name="tipo_estudo_ambiental"]', 'Informe o tipo de estudo ambiental.');
                        }
                    }
                }

                if (firstInvalid) {
                    e.preventDefault();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof firstInvalid.focus === 'function') firstInvalid.focus({ preventScroll: true });
                    return false;
                }

                document.getElementById('loading').style.display = 'flex';
                document.getElementById('botao').disabled = true;
            });

            function clearFormErrors() {
                document.querySelectorAll('.field-invalid').forEach(el => {
                    el.classList.remove('field-invalid');
                    el.removeAttribute('aria-invalid');
                });
                document.querySelectorAll('.field-error').forEach(el => el.remove());
            }
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
        <section style="max-width:900px; margin:0 auto;">

            <!-- Logo SEMA branca -->
            <img src="./assets/SEMA/PNG/Branca/Logo SEMA Horizontal 3.png"
                 alt="SEMA — Secretaria Municipal de Meio Ambiente"
                 style="max-width:240px; height:auto; margin-bottom:32px; opacity:0.95; display:block; margin-left:auto; margin-right:auto;">

            <!-- Botões de ação -->
            <section style="display:flex; flex-wrap:wrap; justify-content:center; gap:12px; margin-bottom:28px;">
                <a href="./consultar/index.php"
                   style="display:inline-flex; align-items:center; gap:8px; padding:12px 28px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); border-radius:10px; color:#fff; text-decoration:none; font-size:0.9rem; font-weight:600; letter-spacing:0.5px; box-shadow:0 4px 12px rgba(0,0,0,0.2); transition:all 0.2s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'">
                    <i class="fas fa-search" style="color:#4ade80;"></i> Consulte seu Alvará
                </a>
                <button onclick="document.getElementById('modal-legislacao').style.display='flex'"
                        style="display:inline-flex; align-items:center; gap:8px; padding:12px 28px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); border-radius:10px; color:#fff; font-size:0.9rem; font-weight:600; cursor:pointer; font-family:inherit; letter-spacing:0.5px; box-shadow:0 4px 12px rgba(0,0,0,0.2); transition:all 0.2s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'">
                    <i class="fas fa-book-open" style="color:#60a5fa;"></i> Legislação Municipal
                </button>
                <button onclick="document.getElementById('modal-estudos').style.display='flex'"
                        style="display:inline-flex; align-items:center; gap:8px; padding:12px 28px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); border-radius:10px; color:#fff; font-size:0.9rem; font-weight:600; cursor:pointer; font-family:inherit; letter-spacing:0.5px; box-shadow:0 4px 12px rgba(0,0,0,0.2); transition:all 0.2s;"
                        onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'">
                    <i class="fas fa-magnifying-glass-chart" style="color:#4ade80;"></i> Estudos Ambientais
                </button>
            </section>

            <!-- Contatos como texto -->
            <section style="display:flex; flex-wrap:wrap; justify-content:center; gap:28px; margin-bottom:36px;">
                <a href="https://wa.me/5584996686413" target="_blank" rel="noopener"
                   style="display:inline-flex; align-items:center; gap:10px; color:rgba(255,255,255,0.75); text-decoration:none; font-size:1rem; transition:color 0.2s;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.75)'">
                    <span style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:rgba(74,222,128,0.15); flex-shrink:0;">
                        <i class="fab fa-whatsapp" style="color:#4ade80; font-size:1.1rem;"></i>
                    </span>
                    <span>
                        <span style="display:block; font-size:0.7rem; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">WhatsApp</span>
                        <span style="font-weight:500;">(84) 99668-6413</span>
                    </span>
                </a>
                <a href="mailto:fiscalizacaosemapdf@gmail.com"
                   style="display:inline-flex; align-items:center; gap:10px; color:rgba(255,255,255,0.75); text-decoration:none; font-size:1rem; transition:color 0.2s;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.75)'">
                    <span style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:rgba(249,168,212,0.15); flex-shrink:0;">
                        <i class="fas fa-envelope" style="color:#f9a8d4; font-size:1rem;"></i>
                    </span>
                    <span>
                        <span style="display:block; font-size:0.7rem; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">E-mail</span>
                        <span style="font-weight:500;">fiscalizacaosemapdf@gmail.com</span>
                    </span>
                </a>
            </section>

            <!-- Divisor sutil -->
            <hr style="border:none; border-top:1px solid rgba(255,255,255,0.1); margin:0 auto 28px; max-width:600px;">

            <!-- Copyright -->
            <p style="font-size:0.95rem; color:rgba(255,255,255,0.85); margin:0; line-height:1.6; letter-spacing:0.3px;">
                &copy; <?= date('Y') ?> <strong style="color:#fff; font-weight:700;">Prefeitura Municipal de Pau dos Ferros</strong> — Todos os direitos reservados.
                <span style="font-size:0.8rem; color:rgba(255,255,255,0.5); display:block; margin-top:8px;">Desenvolvido por <a href="https://github.com/kellyson71" target="_blank" rel="noopener" style="color:rgba(255,255,255,0.65); text-decoration:none; border-bottom:1px dotted rgba(255,255,255,0.4);">Kellyson Raphael</a></span>
            </p>

        </section>
    </footer>

    <!-- Faixa gráfica institucional -->
    <div style="width:100%; height:50px; background:url('./assets/img/faixa.png') repeat-x center / auto 100%; line-height:0; font-size:0;"></div>

    <!-- Loading Spinner -->
    <div id="loading" class="loading" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const documentosDiv = document.getElementById('documentos_necessarios');

            // Adiciona a mensagem inicial
            documentosDiv.innerHTML = `
            <div class="mensagem-inicial">
                <i class="fas fa-file-alt"></i>
                <p>Selecione um tipo de alvará acima para visualizar os documentos necessários e iniciar o processo de requerimento.</p>
            </div>
        `;

            // Carregamento de campos para o tipo de solicitação
            const tipoAlvaraSelect = document.getElementById('tipo_alvara');
            const secoesAlvara = document.querySelectorAll('.secao-alvara');
            const form = document.getElementById('form');
            const declaracaoVeracidade = document.getElementById('declaracao_veracidade');

            function ativarModoAlvara() {
                secoesAlvara.forEach(s => {
                    s.style.display = '';
                    s.querySelectorAll('[data-required]').forEach(el => { el.required = true; });
                });
                if (declaracaoVeracidade) declaracaoVeracidade.required = true;
                form.action = 'processar_formulario.php';
            }

            function ativarModoDenuncia() {
                secoesAlvara.forEach(s => {
                    s.style.display = 'none';
                    s.querySelectorAll('[data-required]').forEach(el => { el.required = false; });
                });
                if (declaracaoVeracidade) declaracaoVeracidade.required = false;
                form.action = 'processar_denuncia_publica.php';
            }

            // Inicializa required corretamente no load
            secoesAlvara.forEach(s => {
                s.querySelectorAll('[data-required]').forEach(el => { el.required = true; });
            });

            if (tipoAlvaraSelect) {
                tipoAlvaraSelect.addEventListener('change', function() {
                    const tipo = this.value;

                    // Modo denúncia: esconde seções de alvará, muda action do form
                    if (tipo === 'denuncia') {
                        ativarModoDenuncia();
                    } else {
                        ativarModoAlvara();
                    }

                    // Mostrar loading enquanto carrega
                    documentosDiv.innerHTML = `
                    <div class="mensagem-carregando">
                        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #009640; margin-bottom: 15px;"></div>
                        <p>Carregando informações...</p>
                    </div>
                `;

                    // Fazemos uma requisição direta para a página PHP que processa os documentos
                    fetch('scripts/obter_documentos.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'tipo=' + encodeURIComponent(tipo)
                        })
                        .then(response => response.text())
                        .then(data => {
                            documentosDiv.innerHTML = data;

                            // Adicionamos os novos campos para o formulário principal
                            const inputsFile = documentosDiv.querySelectorAll('input[type="file"]');
                            inputsFile.forEach(input => {
                                input.setAttribute('form', 'form');
                                input.addEventListener('change', function() {
                                    validarArquivoUpload(this);
                                });
                            });
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            documentosDiv.innerHTML = `
                            <div class="mensagem-erro">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Não foi possível carregar os documentos necessários. Por favor, tente novamente.</p>
                            </div>
                        `;
                        });

                    // Carregamento de campos dinâmicos específicos
                    const camposDinamicos = document.getElementById('campos_dinamicos');

                    if (tipo === '') {
                        camposDinamicos.innerHTML = '';
                        ativarModoAlvara();
                        documentosDiv.innerHTML = `
                        <div class="mensagem-inicial">
                            <i class="fas fa-file-alt"></i>
                            <p>Selecione o tipo de solicitação acima para visualizar os documentos necessários e iniciar o processo.</p>
                        </div>
                    `;
                        return;
                    }

                    const tipoRules = window.SEMA_FORM_CONFIG?.tipoRules || {};
                    const currentRules = tipoRules[tipo] || {};

                    let campos = '';

                    // ── Campos específicos da denúncia pública ────────────────
                    if (tipo === 'denuncia') {
                        campos = `
                        <div style="margin-bottom:10px;">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;color:rgba(255,255,255,0.85);font-size:.9rem;">
                                <input type="checkbox" name="anonimo" id="chk_anonimo" value="1"
                                    style="width:16px;height:16px;accent-color:#ef4444;cursor:pointer;">
                                Realizar denúncia anônima (sua identidade não será registrada)
                            </label>
                        </div>
                        <div id="bloco_denunciante">
                            <div class="form-grid-2" style="margin-top:8px;">
                                <input id="denunciante_nome" name="denunciante_nome"
                                    placeholder="Seu nome completo *">
                                <input name="denunciante_endereco"
                                    placeholder="Seu endereço (opcional)">
                            </div>
                        </div>
                        <div class="form-section-label" style="margin-top:18px;">Dados do Proprietário / Responsável pela Área (opcional)</div>
                        <div class="form-grid-2" style="margin-top:8px;">
                            <input name="proprietario_nome" placeholder="Nome do proprietário">
                            <input name="proprietario_contato" placeholder="Contato (telefone, e-mail)">
                        </div>
                        <div style="margin-top:8px;">
                            <input name="proprietario_endereco" placeholder="Endereço / local da ocorrência" style="width:100%">
                        </div>
                        <div class="form-section-label" style="margin-top:18px;">Tipo de Ocorrência <span style="color:#f87171">*</span></div>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;" id="tipos_denuncia_grid">
                            ${[
                                ['obstrucao_via',       'Obstrução de via'],
                                ['terreno_sujo',        'Terreno sujo'],
                                ['terreno_baldio',      'Terreno baldio'],
                                ['esgoto_via',          'Esgoto em via pública'],
                                ['construcao_irregular','Construção irregular'],
                                ['entulho_construcao',  'Entulho em construção civil'],
                                ['entulho_via',         'Entulho em via pública'],
                                ['outros',              'Outros'],
                            ].map(([val, label]) => `
                                <label style="display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid rgba(255,255,255,0.2);border-radius:8px;cursor:pointer;color:rgba(255,255,255,0.85);font-size:.85rem;min-width:170px;">
                                    <input type="checkbox" name="tipos_denuncia[]" value="${val}"
                                        style="width:15px;height:15px;accent-color:#ef4444;cursor:pointer;"
                                        onchange="toggleOutros(this)">
                                    ${label}
                                </label>
                            `).join('')}
                        </div>
                        <div id="bloco_outros" style="display:none;margin-top:10px;">
                            <input name="outros_descricao" placeholder="Descreva a ocorrência *" style="width:100%">
                        </div>
                        <div class="form-section-label" style="margin-top:18px;">Detalhes adicionais</div>
                        <textarea name="observacoes" rows="4"
                            style="width:100%;margin-top:8px;padding:10px;border:1px solid rgba(255,255,255,0.2);border-radius:8px;background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.9);font-size:.9rem;resize:vertical;"
                            placeholder="Descreva os fatos com o máximo de detalhes possível..."></textarea>
                        `;
                        camposDinamicos.innerHTML = campos;

                        // Toggle anônimo
                        document.getElementById('chk_anonimo').addEventListener('change', function() {
                            const bloco = document.getElementById('bloco_denunciante');
                            const nomeInput = document.getElementById('denunciante_nome');
                            bloco.style.display = this.checked ? 'none' : '';
                            nomeInput.required = !this.checked;
                        });
                        // required inicial no nome do denunciante
                        document.getElementById('denunciante_nome').required = true;

                        return; // não carrega campos de alvará
                    }

                    // ── Campos dos tipos de alvará / autorização ────────────
                    if (tipo === 'autorizacao_area_publica') {
                        campos = `
                            <div class="form-grid-2">
                                <input required name="localizacao_area" placeholder="Localização da área / logradouro *">
                                <input name="informacoes_evento" placeholder="Nome / descrição do evento">
                            </div>
                            <div style="margin-top:8px;">
                                <input name="cro_cronograma" placeholder="CRO / cronograma do evento (datas e horários)"
                                    style="width:100%">
                            </div>
                            <div style="margin-top:8px;">
                                <textarea name="detalhamento" rows="3"
                                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:.9rem;resize:vertical;"
                                    placeholder="Detalhamento da solicitação..."></textarea>
                            </div>
                            <div style="margin-top:8px;padding:12px;background:rgba(255,255,255,0.06);border-radius:8px;color:rgba(255,255,255,0.55);font-size:.82rem;">
                                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                                Quando aplicável, o boleto e demais orientações para pagamento serão enviados posteriormente pelos canais oficiais.
                            </div>
                        `;
                    } else if (tipo === 'licenca_uso_ocupacao_solo') {
                        campos = `
                            <div class="form-grid-2">
                                <input required name="endereco_imovel" placeholder="Endereço do imóvel *">
                                <input name="numero_cadastro" placeholder="Nº do cadastro imobiliário (se souber)">
                            </div>
                            <div style="margin-top:8px;">
                                <textarea name="observacoes_solo" rows="3"
                                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:.9rem;resize:vertical;"
                                    placeholder="Observações sobre o uso pretendido..."></textarea>
                            </div>
                            <div style="margin-top:8px;padding:12px;background:rgba(255,255,255,0.06);border-radius:8px;color:rgba(255,255,255,0.55);font-size:.82rem;">
                                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                                Quando aplicável, o boleto e demais orientações para pagamento serão enviados posteriormente pelos canais oficiais.
                            </div>
                        `;
                    } else if (tipo === 'construcao') {
                        campos = `
                            <div class="form-grid-2">
                                <input required name="area_construcao" placeholder="Área total de construção (m²) *">
                                <input required name="numero_pavimentos" placeholder="Número de pavimentos *">
                            </div>
                            <div class="form-grid-2">
                                <input required name="responsavel_tecnico_nome" placeholder="Nome do Responsável Técnico *">
                                <input required name="responsavel_tecnico_registro" placeholder="Registro Profissional (CREA/CAU) *">
                            </div>
                            <div class="form-grid-2">
                                <select required name="responsavel_tecnico_tipo_documento" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                    <option value="" hidden>Tipo de Documento *</option>
                                    <option value="ART">ART</option>
                                    <option value="RRT">RRT</option>
                                    <option value="TRT">TRT</option>
                                    <option value="ART/RRT">ART/RRT</option>
                                </select>
                                <input required name="responsavel_tecnico_art" placeholder="Número do Documento *">
                            </div>
                        `;
                    } else if (tipo === 'habite_se' || tipo === 'habite_se_simples') {
                        campos = `
                            <div class="form-grid-2">
                                <input required name="area_construida" placeholder="Área Construída (m²) *">
                                <input required name="numero_pavimentos" placeholder="Número de Pavimentos *">
                            </div>
                            <div class="form-grid-2">
                                <input required name="responsavel_tecnico_nome" placeholder="Nome do Responsável Técnico *">
                                <input required name="responsavel_tecnico_registro" placeholder="Registro Profissional (CREA/CAU) *">
                            </div>
                            <div class="form-grid-2">
                                <select required name="responsavel_tecnico_tipo_documento" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                    <option value="" hidden>Tipo de Documento *</option>
                                    <option value="ART">ART</option>
                                    <option value="RRT">RRT</option>
                                    <option value="TRT">TRT</option>
                                    <option value="ART/RRT">ART/RRT</option>
                                </select>
                                <input required name="responsavel_tecnico_numero" placeholder="Número do Documento (ART/RRT/TRT) *">
                            </div>
                            <textarea required name="especificacao" placeholder="Composição do imóvel (ex: 1 sala, 2 quartos, 1 banheiro, 1 cozinha, 1 varanda...) *" rows="3"></textarea>
                        `;
                    } else if (tipo === 'desmembramento') {
                        campos = `
                            <div class="form-grid-2">
                                <input required name="area_lote" placeholder="Área do Lote (m²) *">
                                <input required name="responsavel_tecnico_nome" placeholder="Nome do Responsável Técnico *">
                            </div>
                            <div class="form-grid-2">
                                <input required name="responsavel_tecnico_registro" placeholder="Registro Profissional (CREA/CAU) *">
                                <div style="display: flex; gap: 10px; width: 100%;">
                                    <select required name="responsavel_tecnico_tipo_documento" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 30%;">
                                        <option value="" hidden>Tipo *</option>
                                        <option value="ART">ART</option>
                                        <option value="RRT">RRT</option>
                                        <option value="TRT">TRT</option>
                                        <option value="ART/RRT">ART/RRT</option>
                                    </select>
                                    <input required name="responsavel_tecnico_art" placeholder="Número do Documento *" style="width: 70%;">
                                </div>
                            </div>
                            <textarea required name="descricao_atividade" placeholder="Descrição detalhada do desmembramento *" rows="4"></textarea>
                        `;
                    } else if (currentRules.ambiental) {
                        campos = `
                            <div style="background:rgba(255,255,255,0.08); border-radius:8px; padding:14px 16px; margin-bottom:12px; border-left:4px solid #009640;">
                                <div style="font-weight:600; color:rgba(255,255,255,0.95); margin-bottom:8px; font-size:0.95rem;">
                                    <i class="fas fa-clipboard-check" style="margin-right:6px;"></i>Enquadramento Ambiental (Resolução CONEMA 04/2009)
                                </div>
                                <select required name="enquadramento_atividade" style="padding:10px; border:1px solid #ddd; border-radius:4px; width:100%; margin-bottom:8px;">
                                    <option value="" hidden>Selecione a atividade do empreendimento *</option>
                                    <?php foreach ($enquadramento_conema as $cat): ?>
                                    <optgroup label="<?= htmlspecialchars($cat['titulo']) ?>">
                                        <?php foreach ($cat['atividades'] as $slug => $ativ): ?>
                                        <option value="<?= $slug ?>"><?= htmlspecialchars($ativ['nome']) ?> (<?= $ativ['potencial'] ?>)</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:rgba(255,255,255,0.6); font-size:0.78rem;">Potencial poluidor: P = Pequeno, M = Médio, G = Grande</small>
                            </div>
                            <div style="margin-bottom:12px;">
                                <input name="localizacao_google_maps" placeholder="Link do Google Maps do empreendimento (opcional)" style="width:100%;">
                                <small style="color:rgba(255,255,255,0.6); font-size:0.78rem; display:block; margin-top:4px;">
                                    Abra o Google Maps, busque o local, clique em "Compartilhar" → "Copiar link" e cole aqui.
                                </small>
                            </div>
                            <div class="form-grid-2">
                                <input ${currentRules.exige_ctf ? 'required' : ''} name="ctf_numero" placeholder="Número do Cadastro Técnico Federal ${currentRules.exige_ctf ? '*' : '(se houver)'}">
                                <input ${currentRules.exige_licenca_anterior ? 'required' : ''} name="licenca_anterior_numero" placeholder="Número da licença anterior ${currentRules.exige_licenca_anterior ? '*' : '(se aplicável)'}">
                            </div>
                            <div class="form-grid-2">
                                <div>
                                    <input ${currentRules.exige_diario_oficial ? 'required' : ''} name="publicacao_diario_oficial" placeholder="Dados da publicação em Diário Oficial${currentRules.exige_diario_oficial ? ' *' : ''}" style="width:100%;">
                                    ${!currentRules.exige_diario_oficial ? '<small style="display:block;margin-top:5px;color:rgba(255,255,255,0.45);font-size:0.75rem;"><i class="fas fa-info-circle" style="margin-right:4px;"></i>Campo opcional para este tipo de licença</small>' : ''}
                                </div>
                                <input name="comprovante_pagamento" placeholder="Observação interna sobre pagamento (opcional)">
                            </div>
                            <div style="margin:-2px 0 12px; color:rgba(255,255,255,0.72); font-size:0.8rem;">
                                O boleto será enviado posteriormente para o email informado. Não é necessário anexar comprovante nesta etapa.
                            </div>
                            <div class="form-grid-2">
                                <label class="form-toggle">
                                    <span>Possui estudo ambiental? *</span>
                                    <div class="toggle-options">
                                        <label><input type="radio" name="possui_estudo_ambiental" value="1" required> Sim</label>
                                        <label><input type="radio" name="possui_estudo_ambiental" value="0" required> Não</label>
                                    </div>
                                </label>
                                <input name="tipo_estudo_ambiental" placeholder="Tipo de estudo ambiental (EIA/RIMA, PCA, RCA...)">
                            </div>
                            <div class="form-grid-2">
                                <label>
                                    Data de emissão da certidão municipal (válida por até 2 anos) *
                                    <input required type="date" name="data_certidao_municipal">
                                </label>
                            </div>
                        `;
                    } else if (tipo === 'licenca_previa_obras') {
                        campos = `
                            <div class="form-grid-2">
                                <input required name="area_construida" placeholder="Área Construída (m²) *">
                                <input required name="responsavel_tecnico_nome" placeholder="Nome do Responsável Técnico *">
                            </div>
                            <div class="form-grid-2">
                                <input required name="responsavel_tecnico_registro" placeholder="Registro Profissional (CREA/CAU) *">
                                <div style="display: flex; gap: 10px; width: 100%;">
                                    <select required name="responsavel_tecnico_tipo_documento" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 30%;">
                                        <option value="" hidden>Tipo *</option>
                                        <option value="ART">ART</option>
                                        <option value="RRT">RRT</option>
                                        <option value="TRT">TRT</option>
                                        <option value="ART/RRT">ART/RRT</option>
                                    </select>
                                    <input required name="responsavel_tecnico_art" placeholder="Número do Documento *" style="width: 70%;">
                                </div>
                            </div>
                            <textarea required name="descricao_atividade" placeholder="Descrição detalhada da obra *" rows="4"></textarea>
                        `;
                    } else {
                        campos = `
                            <textarea required name="descricao_atividade" placeholder="Descrição detalhada da atividade *" rows="4"></textarea>
                        `;
                    }

                    camposDinamicos.innerHTML = campos;

                    // Controlar obrigatoriedade do tipo de estudo
                    const estudoRadios = camposDinamicos.querySelectorAll('input[name=\"possui_estudo_ambiental\"]');
                    const tipoEstudoInput = camposDinamicos.querySelector('input[name=\"tipo_estudo_ambiental\"]');
                    if (estudoRadios.length && tipoEstudoInput) {
                        estudoRadios.forEach((radio) => {
                            radio.addEventListener('change', () => {
                                if (radio.value === '1' && radio.checked) {
                                    tipoEstudoInput.setAttribute('required', 'required');
                                } else if (radio.value === '0' && radio.checked) {
                                    tipoEstudoInput.removeAttribute('required');
                                }
                            });
                        });
                    }
                });
            }
        });

        function validarArquivoUpload(input) {
            clearUploadMessage(input);
            if (!input.files || input.files.length === 0) {
                return true;
            }

            const tipoSelecionado = document.querySelector('select[name="tipo_alvara"]')?.value || '';
            const isDenuncia = tipoSelecionado === 'denuncia';
            const denunciaConfig = window.SEMA_FORM_CONFIG?.denunciaUpload || {};
            const tipoConfig = window.SEMA_FORM_CONFIG?.tipoRules?.[tipoSelecionado] || {};
            const limiteBytes = isDenuncia ? denunciaConfig.maxBytes : (tipoConfig.limite_upload || 10485760);
            const limiteLabel = isDenuncia ? denunciaConfig.maxLabel : (tipoConfig.limite_upload_label || '10MB');
            const extensoesPermitidas = isDenuncia ? denunciaConfig.allowedExtensions : ['pdf'];
            const tiposPermitidos = isDenuncia ? denunciaConfig.allowedTypes : ['application/pdf'];

            for (const file of Array.from(input.files)) {
                const ext = file.name.toLowerCase().split('.').pop();
                if (!extensoesPermitidas.includes(ext) || (file.type && !tiposPermitidos.includes(file.type))) {
                    input.value = '';
                    setUploadMessage(input, isDenuncia
                        ? 'Envie apenas JPG, PNG, PDF, MP4 ou MOV.'
                        : 'Envie apenas arquivos em PDF.');
                    return false;
                }
                if (file.size > limiteBytes) {
                    input.value = '';
                    setUploadMessage(input, 'O arquivo "' + file.name + '" ultrapassa o limite de ' + limiteLabel + '.');
                    return false;
                }
            }

            setUploadMessage(input, Array.from(input.files).map(file => file.name).join(', '), true);
            return true;
        }

        function clearUploadMessage(input) {
            const current = input.parentElement?.querySelector('.upload-feedback');
            if (current) current.remove();
            input.classList.remove('field-invalid');
        }

        function setUploadMessage(input, message, success = false) {
            clearUploadMessage(input);
            const el = document.createElement('div');
            el.className = success ? 'upload-feedback upload-feedback-ok' : 'upload-feedback upload-feedback-error';
            el.textContent = message;
            input.parentElement?.appendChild(el);
            if (!success) input.classList.add('field-invalid');
        }

        function toggleOutros(checkbox) {
            if (checkbox.value !== 'outros') return;
            const bloco = document.getElementById('bloco_outros');
            if (!bloco) return;
            bloco.style.display = checkbox.checked ? '' : 'none';
        }

    </script>

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
            color: #fecaca;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .upload-feedback-ok {
            margin-top: 6px;
            color: #bbf7d0;
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

        /* Estilo para labels dos campos dinâmicos - BRANCO */
        .form-grid-2 > label {
            color: white !important;
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
            color: white !important;
            font-weight: 500;
        }

        .form-toggle > span {
            color: white !important;
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
            color: white !important;
            font-size: 14px;
            cursor: pointer;
            padding: 8px 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .form-toggle .toggle-options label:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
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
            background-color: rgba(0, 150, 64, 0.3);
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
