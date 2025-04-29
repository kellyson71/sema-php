<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

$mensagem = getMensagem();
$resultado = null;
$requerimento = null;
$requerente = null;
$proprietario = null;
$documentos = null;

// Verificar se foi enviado um protocolo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['protocolo'])) {
    $protocolo = trim($_POST['protocolo']);

    if (empty($protocolo)) {
        setMensagem('erro', 'Por favor, informe o número do protocolo.');
    } else {
        // Buscar o requerimento pelo protocolo
        $requerimentoModel = new Requerimento();
        $requerimento = $requerimentoModel->buscarPorProtocolo($protocolo);

        if ($requerimento) {
            // Buscar dados do requerente
            $requerenteModel = new Requerente();
            $requerente = $requerenteModel->buscarPorId($requerimento['requerente_id']);

            // Buscar dados do proprietário
            if (!empty($requerimento['proprietario_id'])) {
                $proprietarioModel = new Proprietario();
                $proprietario = $proprietarioModel->buscarPorId($requerimento['proprietario_id']);
            }

            // Buscar documentos do requerimento
            $documentoModel = new Documento();
            $documentos = $documentoModel->buscarPorRequerimento($requerimento['id']);

            $resultado = true;
        } else {
            setMensagem('erro', 'Protocolo não encontrado. Verifique o número informado.');
        }
    }

    $mensagem = getMensagem();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Requerimento - Secretaria Municipal de Meio Ambiente</title>
    <link rel="icon" href="../assets/prefeitura-logo.png" type="image/png">
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .consulta-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .consulta-form {
            text-align: center;
            margin-bottom: 30px;
        }

        .consulta-form h2 {
            color: #009851;
            margin-bottom: 20px;
        }

        .consulta-form p {
            margin-bottom: 15px;
            color: #555;
        }

        .protocolo-input {
            display: flex;
            max-width: 500px;
            margin: 0 auto;
        }

        .protocolo-input input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
        }

        .protocolo-input button {
            padding: 12px 20px;
            background-color: #009851;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .protocolo-input button:hover {
            background-color: #007840;
        }

        .resultado-container {
            margin-top: 20px;
        }

        .resultado-titulo {
            color: #009851;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .info-bloco {
            margin-bottom: 25px;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 15px;
        }

        .info-bloco h3 {
            margin-top: 0;
            color: #444;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-item label {
            display: block;
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .info-valor {
            font-size: 16px;
            color: #333;
        }

        .documentos-lista {
            list-style: none;
            padding: 0;
        }

        .documento-item {
            padding: 10px 15px;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .documento-info {
            flex: 1;
        }

        .documento-nome {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .documento-meta {
            font-size: 14px;
            color: #777;
        }

        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            color: white;
            display: inline-block;
        }

        .status-analise {
            background-color: #ffc107;
        }

        .status-aprovado {
            background-color: #28a745;
        }

        .status-reprovado {
            background-color: #dc3545;
        }

        .status-pendente {
            background-color: #6c757d;
        }

        .documento-acao a {
            padding: 8px 12px;
            background-color: #009851;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .documento-acao a:hover {
            background-color: #007840;
        }

        .empty-result {
            text-align: center;
            padding: 20px;
        }

        .empty-result i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .mensagem {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .mensagem-sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensagem-erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .mensagem-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .mensagem-alerta {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>

<body>
    <header>
        <nav>
            <ul>
                <a>
                    <li><a href="https://www.instagram.com/prefeituradepaudosferros/">
                            <img src="../assets/instagram.png">
                        </a>
                    </li>
                </a>
                <a>
                    <li><a href="https://www.facebook.com/prefeituradepaudosferros/">
                            <img src="../assets/facebook.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://twitter.com/paudosferros">
                            <img src="../assets/twitter.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros">
                            <img src="../assets/youtube.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://instagram.com">
                            <img src="../assets/copy-url.png">
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
        <div class="consulta-container">
            <div class="consulta-form">
                <h2>Consultar Requerimento</h2>
                <p>Informe o número do protocolo para consultar o status do seu requerimento.</p>

                <?php if ($mensagem): ?>
                    <div class="mensagem mensagem-<?php echo $mensagem['tipo']; ?>">
                        <?php echo $mensagem['texto']; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="protocolo-input">
                        <input type="text" name="protocolo" placeholder="Digite o número do protocolo"
                            value="<?php echo isset($_POST['protocolo']) ? htmlspecialchars($_POST['protocolo']) : ''; ?>" required>
                        <button type="submit"><i class="fas fa-search"></i> Consultar</button>
                    </div>
                </form>
            </div>

            <?php if (isset($resultado) && $resultado): ?>
                <div class="resultado-container">
                    <h3 class="resultado-titulo">Detalhes do Requerimento</h3>

                    <?php if ($requerimento): ?>
                        <div class="info-bloco">
                            <h3>Informações Gerais</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Protocolo:</label>
                                    <div class="info-valor"><?php echo sanitize($requerimento['protocolo']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Tipo de Alvará:</label>
                                    <div class="info-valor"><?php echo sanitize($requerimento['tipo_alvara']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Status:</label>
                                    <div class="info-valor"><?php echo formatarStatus($requerimento['status']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Data de Envio:</label>
                                    <div class="info-valor"><?php echo formatarData($requerimento['data_envio']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Última Atualização:</label>
                                    <div class="info-valor"><?php echo formatarData($requerimento['data_atualizacao']); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if ($requerimento['observacoes']): ?>
                            <div class="info-bloco">
                                <h3>Observações</h3>
                                <div class="info-item">
                                    <div class="info-valor"><?php echo nl2br(sanitize($requerimento['observacoes'])); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($requerente): ?>
                            <div class="info-bloco">
                                <h3>Dados do Requerente</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Nome:</label>
                                        <div class="info-valor"><?php echo sanitize($requerente['nome']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <label>CPF/CNPJ:</label>
                                        <div class="info-valor"><?php echo sanitize($requerente['cpf_cnpj']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <label>E-mail:</label>
                                        <div class="info-valor"><?php echo sanitize($requerente['email']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <label>Telefone:</label>
                                        <div class="info-valor"><?php echo sanitize($requerente['telefone']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($proprietario): ?>
                            <div class="info-bloco">
                                <h3>Dados do Proprietário</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Nome:</label>
                                        <div class="info-valor"><?php echo sanitize($proprietario['nome']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <label>CPF/CNPJ:</label>
                                        <div class="info-valor"><?php echo sanitize($proprietario['cpf_cnpj']); ?></div>
                                    </div>
                                    <?php if ($proprietario['mesmo_requerente']): ?>
                                        <div class="info-item">
                                            <label>Observação:</label>
                                            <div class="info-valor">O proprietário é o mesmo que o requerente.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="info-bloco">
                            <h3>Endereço do Objetivo</h3>
                            <div class="info-item">
                                <div class="info-valor"><?php echo sanitize($requerimento['endereco_objetivo']); ?></div>
                            </div>
                        </div>

                        <?php if ($documentos && count($documentos) > 0): ?>
                            <div class="info-bloco">
                                <h3>Documentos Enviados</h3>
                                <ul class="documentos-lista">
                                    <?php foreach ($documentos as $documento): ?>
                                        <li class="documento-item">
                                            <div class="documento-info">
                                                <div class="documento-nome"><?php echo sanitize($documento['nome_original']); ?></div>
                                                <div class="documento-meta">
                                                    Tipo: <?php echo sanitize($documento['campo_formulario']); ?> |
                                                    Tamanho: <?php echo formatarTamanho($documento['tamanho']); ?> |
                                                    Enviado em: <?php echo formatarData($documento['data_upload']); ?>
                                                </div>
                                            </div>
                                            <!-- As URLs de arquivos são desabilitadas pois exigiriam mais segurança -->
                                            <!--
                            <div class="documento-acao">
                                <a href="../<?php echo sanitize($documento['caminho']); ?>" target="_blank">
                                    <i class="fas fa-download"></i> Baixar
                                </a>
                            </div>
                            -->
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-result">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Nenhum requerimento encontrado com este protocolo.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="consulta-form" style="margin-top: 30px;">
                <a href="../index.php" style="color: #009851; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Voltar para o formulário
                </a>
            </div>
        </div>
    </main>

    <footer>
        <div>
            <div>
                <a href="../consultar/index.php" class="consulta-btn">
                    <i class="fas fa-search"></i>
                    <span>Consulte sua inscrição</span>
                </a>
            </div>
            <div>
                <img src="../assets/phone.png">
                (84) 99858-6712
            </div>
            <div>
                <img src="../assets/email.png">
                pmpfestagio@gmail.com
            </div>
        </div>
        <div>
            <span>
                © 2023 - Todos os direitos reservados. Programa da&ensp;<a href="https://www.paudosferros.rn.gov.br/">Prefeitura de Pau dos Ferros</a>
                <p>Desenvolvido por&ensp;<a href="https://github.com/kellyson71" style="text-decoration: none; color: inherit;">Kellyson Raphael</a></p>
            </span>
            <div>
                <img src="../assets/Secretaria Municipal de Administração - SEAD.png" style="width: 100%; max-width: 200px; height: auto;">
            </div>
        </div>
    </footer>

    <script>
        function increaseFont() {
            document.body.style.fontSize = parseInt(window.getComputedStyle(document.body).fontSize) + 1 + "px";
        }

        function decreaseFont() {
            document.body.style.fontSize = parseInt(window.getComputedStyle(document.body).fontSize) - 1 + "px";
        }
    </script>
</body>

</html>