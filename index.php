<?php
// Verificação de redirecionamento para o domínio principal
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}

// Inclui configurações
include_once 'includes/config.php';

// Inclui o arquivo com os tipos de alvará
include_once 'tipos_alvara.php';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="./js/index.js" defer></script>
</head>

<body>
    <div class="feedback" id="feedback"></div>

    <?php if (defined('MODO_HOMOLOG') && MODO_HOMOLOG): ?>
    <!-- Banner de Homologação -->
    <div style="
        background: repeating-linear-gradient(45deg, #ff9800, #ff9800 10px, #f57c00 10px, #f57c00 20px);
        color: white;
        text-align: center;
        padding: 10px;
        font-weight: bold;
        font-size: 1.2rem;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        text-transform: uppercase;
        letter-spacing: 2px;
        pointer-events: none;
        opacity: 0.9;
    ">
        Ambiente de Homologação / Testes
    </div>
    <div style="height: 44px;"></div> <!-- Espaçador para o banner fixo -->
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
                <div class="form-header">
                    <img src="./assets/img/Logo_sema.png" alt="Secretaria Municipal de Meio Ambiente">
                    <h1>SECRETARIA MUNICIPAL DE MEIO AMBIENTE</h1>
                    <p>REQUERIMENTO DE ALVARÁ AMBIENTAL | PROTOCOLO ELETRÔNICO</p>
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
                    
                    // Restaurar mesmo_requerente
                    if (formData.mesmo_requerente) {
                        const radio = document.querySelector(`input[name="mesmo_requerente"][value="${formData.mesmo_requerente}"]`);
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change'));
                        }
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

                <!-- Seção 1: Dados do Proprietário -->
                <div class="form-section">
                    <div class="form-part-3">
                        <div class="y-n-field">
                            <p>O requerente é o mesmo que o proprietário?</p>
                            <label for="mesmo-sim">
                                <input required title="Sim" name="mesmo_requerente" id="mesmo-sim" type="radio"
                                    value="true">
                                Sim
                            </label>
                            <label for="mesmo-nao">
                                <input required title="Não" name="mesmo_requerente" id="mesmo-nao" type="radio"
                                    value="false">
                                Não
                            </label>
                        </div>
                    </div>

                    <div class="form-part-2" id="proprietario-fields">
                        <input id="proprietario_nome" name="proprietario[nome]"
                            placeholder="Nome Completo do Proprietário *" autocomplete="name">
                        <input oninput="mascara(this)" type="text" name="proprietario[cpf_cnpj]"
                            id="proprietario_cpf_cnpj"
                            placeholder="CPF/CNPJ do Proprietário: 000.000.000-00 ou 00.000.000/0000-00" maxlength="18" autocomplete="off" data-type="cpf-cnpj">
                    </div>
                </div>

                <!-- Seção 2: Dados do Requerente -->
                <div class="form-section">
                    <div class="form-part-2">
                        <input required id="name" name="requerente[nome]" placeholder="Nome Completo *" autocomplete="name">
                        <input required type="email" name="requerente[email]" placeholder="Digite seu email *" autocomplete="email">
                        <input oninput="mascara(this)" type="text" required name="requerente[cpf_cnpj]" id="cpf"
                            placeholder="CPF/CNPJ: 000.000.000-00 ou 00.000.000/0000-00" maxlength="18" autocomplete="off" data-type="cpf-cnpj">
                        <input type="tel" maxlength="15" onkeyup="handlePhone(event)" required
                            name="requerente[telefone]" id="phone" placeholder="Digite seu Telefone *" autocomplete="tel">
                    </div>
                </div>

                <!-- Seção 3: Endereço do Objetivo -->
                <div class="form-section">
                    <div class="form-part-2">
                        <input required name="endereco_objetivo"
                            placeholder="Endereço Completo (Rua, número, bairro, CEP) *" autocomplete="street-address">
                    </div>
                </div>

                <!-- Seção 4: Tipo de Alvará -->
                <div class="form-section form-section-alvara">
                    <div class="tipo-alvara-container">
                        <div class="tipo-alvara-titulo">
                            <i class="fas fa-clipboard-list"></i>
                            SELECIONE O TIPO DE ALVARÁ
                        </div>
                        <div class="tipo-alvara-content">
                            <div class="tipo-alvara-left">
                                <select required name="tipo_alvara" id="tipo_alvara" title="Tipo de Alvará">
                                    <option value="" hidden>Selecione um tipo de alvará...</option>
                                    <option value="construcao">Alvará de Construção</option>
                                    <option value="habite_se">Alvará de Habite-se e Legalização</option>
                                    <option value="habite_se_simples">Alvará de Habite-se Simples</option>
                                    <option value="funcionamento">Alvará de Funcionamento</option>
                                    <option value="desmembramento">Alvará de Desmembramento e Remembramento</option>
                                    <option value="demolicao">Alvará de Demolição</option>
                                    <option value="loteamento">Alvará de Loteamento</option>
                                    <option value="transporte">Licenciamento de Transporte</option>
                                    <option value="uso_solo">Certidão de Uso e Ocupação do Solo</option>
                                    <option value="parques_circos">Alvará para Parques e Circos</option>
                                    <option value="licenca_previa_obras">Licença Prévia de Obras</option>
                                    <option value="licenca_previa_ambiental">LP — Licença Prévia Ambiental</option>
                                    <option value="licenca_previa_instalacao">LP/LI — Licença Prévia + Instalação</option>
                                    <option value="licenca_instalacao_operacao">LI/LO — Instalação + Operação</option>
                                    <option value="licenca_operacao">LO — Licença de Operação</option>
                                    <option value="licenca_ambiental_unica">LAU — Licença Ambiental Única</option>
                                    <option value="licenca_ampliacao">LA — Licença de Ampliação</option>
                                    <option value="licenca_operacional_corretiva">LOC — Licença Operacional Corretiva</option>
                                    <option value="autorizacao_supressao">Autorização de Supressão Vegetal</option>
                                    <option value="outros">Outros</option>
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
                        <label for="declaracao_veracidade">Declaro, sob as penas da lei, que as informações aqui
                            prestadas são verdadeiras e assumo total responsabilidade pela veracidade das mesmas,
                            estando ciente das sanções previstas na legislação.</label>
                    </div>
                </div>

                <div class="captcha"></div>

                <button type="submit" id="botao">
                    <i class="fas fa-paper-plane"></i> Enviar Requerimento
                </button>
            </form>
            
            <script>
            // Validação em tempo real do formulário
            document.getElementById('form').addEventListener('submit', function(e) {
                const erros = [];
                
                // Validar campos do requerente
                const nomeRequerente = document.querySelector('input[name="requerente[nome]"]').value.trim();
                const emailRequerente = document.querySelector('input[name="requerente[email]"]').value.trim();
                const cpfRequerente = document.querySelector('input[name="requerente[cpf_cnpj]"]').value.trim();
                const telefoneRequerente = document.querySelector('input[name="requerente[telefone]"]').value.trim();
                
                if (!nomeRequerente) erros.push('Nome do requerente é obrigatório');
                if (!emailRequerente) erros.push('Email do requerente é obrigatório');
                if (!cpfRequerente) erros.push('CPF/CNPJ do requerente é obrigatório');
                if (!telefoneRequerente) erros.push('Telefone do requerente é obrigatório');
                
                // Validar email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRequerente && !emailRegex.test(emailRequerente)) {
                    erros.push('Email inválido');
                }
                
                // Validar endereço
                const endereco = document.querySelector('input[name="endereco_objetivo"]').value.trim();
                if (!endereco) erros.push('Endereço do objetivo é obrigatório');
                
                // Validar tipo de alvará
                const tipoAlvara = document.getElementById('tipo_alvara').value;
                if (!tipoAlvara) erros.push('Selecione um tipo de alvará');
                
                // Validar proprietário (se não for o mesmo)
                const mesmoRequerente = document.querySelector('input[name="mesmo_requerente"]:checked');
                if (!mesmoRequerente) {
                    erros.push('Informe se o requerente é o mesmo que o proprietário');
                } else if (mesmoRequerente.value === 'false') {
                    const nomeProprietario = document.querySelector('input[name="proprietario[nome]"]')?.value.trim();
                    const cpfProprietario = document.querySelector('input[name="proprietario[cpf_cnpj]"]')?.value.trim();
                    
                    if (nomeProprietario || cpfProprietario) {
                        if (!nomeProprietario) erros.push('Nome do proprietário é obrigatório');
                        if (!cpfProprietario) erros.push('CPF/CNPJ do proprietário é obrigatório');
                    }
                }
                
                // Validar campos específicos de licenças ambientais
                const tiposAmbientais = [
                    'licenca_previa_ambiental',
                    'licenca_previa_instalacao',
                    'licenca_instalacao_operacao',
                    'licenca_operacao',
                    'licenca_ambiental_unica',
                    'licenca_ampliacao',
                    'licenca_operacional_corretiva',
                    'autorizacao_supressao'
                ];
                
                if (tiposAmbientais.includes(tipoAlvara)) {
                    const publicacaoDO = document.querySelector('input[name="publicacao_diario_oficial"]')?.value.trim();
                    const comprovantePag = document.querySelector('input[name="comprovante_pagamento"]')?.value.trim();
                    
                    if (!publicacaoDO) erros.push('Dados da publicação em Diário Oficial são obrigatórios');
                    if (!comprovantePag) erros.push('Comprovante de pagamento é obrigatório');
                    
                    // Validar estudo ambiental
                    const possuiEstudo = document.querySelector('input[name="possui_estudo_ambiental"]:checked');
                    if (!possuiEstudo) {
                        erros.push('Informe se há estudo ambiental');
                    } else if (possuiEstudo.value === '1') {
                        const tipoEstudo = document.querySelector('input[name="tipo_estudo_ambiental"]')?.value.trim();
                        if (!tipoEstudo) erros.push('Informe o tipo de estudo ambiental');
                    }
                    
                    // Validar CTF para tipos específicos
                    const tiposExigemCTF = [
                        'licenca_operacao',
                        'licenca_instalacao_operacao',
                        'licenca_ambiental_unica',
                        'licenca_ampliacao',
                        'licenca_operacional_corretiva'
                    ];
                    if (tiposExigemCTF.includes(tipoAlvara)) {
                        const ctf = document.querySelector('input[name="ctf_numero"]')?.value.trim();
                        if (!ctf) erros.push('Número do CTF é obrigatório para este tipo de licença');
                    }
                    
                    // Validar licença anterior
                    const tiposExigemLicencaAnterior = ['licenca_operacao', 'licenca_instalacao_operacao'];
                    if (tiposExigemLicencaAnterior.includes(tipoAlvara)) {
                        const licencaAnterior = document.querySelector('input[name="licenca_anterior_numero"]')?.value.trim();
                        if (!licencaAnterior) erros.push('Número da licença anterior é obrigatório');
                    }
                }
                
                // Se houver erros, mostrar e impedir envio
                if (erros.length > 0) {
                    e.preventDefault();
                    alert('❌ Corrija os seguintes erros antes de enviar:\n\n' + erros.map((erro, i) => `${i + 1}. ${erro}`).join('\n'));
                    return false;
                }
                
                // Mostrar loading
                document.getElementById('loading').style.display = 'flex';
                document.getElementById('botao').disabled = true;
            });
            </script>
        </section>
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
                © 2023 - Todos os direitos reservados. Programa da&ensp;<a
                    href="https://www.paudosferros.rn.gov.br/">Prefeitura de Pau dos Ferros</a>
                <p>Desenvolvido por&ensp;<a href="https://github.com/kellyson71"
                        style="text-decoration: none; color: inherit;">Kellyson Raphael</a></p>
            </span>
            <div>
                <img src="./assets/img/Logo.png" alt="SEAD">
            </div>
        </div>
    </footer>

    <!-- Loading Spinner -->
    <div id="loading" class="loading" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <script>
        // Função para mostrar/esconder campos do proprietário
        document.addEventListener('DOMContentLoaded', function() {
            const mesmoSimRadio = document.getElementById('mesmo-sim');
            const mesmoNaoRadio = document.getElementById('mesmo-nao');
            const proprietarioFields = document.getElementById('proprietario-fields');
            const documentosDiv = document.getElementById('documentos_necessarios');

            // Adiciona a mensagem inicial
            documentosDiv.innerHTML = `
            <div class="mensagem-inicial">
                <i class="fas fa-file-alt"></i>
                <p>Selecione um tipo de alvará acima para visualizar os documentos necessários e iniciar o processo de requerimento.</p>
            </div>
        `;

            function toggleProprietarioFields() {
                if (mesmoSimRadio.checked) {
                    proprietarioFields.style.display = 'none';
                } else {
                    proprietarioFields.style.display = 'grid';
                }
            }

            mesmoSimRadio.addEventListener('change', toggleProprietarioFields);
            mesmoNaoRadio.addEventListener('change', toggleProprietarioFields);

            // Estado inicial
            proprietarioFields.style.display = 'none';

            // Carregamento de campos para o tipo de alvará
            const tipoAlvaraSelect = document.getElementById('tipo_alvara');

            if (tipoAlvaraSelect) {
                tipoAlvaraSelect.addEventListener('change', function() {
                    const tipo = this.value;

                    // Mostrar loading enquanto carrega
                    documentosDiv.innerHTML = `
                    <div class="mensagem-carregando">
                        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #009640; margin-bottom: 15px;"></div>
                        <p>Carregando documentos necessários...</p>
                    </div>
                `;

                    // Fazemos uma requisição direta para a página PHP que processa os documentos
                    fetch('scripts/obter_documentos.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'tipo=' + tipo
                        })
                        .then(response => response.text())
                        .then(data => {
                            documentosDiv.innerHTML = data;

                            // Adicionamos os novos campos para o formulário principal
                            const inputsFile = documentosDiv.querySelectorAll('input[type="file"]');
                            inputsFile.forEach(input => {
                                input.setAttribute('form', 'form');
                                input.addEventListener('change', function() {
                                    validarArquivoPDF(this);
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
                        documentosDiv.innerHTML = `
                        <div class="mensagem-inicial">
                            <i class="fas fa-file-alt"></i>
                            <p>Selecione um tipo de alvará acima para visualizar os documentos necessários e iniciar o processo de requerimento.</p>
                        </div>
                    `;
                        return;
                    }

                    // Campos específicos para cada tipo
                    const tiposAmbientais = [
                        'licenca_previa_ambiental',
                        'licenca_previa_instalacao',
                        'licenca_instalacao_operacao',
                        'licenca_operacao',
                        'licenca_ambiental_unica',
                        'licenca_ampliacao',
                        'licenca_operacional_corretiva',
                        'autorizacao_supressao'
                    ];
                    const tiposExigemCTF = [
                        'licenca_operacao',
                        'licenca_instalacao_operacao',
                        'licenca_ambiental_unica',
                        'licenca_ampliacao',
                        'licenca_operacional_corretiva'
                    ];
                    const tiposExigemLicencaAnterior = ['licenca_operacao', 'licenca_instalacao_operacao'];

                    let campos = '';
                    if (tipo === 'construcao') {
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
                                    <option value="ART/TRT">ART/TRT</option>
                                </select>
                                <input required name="responsavel_tecnico_art" placeholder="Número do Documento *">
                            </div>
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
                                        <option value="ART/TRT">ART/TRT</option>
                                    </select>
                                    <input required name="responsavel_tecnico_art" placeholder="Número do Documento *" style="width: 70%;">
                                </div>
                            </div>
                            <textarea required name="descricao_atividade" placeholder="Descrição detalhada do desmembramento *" rows="4"></textarea>
                        `;
                    } else if (tiposAmbientais.includes(tipo)) {
                        campos = `
                            <div class="form-grid-2">
                                <input ${tiposExigemCTF.includes(tipo) ? 'required' : ''} name="ctf_numero" placeholder="Número do Cadastro Técnico Federal ${tiposExigemCTF.includes(tipo) ? '*' : '(se houver)'}">
                                <input ${tiposExigemLicencaAnterior.includes(tipo) ? 'required' : ''} name="licenca_anterior_numero" placeholder="Número da licença anterior ${tiposExigemLicencaAnterior.includes(tipo) ? '*' : '(se aplicável)'}">
                            </div>
                            <div class="form-grid-2">
                                <input required name="publicacao_diario_oficial" placeholder="Dados da publicação em Diário Oficial *">
                                <input required name="comprovante_pagamento" placeholder="Comprovante de pagamento (código/recibo) *">
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
                                        <option value="ART/TRT">ART/TRT</option>
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

        // Função para validar que apenas arquivos PDF sejam enviados
        function validarArquivoPDF(input) {
            if (!input.files || input.files.length === 0) {
                return true; // Se não há arquivo, não precisa validar
            }

            var file = input.files[0];
            var fileName = file.name.toLowerCase();

            if (!fileName.endsWith('.pdf')) {
                alert('Por favor, selecione apenas arquivos em formato PDF.');
                input.value = '';
                return false;
            }

            // Verificar tamanho do arquivo (máximo 10MB)
            if (file.size > 10485760) {
                alert('O arquivo é muito grande. Por favor, selecione um arquivo com tamanho máximo de 10MB.');
                input.value = '';
                return false;
            }

            return true;
        }

    </script>

    <style>

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
</body>

</html>
