<?php
                                        // Verificação de redirecionamento para o domínio principal
                                        $host = $_SERVER['HTTP_HOST'] ?? '';
                                        if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
                                            $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $_SERVER['REQUEST_URI'];
                                            header("HTTP/1.1 301 Moved Permanently");
                                            header("Location: $redirect_url");
                                            exit();
                                        }

                                        // Iniciar sessão para receber mensagens
                                        session_start();

// Verificar se existe um protocolo na sessão
if (!isset($_SESSION['protocolo'])) {
    header('Location: index.php');

    exit;
}

$protocolo = $_SESSION['protocolo'];
$sucesso = $_SESSION['sucesso'] ?? 'Requerimento enviado com sucesso!';

// Limpar mensagens da sessão após mostrar
unset($_SESSION['protocolo']);
unset($_SESSION['sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requerimento Enviado - Secretaria Municipal de Meio Ambiente</title>
    <link rel="icon" href="./assets/img/prefeitura-logo.png" type="image/png">
    <link rel="stylesheet" href="./css/index.css">
    <style>
        .sucesso-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .sucesso-icon {
            font-size: 60px;
            color: #009851;
            margin-bottom: 20px;
        }

        .sucesso-titulo {
            color: #009851;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .registro-entrada {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;
        }

        .instrucoes {
            margin-top: 20px;
            text-align: left;
        }

        .instrucoes p {
            margin-bottom: 10px;
        }

        .botoes {
            margin-top: 30px;
        }

        .botao {
            display: inline-block;
            padding: 10px 20px;
            background-color: #009851;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 5px;
            transition: background-color 0.3s;
        }

        .botao:hover {
            background-color: #007840;
        }

        .botao.secundario {
            background-color: #6c757d;
        }

        .botao.secundario:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <header>
        <!-- Header igual ao da página inicial -->
        <nav>
            <ul>
                <a>
                    <li><a href="https://www.instagram.com/prefeituradepaudosferros/">
                            <img src="./assets/img/instagram.png">
                        </a>
                    </li>
                </a>
                <a>
                    <li><a href="https://www.facebook.com/prefeituradepaudosferros/">
                            <img src="./assets/img/facebook.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://twitter.com/paudosferros">
                            <img src="./assets/img/twitter.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros">
                            <img src="./assets/img/youtube.png">
                        </a>
                </a>
                <a>
                    <li><a href="https://instagram.com">
                            <img src="./assets/img/copy-url.png">
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
        <div class="sucesso-container">
            <div class="sucesso-icon">✓</div>
            <h1 class="sucesso-titulo"><?php echo $sucesso; ?></h1>
            <p>Seu requerimento foi recebido e será analisado pela nossa equipe.</p>

            <div class="registro-entrada">
                Registro de Entrada: <?php echo $protocolo; ?>
            </div>
            <div class="instrucoes">
                <p><strong>⚠️ IMPORTANTE:</strong></p>
                <p>1. Guarde este número de registro de entrada para referência interna.</p>
                <p>2. <strong>Este número é apenas um registro de entrada interno do sistema. O número de protocolo oficial para acompanhamento no portal da prefeitura será enviado posteriormente via email para o endereço cadastrado.</strong></p>
                <p>3. Após o processamento pela nossa equipe, você receberá o protocolo oficial que deverá ser utilizado para acompanhamento no sistema de tributos municipais.</p>
                <p>4. Em caso de dúvidas, entre em contato com a Secretaria Municipal de Meio Ambiente pelo telefone (84) 99668-6413.</p>
            </div>
            <div class="botoes">
                <a href="index.php" class="botao secundario">Voltar ao Início</a>
                <a href="https://gestor.tributosmunicipais.com.br/redesim/prefeitura/paudosferros/views/publico/portaldocontribuinte/index.xhtml" class="botao">Consultar Requerimento</a>
            </div>
        </div>
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
