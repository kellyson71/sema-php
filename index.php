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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

    <link rel="stylesheet" href="./css/index.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="./js/index.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="feedback" id="feedback">

    </div>
    <header>
        <nav>
            <ul>
                <a>
                    <li><a href="https://www.instagram.com/prefeituradepaudosferros/">
                            <img src="./assets/instagram.png">
                        </a>
                    </li>
                </a>
                <a>
                    <li><a href="https://www.facebook.com/prefeituradepaudosferros/">
                            <img src="./assets/facebook.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://twitter.com/paudosferros">
                            <img src="./assets/twitter.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros">
                            <img src="./assets/youtube.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://instagram.com">
                            <img src="./assets/copy-url.png">
                        </a>
                </a>
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
                    <img src="./assets/prefeitura-logo.png" alt="prefeitura de pau dos ferros">
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
                <div class="form-section">
                    <div class="form-part-2">
                        <select required name="tipo_alvara" id="tipo_alvara" title="Tipo de Alvará">
                            <option value="" hidden>Selecione o tipo de alvará...</option>
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
                    </div>

                    <div id="campos_dinamicos" class="form-part-2">
                        <!-- Os campos específicos serão carregados aqui -->
                    </div>

                    <div id="documentos_necessarios" class="form-part-2">
                        <!-- A lista de documentos necessários será exibida aqui -->
                        <p>Selecione um tipo de alvará para ver os documentos necessários.</p>
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
                    <span>Consulte sua inscrição</span>
                </a>
            </div>
            <div>
                <img src="./assets/phone.png">
                (84) 99858-6712
            </div>
            <div>
                <img src="./assets/email.png">
                pmpfestagio@gmail.com
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
                <img src="./assets/Secretaria Municipal de Administração - SEAD.png"
                    style="width: 100%; max-width: 200px; height: auto;">
            </div>
        </div>
    </footer>

    <!-- Loading Spinner -->
    <div id="loading" class="loading" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <style>
    .loading {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #009851;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .form-section {
        margin-bottom: 25px;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 15px;
    }

    .form-section h2 {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        font-size: 18px;
        color: #333;
    }

    .form-section h2 span {
        background-color: #009851;
        color: white;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        font-size: 14px;
        font-weight: bold;
    }

    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: vertical;
    }

    /* Estilo para a lista de documentos */
    .documentos-container {
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 15px;
        margin-top: 15px;
    }

    .documentos-container h3 {
        color: #009851;
        margin-top: 0;
        font-size: 18px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 10px;
    }

    .documentos-container h4 {
        color: #333;
        margin-top: 15px;
        font-size: 16px;
    }

    .lista-documentos {
        list-style-type: none;
        padding-left: 0;
    }

    .lista-documentos li {
        margin-bottom: 8px;
        padding-left: 20px;
        position: relative;
    }

    .lista-documentos li::before {
        content: "•";
        color: #009851;
        font-size: 18px;
        position: absolute;
        left: 0;
        top: -2px;
    }

    .observacoes {
        margin-top: 15px;
        font-style: italic;
    }

    .contato {
        margin-top: 15px;
        background-color: #e9f7ef;
        padding: 10px;
        border-radius: 5px;
    }

    /* Estilos para o formulário de upload */
    .uploads-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }

    @media (min-width: 768px) {
        .uploads-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    .upload-item {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
        transition: all 0.3s ease;
    }

    .upload-item:hover {
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .upload-item label {
        display: block;
        font-size: 14px;
        margin-bottom: 8px;
        color: #333;
    }

    .upload-item input[type="file"] {
        width: 100%;
        padding: 8px;
        border: 1px dashed #ccc;
        border-radius: 4px;
        background-color: #f9f9f9;
    }

    .upload-item input[type="file"]:hover {
        border-color: #009851;
    }
    </style>

    <script>
    // Função para mostrar/esconder campos do proprietário
    document.addEventListener('DOMContentLoaded', function() {
        const mesmoSimRadio = document.getElementById('mesmo-sim');
        const mesmoNaoRadio = document.getElementById('mesmo-nao');
        const proprietarioFields = document.getElementById('proprietario-fields');

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
        const documentosDiv = document.getElementById('documentos_necessarios');

        if (tipoAlvaraSelect) {
            tipoAlvaraSelect.addEventListener('change', function() {
                const tipo = this.value;

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
                        documentosDiv.innerHTML =
                            '<p>Erro ao carregar os documentos necessários.</p>';
                    });

                // Carregamento de campos dinâmicos específicos (mantido do código original)
                const camposDinamicos = document.getElementById('campos_dinamicos');

                if (tipo === '') {
                    camposDinamicos.innerHTML = '';
                    documentosDiv.innerHTML =
                        '<p>Selecione um tipo de alvará para ver os documentos necessários.</p>';
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