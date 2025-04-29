<?php
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
    <link rel="icon" href="./assets/prefeitura-logo.png" type="image/png">
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

        .protocolo {
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
        <div class="sucesso-container">
            <div class="sucesso-icon">✓</div>
            <h1 class="sucesso-titulo"><?php echo $sucesso; ?></h1>
            <p>Seu requerimento foi recebido e será analisado pela nossa equipe.</p>

            <div class="protocolo">
                Número do Protocolo: <?php echo $protocolo; ?>
            </div>

            <div class="instrucoes">
                <p><strong>Importante:</strong></p>
                <p>1. Guarde o número do protocolo para consultas futuras.</p>
                <p>2. Acompanhe o status do seu requerimento através da página de consulta.</p>
                <p>3. Em caso de dúvidas, entre em contato com a Secretaria Municipal de Meio Ambiente pelo telefone (84) 99668-6413.</p>
            </div>

            <div class="botoes">
                <a href="index.php" class="botao secundario">Voltar ao Início</a>
                <a href="./consultar/index.php" class="botao">Consultar Requerimento</a>
            </div>
        </div>
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
                © 2023 - Todos os direitos reservados. Programa da&ensp;<a href="https://www.paudosferros.rn.gov.br/">Prefeitura de Pau dos Ferros</a>
                <p>Desenvolvido por&ensp;<a href="https://github.com/kellyson71" style="text-decoration: none; color: inherit;">Kellyson Raphael</a></p>
            </span>
            <div>
                <img src="./assets/Secretaria Municipal de Administração - SEAD.png" style="width: 100%; max-width: 200px; height: auto;">
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