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

<?php
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
    <link rel="icon" href="./assets/prefeitura-logo.png" type="image/png">

    <meta name="description"
        content="Requerimento de Alvará Ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta name="keywords"
        content="alvará ambiental, meio ambiente, Pau dos Ferros, prefeitura, licenciamento ambiental, SEMA, requerimento">
    <meta name="author" content="Prefeitura de Pau dos Ferros">

    <meta property="og:title" content="Requerimento de Alvará - SEMA Pau dos Ferros">
    <meta property="og:description"
        content="Solicite seu alvará ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta property="og:image" content="./assets/prefeitura-logo.png">
    <meta property="og:url" content="https://www.paudosferros.rn.gov.br/sema">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Requerimento de Alvará - Secretaria Municipal de Meio Ambiente">
    <meta name="twitter:description"
        content="Requerimento de Alvará Ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta name="twitter:image" content="./assets/prefeitura-logo.png">

    <!-- CSS -->
    <link rel="stylesheet" href="./css/index.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="./js/index.js" defer></script>
</head>

<body>
    <div class="feedback" id="feedback">

    </div>
    <header>
        <nav>
            <ul>
                <li><a href="https://www.instagram.com/prefeituradepaudosferros/">
                        <img src="./assets/instagram.png" alt="Instagram">
                    </a>
                </li>
                <li><a href="https://www.facebook.com/prefeituradepaudosferros/">
                        <img src="./assets/facebook.png" alt="Facebook">
                    </a>
                </li>
                <li><a href="https://twitter.com/paudosferros">
                        <img src="./assets/twitter.png" alt="Twitter">
                    </a>
                </li>
                <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros">
                        <img src="./assets/youtube.png" alt="YouTube">
                    </a>
                </li>
                <li><a href="https://instagram.com">
                        <img src="./assets/copy-url.png" alt="URL">
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
                    <img src="./assets/Logo_sema.png" alt="Secretaria Municipal de Meio Ambiente">
                    <h1>SECRETARIA MUNICIPAL DE MEIO AMBIENTE</h1>
                    <p>REQUERIMENTO DE ALVARÁ AMBIENTAL | PROTOCOLO ELETRÔNICO</p>
                </div>

                <!-- Seção 1: Dados do Requerente -->
                <div class="form-section">
                    <div class="form-part-2">
                        <input required id="name" name="requerente[nome]" placeholder="Nome Completo *">
                        <input required type="email" name="requerente[email]" placeholder="Digite seu email *">
                        <input oninput="mascara(this)" type="text" required name="requerente[cpf_cnpj]" id="cpf"
                            placeholder="CPF/CNPJ: 000.000.000-00 ou 00.000.000/0000-00" maxlength="18">
                        <input type="tel" maxlength="15" onkeyup="handlePhone(event)" required
                            name="requerente[telefone]" id="phone" placeholder="Digite seu Telefone *">
                    </div>
                </div>

                <!-- Seção 2: Dados do Proprietário -->
                <div class="form-section">
                    <div class="form-part-3">
                        <div class="y-n-field">
                            <p>O proprietário é o mesmo que o requerente?</p>
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
                            placeholder="Nome Completo do Proprietário *">
                        <input oninput="mascara(this)" type="text" name="proprietario[cpf_cnpj]"
                            id="proprietario_cpf_cnpj"
                            placeholder="CPF/CNPJ do Proprietário: 000.000.000-00 ou 00.000.000/0000-00" maxlength="18">
                    </div>
                </div>

                <!-- Seção 3: Endereço do Objetivo -->
                <div class="form-section">
                    <div class="form-part-2">
                        <input required name="endereco_objetivo"
                            placeholder="Endereço Completo (Rua, número, bairro, CEP) *">
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
                                    <option value="funcionamento">Alvará de Funcionamento</option>
                                    <option value="desmembramento">Alvará de Desmembramento e Remembramento</option>
                                    <option value="demolicao">Alvará de Demolição</option>
                                    <option value="loteamento">Alvará de Loteamento</option>
                                    <option value="transporte">Licenciamento de Transporte</option>
                                    <option value="uso_solo">Certidão de Uso e Ocupação do Solo</option>
                                    <option value="parques_circos">Alvará para Parques e Circos</option>
                                    <option value="licenca_previa">Licença Prévia</option>
                                    <option value="licenca_operacao">Licença de Operação</option>
                                    <option value="licenca_instalacao">Licença de Instalação</option>
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
                <img src="./assets/phone.png" alt="Telefone">
                WhatsApp (84) 99668-6413
            </div>
            <div>
                <img src="./assets/email.png" alt="Email">
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
                <img src="./assets/Logo.png" alt="SEAD">
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
                    fetch('obter_documentos.php', {
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

                    // Exemplo de campos específicos para cada tipo
                    let campos = '';
                    if (tipo === 'construcao') {
                        campos = `
                        <input required name="area_construcao" placeholder="Área total de construção (m²) *">
                        <input required name="numero_pavimentos" placeholder="Número de pavimentos *">
                    `;
                    } else if (tipo === 'licenca_operacao') {
                        campos = `
                        <input required name="atividade" placeholder="Descrição da atividade *">
                        <input required name="porte_empreendimento" placeholder="Porte do empreendimento *">
                    `;
                    } else {
                        campos = `
                        <textarea required name="descricao_atividade" placeholder="Descrição detalhada da atividade *" rows="4"></textarea>
                    `;
                    }

                    camposDinamicos.innerHTML = campos;
                });
            }
        });
    </script>
</body>

</html>