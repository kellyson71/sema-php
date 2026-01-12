<?php
require_once 'conexao.php';
verificaLogin();

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: requerimentos.php");
    exit;
}

$id = (int)$_GET['id'];

// Buscar dados do requerimento
$stmt = $pdo->prepare("
    SELECT r.*, 
           req.nome as requerente_nome, 
           req.cpf_cnpj as requerente_cpf_cnpj, 
           req.telefone as requerente_telefone, 
           req.email as requerente_email,
           p.nome as proprietario_nome,
           p.cpf_cnpj as proprietario_cpf_cnpj
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    LEFT JOIN proprietarios p ON r.proprietario_id = p.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$requerimento = $stmt->fetch();

if (!$requerimento) {
    header("Location: requerimentos.php");
    exit;
}

// Obter ano atual
$anoAtual = date('Y');

// Formatações
function formatarData($data)
{
    return date('d/m/Y', strtotime($data));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Configurações para impressão sem cabeçalhos/rodapés -->
    <meta name="format-detection" content="telephone=no">
    <title>Capa do Processo - <?php echo htmlspecialchars($requerimento['protocolo']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 2cm;

            /* Desabilitar cabeçalhos e rodapés padrão do navegador */
            @top-left {
                content: "";
            }

            @top-center {
                content: "";
            }

            @top-right {
                content: "";
            }

            @bottom-left {
                content: "";
            }

            @bottom-center {
                content: "";
            }

            @bottom-right {
                content: "";
            }
        }

        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            padding: 1cm;
            margin: 0;
        }

        .pagina {
            background: white;
            width: 21cm;
            min-height: 29.7cm;
            margin: auto;
            padding: 1.2cm;
            box-sizing: border-box;
            color: #000;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .conteudo {
            flex: 1;
        }

        .logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .logo img {
            height: 80px;
        }

        .titulo-topo {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        h1 {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
            font-size: 22pt;
            letter-spacing: 1px;
        }

        .dados {
            margin-top: 20px;
            font-size: 12pt;
            line-height: 1.6;
        }

        .negrito {
            font-weight: bold;
        }

        .linha {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 200px;
            height: 20px;
            vertical-align: bottom;
        }

        .rodape {
            padding-top: 20px;
            font-size: 10pt;
            text-align: center;
            border-top: 1px solid #ddd;
            margin-top: auto;
        }

        .rodape span {
            margin: 0 10px;
        }

        /* Botões de ação (não imprimem) */
        .acoes {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            font-size: 12px;
        }

        .btn-fechar {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            opacity: 0.8;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            body.printing {
                margin: 0 !important;
                padding: 0 !important;
            }

            .pagina {
                margin: 0;
                box-shadow: none;
                height: auto;
                width: auto;
                padding: 0;
                position: relative;
                min-height: 100vh;
            }

            .conteudo {
                min-height: calc(100vh - 100px);
            }

            .rodape {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 20px;
            }

            .acoes {
                display: none !important;
            }

            /* Garantir que não apareçam elementos do navegador */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <!-- Botão de fechar (apenas para emergência) -->
    <div class="acoes">
        <button onclick="window.close()" class="btn btn-fechar">
            ✖ Fechar
        </button>
    </div>

    <div class="pagina">

        <div class="conteudo">
            <div class="logo">
                <img src="../assets/img/Logo.png" alt="Logo da Prefeitura">
            </div>

            <div class="titulo-topo">
                ESTADO DO RIO GRANDE DO NORTE<br>
                MUNICÍPIO DE PAU DOS FERROS<br>
                SECRETARIA MUNICIPAL DO MEIO AMBIENTE - SEMA
            </div>

            <h1>PROCESSO</h1>

            <div class="dados">
                <div><span class="negrito">Nº DO PROCESSO SEMA:</span> ___________________________________</div>
                <div><span class="negrito">ANO:</span> <?php echo $anoAtual; ?></div>
                <div><span class="negrito">PREFIXO:</span> PMPF</div>
                <div><span class="negrito">Nº DE PROTOCOLO SISTEMA:</span> <?php echo htmlspecialchars($requerimento['protocolo']); ?></div>
                <div><span class="negrito">Nº DE PROTOCOLO GERAL:</span> ___________________________________</div>

                <br>

                <div><span class="negrito">ASSUNTO:</span> <?php echo htmlspecialchars($requerimento['tipo_alvara']); ?></div>
                <div><span class="negrito">INTERESSADO:</span> <?php echo htmlspecialchars($requerimento['requerente_nome']); ?></div>
                <div><span class="negrito">E-MAIL REMETENTE:</span> <?php echo htmlspecialchars($requerimento['requerente_email']); ?></div>
                <div><span class="negrito">DATA DE ENTRADA:</span> <?php echo formatarData($requerimento['data_envio']); ?></div>

                <br>

                <?php
                // Verificar se o proprietário é diferente do requerente
                $proprietario_diferente = false;
                if (!empty($requerimento['proprietario_id']) && !empty($requerimento['proprietario_nome'])) {
                    // Comparar se o proprietário é diferente do requerente
                    if (
                        $requerimento['proprietario_nome'] !== $requerimento['requerente_nome'] ||
                        $requerimento['proprietario_cpf_cnpj'] !== $requerimento['requerente_cpf_cnpj']
                    ) {
                        $proprietario_diferente = true;
                    }
                }

                if ($proprietario_diferente): ?>
                    <div><span class="negrito">PROPRIETÁRIO:</span> <?php echo htmlspecialchars($requerimento['proprietario_nome']); ?></div>
                    <div><span class="negrito">CPF/CNPJ PROPRIETÁRIO:</span> <?php echo htmlspecialchars($requerimento['proprietario_cpf_cnpj']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="rodape">
            <span>@prefeituradepaudosferros</span> |
            <span>www.paudosferros.rn.gov.br</span>
        </div>

    </div>

    <script>
        // Impressão automática ao carregar a página
        window.onload = function() {
            // Aguardar um pouco para garantir que tudo carregou
            setTimeout(function() {
                // Tentar definir configurações de impressão
                if (window.print) {
                    // Configurar para impressão sem cabeçalhos/rodapés se possível
                    try {
                        const mediaQueryList = window.matchMedia('print');
                        mediaQueryList.addListener(function(mql) {
                            if (mql.matches) {
                                // Configurações específicas para impressão
                                document.body.style.margin = '0';
                                document.body.style.padding = '0';
                            }
                        });
                    } catch (e) {
                        console.log('Configuração de impressão não suportada');
                    }

                    window.print();
                }
            }, 500);
        }

        // Fechar automaticamente após a impressão
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 100);
        }

        // Configurar impressão antes de executar
        window.onbeforeprint = function() {
            // Remover qualquer padding/margin extra para impressão
            document.body.classList.add('printing');
        }

        // Adicionar data de geração no rodapé
        document.addEventListener('DOMContentLoaded', function() {
            const rodape = document.querySelector('.rodape');
            const dataGeracao = document.createElement('div');
            dataGeracao.style.marginTop = '10px';
            dataGeracao.style.fontSize = '8pt';
            dataGeracao.style.color = '#666';
            dataGeracao.textContent = 'Documento gerado em <?php echo date("d/m/Y \\à\\s H:i"); ?>';
            rodape.appendChild(dataGeracao);
        });
    </script>

</body>

</html>