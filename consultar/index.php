<?php
// Verificação de redirecionamento para o domínio principal
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}

                // Incluir arquivos necessários
                require_once '../includes/config.php';
                require_once '../includes/database.php';
                require_once '../includes/functions.php';

                // Inicializar variáveis
                $mensagem = null;
                $resultado = false;
                $requerimento = null;
                $requerente = null;
                $proprietario = null;
                $documentos = [];

                // Processar formulário de consulta
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['protocolo'])) {
                    $protocolo = trim($_POST['protocolo']);

                    if (!empty($protocolo)) {
                        try {
                            // Conectar ao banco de dados
                            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                            $pdo->exec("SET NAMES utf8");

                            // Buscar requerimento
                            $stmt = $pdo->prepare("
                SELECT r.*, ta.nome as tipo_alvara 
                FROM requerimentos r 
                LEFT JOIN tipos_alvara ta ON r.tipo_alvara = ta.id 
                WHERE r.protocolo = ?
            ");
                            $stmt->execute([$protocolo]);
                            $requerimento = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($requerimento) {
                                $resultado = true;

                                // Buscar dados do requerente
                                $stmt = $pdo->prepare("SELECT * FROM requerentes WHERE id = ?");
                                $stmt->execute([$requerimento['requerente_id']]);
                                $requerente = $stmt->fetch(PDO::FETCH_ASSOC);

                                // Buscar dados do proprietário
                                $stmt = $pdo->prepare("SELECT * FROM proprietarios WHERE id = ?");
                                $stmt->execute([$requerimento['proprietario_id']]);
                                $proprietario = $stmt->fetch(PDO::FETCH_ASSOC);

                                // Buscar documentos
                                $stmt = $pdo->prepare("SELECT * FROM documentos WHERE requerimento_id = ? ORDER BY campo_formulario");
                                $stmt->execute([$requerimento['id']]);
                                $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } else {
                                $mensagem = [
                                    'tipo' => 'erro',
                                    'texto' => 'Protocolo não encontrado. Verifique o número digitado e tente novamente.'
                                ];
                            }
                        } catch (Exception $e) {
                            $mensagem = [
                                'tipo' => 'erro',
                                'texto' => 'Erro ao consultar o protocolo. Tente novamente mais tarde.'
                            ];
                        }
                    } else {
                        $mensagem = [
                            'tipo' => 'erro',
                            'texto' => 'Por favor, digite um número de protocolo.'
                        ];
                    }
                }
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Consulta de Alvarás - Secretaria Municipal de Meio Ambiente</title>
    <link rel="icon" href="../assets/prefeitura-logo.png" type="image/png">

    <meta name="description" content="Consulte o status do seu alvará na Secretaria Municipal de Meio Ambiente de Pau dos Ferros.">
    <meta name="keywords" content="alvará, consulta, meio ambiente, Pau dos Ferros, prefeitura, SEMA">
    <meta name="author" content="Prefeitura de Pau dos Ferros">

    <!-- CSS -->
    <link rel="stylesheet" href="../css/index.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Tailwind para estilização avançada -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                    },
                    fontFamily: {
                        sans: ['Segoe UI', 'Roboto', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        /* Estilos específicos para a caixa de consulta */
        .box-consulta {
            max-width: 800px;
            margin: 50px auto;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .box-consulta::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #009640, #0dcaf0);
            z-index: 1;
        }

        .box-consulta h2 {
            color: #024287;
            font-size: 28px;
            margin-bottom: 30px;
            font-weight: 700;
        }

        .box-consulta .icone {
            font-size: 48px;
            color: #009851;
            margin-bottom: 20px;
        }

        .campo-consulta {
            position: relative;
            margin: 30px 0;
        }

        .campo-consulta input {
            width: 100%;
            padding: 16px 50px 16px 20px;
            border: 2px solid rgba(2, 66, 135, 0.2);
            border-radius: 12px;
            font-size: 16px;
            color: #024287;
            transition: all 0.3s;
        }

        .campo-consulta input:focus {
            border-color: #0dcaf0;
            box-shadow: 0 0 0 4px rgba(13, 202, 240, 0.25);
            outline: none;
        }

        .botao-consulta {
            display: inline-block;
            padding: 15px 40px;
            background-color: #009640;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0, 150, 64, 0.2);
        }

        .botao-consulta:hover {
            background-color: #008748;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 150, 64, 0.3);
        }

        .botao-consulta i {
            margin-right: 10px;
        }

        /* Adicionando margem no final da página para evitar que o footer fique encostado */
        main {
            min-height: calc(100vh - 350px);
            /* Garante altura mínima considerando o tamanho do footer */
            margin-bottom: 80px;
            /* Adiciona espaço entre o conteúdo principal e o footer */
        }

        /* Estilo para mensagens de erro/sucesso */
        .mensagem {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .erro {
            background-color: #fef2f2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        .sucesso {
            background-color: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        /* Estilos para os resultados da consulta */
        .resultado-consulta {
            text-align: left;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(90deg, #009640, #047857);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
            font-size: 18px;
        }

        .card-body {
            background: #f9fafb;
            padding: 20px;
            border-radius: 0 0 10px 10px;
            border: 1px solid #e5e7eb;
            border-top: none;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
        }

        .status-analise {
            background-color: #fff9c2;
            color: #854d0e;
        }

        .status-aprovado {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-reprovado {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-pendente {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .info-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 18px;
            color: #047857;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        /* Botão para voltar à página inicial */
        .botao-voltar {
            display: inline-block;
            padding: 15px 30px;
            background-color: #024287;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
            box-shadow: 0 4px 12px rgba(2, 66, 135, 0.2);
            text-decoration: none;
        }

        .botao-voltar:hover {
            background-color: #013a77;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(2, 66, 135, 0.3);
        }

        .botao-voltar i {
            margin-right: 8px;
        }

        .acoes-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>
    <header>
        <nav>
            <ul>
                <li><a href="https://www.instagram.com/prefeituradepaudosferros/">
                        <img src="../assets/img/instagram.png" alt="Instagram">
                    </a>
                </li>
                <li><a href="https://www.facebook.com/prefeituradepaudosferros/">
                        <img src="../assets/img/facebook.png" alt="Facebook">
                    </a>
                </li>
                <li><a href="https://twitter.com/paudosferros">
                        <img src="../assets/img/twitter.png" alt="Twitter">
                    </a>
                </li>
                <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros">
                        <img src="../assets/img/youtube.png" alt="YouTube">
                    </a>
                </li>
                <li><a href="https://instagram.com">
                        <img src="../assets/img/copy-url.png" alt="URL">
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
            <div class="box-consulta">
                <div class="icone">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h2>Consulta de Alvará</h2>

                <?php if ($mensagem): ?>
                    <div class="mensagem <?php echo $mensagem['tipo']; ?>">
                        <?php echo $mensagem['texto']; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$resultado): ?>
                    <p>Digite o número do protocolo para verificar o status do seu alvará ou requerimento junto à Secretaria Municipal de Meio Ambiente.</p>
                    <form action="" method="post">
                        <div class="campo-consulta">
                            <input type="text" id="protocolo" name="protocolo" placeholder="Digite o número do protocolo..." required>
                        </div>
                        <button type="submit" class="botao-consulta" id="btn-consultar">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </form>

                    <div class="acoes-container">
                        <a href="../index.php" class="botao-voltar">
                            <i class="fas fa-home"></i> Página Inicial
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Resultado da Consulta -->
                    <div class="resultado-consulta">
                        <div class="card-header flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-clipboard-list mr-2"></i>
                                <span>Detalhes do Requerimento</span>
                            </div>
                            <div>
                                <?php
                                $statusClass = 'status-' . strtolower($requerimento['status']);
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo formatarStatus($requerimento['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Protocolo</div>
                                    <div class="info-value font-bold text-xl"><?php echo sanitize($requerimento['protocolo']); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Tipo de Alvará</div>
                                    <div class="info-value"><?php echo sanitize($requerimento['tipo_alvara']); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Data de Envio</div>
                                    <div class="info-value"><?php echo formatarData($requerimento['data_envio']); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Última Atualização</div>
                                    <div class="info-value"><?php echo formatarData($requerimento['data_atualizacao']); ?></div>
                                </div>
                            </div>

                            <?php if (!empty($requerimento['endereco_objetivo'])): ?>
                                <div class="mt-6">
                                    <div class="section-title"><i class="fas fa-map-marker-alt mr-2"></i> Endereço do Objetivo</div>
                                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                                        <?php echo sanitize($requerimento['endereco_objetivo']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($requerimento['observacoes'])): ?>
                                <div class="mt-6">
                                    <div class="section-title"><i class="fas fa-comment-alt mr-2"></i> Observações</div>
                                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                                        <?php echo nl2br(sanitize($requerimento['observacoes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($requerente): ?>
                                <div class="mt-6">
                                    <div class="section-title"><i class="fas fa-user mr-2"></i> Dados do Requerente</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <div class="info-label">Nome</div>
                                            <div class="info-value"><?php echo sanitize($requerente['nome']); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">CPF/CNPJ</div>
                                            <div class="info-value"><?php echo sanitize($requerente['cpf_cnpj']); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Email</div>
                                            <div class="info-value"><?php echo sanitize($requerente['email']); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Telefone</div>
                                            <div class="info-value"><?php echo sanitize($requerente['telefone']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($proprietario): ?>
                                <div class="mt-6">
                                    <div class="section-title"><i class="fas fa-home mr-2"></i> Dados do Proprietário</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <div class="info-label">Nome</div>
                                            <div class="info-value"><?php echo sanitize($proprietario['nome']); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">CPF/CNPJ</div>
                                            <div class="info-value"><?php echo sanitize($proprietario['cpf_cnpj']); ?></div>
                                        </div>
                                        <?php if ($proprietario['mesmo_requerente']): ?>
                                            <div class="col-span-2">
                                                <div class="bg-blue-100 text-blue-800 p-2 rounded-md mt-2">
                                                    <i class="fas fa-info-circle mr-2"></i> O proprietário é o mesmo que o requerente.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($documentos && count($documentos) > 0): ?>
                                <div class="mt-6">
                                    <div class="section-title"><i class="fas fa-file-pdf mr-2"></i> Documentos Enviados</div>
                                    <div class="divide-y divide-gray-200">
                                        <?php foreach ($documentos as $documento): ?>
                                            <div class="py-3">
                                                <div class="flex items-start">
                                                    <i class="fas fa-file-alt text-red-500 mr-3 mt-1"></i>
                                                    <div>
                                                        <div class="font-medium"><?php echo sanitize($documento['nome_original']); ?></div>
                                                        <div class="text-sm text-gray-500 mt-1 flex flex-wrap gap-x-4 gap-y-2">
                                                            <span class="flex items-center">
                                                                <i class="fas fa-tag text-gray-400 mr-1"></i>
                                                                <?php echo sanitize($documento['campo_formulario']); ?>
                                                            </span>
                                                            <span class="flex items-center">
                                                                <i class="fas fa-database text-gray-400 mr-1"></i>
                                                                <?php echo formatarTamanho($documento['tamanho']); ?>
                                                            </span>
                                                            <span class="flex items-center">
                                                                <i class="fas fa-calendar text-gray-400 mr-1"></i>
                                                                <?php echo formatarData($documento['data_upload']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Botões de ação -->
                        <div class="acoes-container">
                            <a href="../index.php" class="botao-voltar">
                                <i class="fas fa-home"></i> Página Inicial
                            </a>
                            <a href="index.php" class="botao-consulta">
                                <i class="fas fa-search"></i> Nova Consulta
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <div>
            <div>
                <a href="../consultar/index.php" class="consulta-btn">
                    <i class="fas fa-search"></i>
                    <span>Consulte seu Alvará</span>
                </a>
            </div>
            <div>
                <img src="../assets/phone.png" alt="Telefone">
                WhatsApp (84) 99668-6413
            </div>
            <div>
                <img src="../assets/email.png" alt="Email">
                fiscalizacaosemapdf@gmail.com
            </div>
        </div>
        <div>
            <span>
                © 2025 - Todos os direitos reservados. Programa da&ensp;<a
                    href="https://www.paudosferros.rn.gov.br/">Prefeitura de Pau dos Ferros</a>
                <p>Desenvolvido por&ensp;<a href="https://github.com/kellyson71"
                        style="text-decoration: none; color: inherit;">Kellyson Raphael</a></p>
            </span>
            <div>
                <img src="../assets/Logo.png" alt="SEAD">
            </div>
        </div>
    </footer>

    <script>
        // Funções para alterar o tamanho da fonte
        function increaseFont() {
            const root = document.documentElement;
            const fontSize = getComputedStyle(root).getPropertyValue('font-size');
            const currentSize = parseFloat(fontSize);
            root.style.fontSize = `${currentSize + 1}px`;
        }

        function decreaseFont() {
            const root = document.documentElement;
            const fontSize = getComputedStyle(root).getPropertyValue('font-size');
            const currentSize = parseFloat(fontSize);

            // Não permitir fontes muito pequenas
            if (currentSize > 12) {
                root.style.fontSize = `${currentSize - 1}px`;
            }
        }

        // Permitir consultar ao pressionar Enter no campo de texto
        document.addEventListener('DOMContentLoaded', function() {
            const protocoloInput = document.getElementById('protocolo');
            if (protocoloInput) {
                protocoloInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        document.getElementById('btn-consultar').click();
                    }
                });
            }
        });
    </script>
</body>

</html>