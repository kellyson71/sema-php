<?php
// Verificação de redirecionamento para o domínio principal
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}

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
    <link rel="icon" href="./assets/img/prefeitura-logo.png" type="image/png">

    <meta name="description"
        content="Requerimento de Alvará Ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta name="keywords"
        content="alvará ambiental, meio ambiente, Pau dos Ferros, prefeitura, licenciamento ambiental, SEMA, requerimento">
    <meta name="author" content="Prefeitura de Pau dos Ferros">

    <meta property="og:title" content="Requerimento de Alvará - SEMA Pau dos Ferros">
    <meta property="og:description"
        content="Solicite seu alvará ambiental junto à Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
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
    <div class="feedback" id="feedback">

    </div>
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
                                    <option value="habite_se_simples">Alvará de Habite-se Simples</option>
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

    <!-- Modal de Confirmação para Substituição de Documento -->
    <div id="modal-substituir-documento" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Substituir Documento</h3>
            </div>
            <div class="modal-body">
                <p>Você já selecionou um documento para este campo. Deseja substituí-lo pelo novo arquivo?</p>
                <div class="documento-info">
                    <strong>Documento atual:</strong> <span id="documento-atual-nome"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancelar" onclick="cancelarSubstituicao()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn-confirmar" onclick="confirmarSubstituicao()">
                    <i class="fas fa-check"></i> Substituir
                </button>
            </div>
        </div>
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

        // Variáveis para controle do modal de substituição
        let arquivoTemporario = null;
        let inputTemporario = null;

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

        // Função para atualizar o status visual do arquivo
        function atualizarStatusArquivo(input, nomeArquivo = null) {
            const statusElement = document.getElementById('status-' + input.id);
            if (statusElement) {
                if (nomeArquivo) {
                    statusElement.innerHTML = '<span class="file-selected">' + nomeArquivo + '</span>';
                } else {
                    statusElement.innerHTML = '<span class="file-placeholder">Nenhum arquivo selecionado</span>';
                }
            }
        }

        // Função para verificar se já existe arquivo e mostrar modal de confirmação
        function verificarSubstituicaoDocumento(input) {
            console.log('Verificando substituição para:', input.id);
            console.log('Arquivo anterior:', input.dataset.arquivoAnterior);
            console.log('Arquivos selecionados:', input.files.length);

            // Se não há arquivo selecionado, não faz nada
            if (!input.files || input.files.length === 0) {
                atualizarStatusArquivo(input);
                return true;
            }

            // Verifica se já havia um arquivo selecionado anteriormente
            if (input.dataset.arquivoAnterior) {
                // Armazena o novo arquivo temporariamente
                arquivoTemporario = input.files[0];
                inputTemporario = input;

                // Mostra o nome do arquivo atual no modal
                document.getElementById('documento-atual-nome').textContent = input.dataset.arquivoAnterior;

                // Remove o arquivo atual (volta ao estado vazio temporariamente)
                const dataTransfer = new DataTransfer();
                input.files = dataTransfer.files;

                // Mantém o status visual do arquivo anterior
                atualizarStatusArquivo(input, input.dataset.arquivoAnterior);

                // Mostra o modal
                document.getElementById('modal-substituir-documento').style.display = 'flex';

                return false; // Impede o processamento normal
            } else {
                // Primeira vez selecionando um arquivo - valida e armazena o nome
                if (validarArquivoPDF(input)) {
                    const nomeArquivo = input.files[0].name;
                    input.dataset.arquivoAnterior = nomeArquivo;
                    atualizarStatusArquivo(input, nomeArquivo);
                    console.log('Arquivo armazenado:', input.dataset.arquivoAnterior);
                    return true;
                } else {
                    atualizarStatusArquivo(input);
                    return false;
                }
            }
        }

        // Função para confirmar a substituição do documento
        function confirmarSubstituicao() {
            if (inputTemporario && arquivoTemporario) {
                console.log('Confirmando substituição:', arquivoTemporario.name);

                // Valida o novo arquivo primeiro
                if (arquivoTemporario.name.toLowerCase().endsWith('.pdf') && arquivoTemporario.size <= 10485760) {
                    // Cria um novo FileList com o arquivo temporário
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(arquivoTemporario);
                    inputTemporario.files = dataTransfer.files;

                    // Atualiza o nome do arquivo anterior
                    inputTemporario.dataset.arquivoAnterior = arquivoTemporario.name;

                    // Atualiza o status visual
                    atualizarStatusArquivo(inputTemporario, arquivoTemporario.name);

                    console.log('Arquivo substituído com sucesso:', inputTemporario.dataset.arquivoAnterior);
                } else {
                    alert('Arquivo inválido. Selecione um arquivo PDF válido com menos de 10MB.');
                    // Restaura o arquivo anterior visualmente
                    atualizarStatusArquivo(inputTemporario, inputTemporario.dataset.arquivoAnterior);
                }

                // Limpa as variáveis temporárias
                arquivoTemporario = null;
                inputTemporario = null;
            }

            // Fecha o modal
            document.getElementById('modal-substituir-documento').style.display = 'none';
        }

        // Função para cancelar a substituição do documento
        function cancelarSubstituicao() {
            console.log('Cancelando substituição');

            // Se havia um input temporário, restaura o status visual
            if (inputTemporario && inputTemporario.dataset.arquivoAnterior) {
                atualizarStatusArquivo(inputTemporario, inputTemporario.dataset.arquivoAnterior);
                console.log('Mantendo arquivo anterior:', inputTemporario.dataset.arquivoAnterior);
            }

            // Limpa as variáveis temporárias
            arquivoTemporario = null;
            inputTemporario = null;

            // Fecha o modal
            document.getElementById('modal-substituir-documento').style.display = 'none';
        }

        // Função para fechar modal clicando fora dele
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('modal-substituir-documento');
            if (modal && event.target === modal) {
                cancelarSubstituicao();
            }
        });
    </script>

    <style>
        /* Estilo para a mensagem de formato de arquivo */
        .formato-arquivo {
            display: block;
            color: #dc3545;
            font-size: 12px;
            margin-top: 6px;
            font-style: italic;
        }

        /* Estilo para destacar quando o arquivo é inválido */
        input[type="file"].invalid-file {
            border: 1px solid #dc3545;
            background-color: #fff8f8;
        }

        /* Modal de Confirmação para Substituição de Documento */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
        }

        /* Estilo para o container do input de arquivo */
        .file-input-container {
            position: relative;
        }

        .file-status {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            pointer-events: none;
            font-size: 14px;
        }

        .file-placeholder {
            color: #6c757d;
            font-style: italic;
        }

        .file-selected {
            color: #009640;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-selected::before {
            content: "📄";
            font-size: 16px;
        }

        .upload-item input[type="file"] {
            position: relative;
            z-index: 1;
            opacity: 0;
            cursor: pointer;
        }

        .upload-item input[type="file"]:focus+.file-status {
            border-color: #009640;
            box-shadow: 0 0 0 2px rgba(0, 150, 64, 0.1);
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
        }

        .modal-header {
            padding: 20px 24px 0;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .modal-header i {
            font-size: 3rem;
            color: #ffc107;
            margin-bottom: 10px;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 0 24px 20px;
            text-align: center;
        }

        .modal-body p {
            margin: 0 0 15px;
            color: #666;
            line-height: 1.5;
        }

        .documento-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: left;
        }

        .documento-info strong {
            color: #333;
        }

        #documento-atual-nome {
            color: #009640;
            font-weight: 500;
        }

        .modal-footer {
            padding: 20px 24px;
            display: flex;
            gap: 12px;
            justify-content: center;
            border-top: 1px solid #e9ecef;
        }

        .btn-cancelar,
        .btn-confirmar {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .btn-cancelar {
            background: #6c757d;
            color: white;
        }

        .btn-cancelar:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn-confirmar {
            background: #009640;
            color: white;
        }

        .btn-confirmar:hover {
            background: #007a35;
            transform: translateY(-1px);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .btn-cancelar,
            .btn-confirmar {
                width: 100%;
                min-width: auto;
            }
        }
    </style>
</body>

</html>