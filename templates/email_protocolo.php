<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação de Requerimento</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #009851;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }

        .titulo {
            color: #009851;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .registro-entrada {
            background: linear-gradient(135deg, #009851, #007a3d);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        .registro-numero {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }

        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #009851;
            padding: 15px;
            margin: 20px 0;
        }

        .info-title {
            font-weight: bold;
            color: #009851;
            margin-bottom: 10px;
        }

        .aviso-importante {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }

        .aviso-importante strong {
            color: #856404;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }

        .contato {
            background-color: #e8f5e8;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .contato h4 {
            color: #009851;
            margin-top: 0;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #009851, #007a3d);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 10px 0;
        }

        .dados-requerimento {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }

        .dados-linha {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }

        .dados-label {
            font-weight: bold;
            color: #555;
        }

        .dados-valor {
            color: #333;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 class="titulo">Secretaria Municipal de Meio Ambiente</h1>
            <p style="margin: 5px 0; color: #666;">Prefeitura de Pau dos Ferros - RN</p>
        </div>

        <p><strong>Olá, <?php echo htmlspecialchars($nome); ?>!</strong></p>

        <p>Seu requerimento foi recebido com sucesso pela Secretaria Municipal de Meio Ambiente.</p>

        <div class="registro-entrada">
            <p style="margin: 0; font-size: 16px;">Registro de Entrada</p>
            <div class="registro-numero">#<?php echo htmlspecialchars($protocolo); ?></div>
            <p style="margin: 5px 0; font-size: 14px; opacity: 0.9;">Guarde este número para referência interna</p>
        </div>

        <div class="dados-requerimento">
            <h4 style="color: #009851; margin-top: 0;">Dados do Requerimento</h4>
            <div class="dados-linha">
                <span class="dados-label">Tipo de Alvará:</span>
                <span class="dados-valor"><?php echo htmlspecialchars($tipo_alvara); ?></span>
            </div>
            <?php if (isset($dados['data_envio'])): ?>
                <div class="dados-linha">
                    <span class="dados-label">Data de Envio:</span>
                    <span class="dados-valor"><?php echo date('d/m/Y H:i', strtotime($dados['data_envio'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($dados['endereco_objetivo'])): ?>
                <div class="dados-linha">
                    <span class="dados-label">Endereço do Objetivo:</span>
                    <span class="dados-valor"><?php echo htmlspecialchars($dados['endereco_objetivo']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="aviso-importante">
            <p><strong>⚠️ IMPORTANTE:</strong></p>
            <p>Este número é apenas um <strong>registro de entrada interno</strong> do sistema (também chamado de protocolo interno em alguns setores). <strong>O número de protocolo oficial para acompanhamento no portal da prefeitura será enviado posteriormente via email quando seu requerimento for processado pela nossa equipe.</strong></p>
        </div>

        <div class="info-box">
            <div class="info-title">📋 Próximos Passos:</div>
            <ul>
                <li>Seu requerimento será analisado pela nossa equipe técnica</li>
                <li>Você receberá um email com o <strong>protocolo oficial da prefeitura</strong> após o processamento</li>
                <li>Com o protocolo oficial, você poderá acompanhar o status no portal da prefeitura: <a href="https://www.paudosferros.rn.gov.br/" style="color: #009851;">www.paudosferros.rn.gov.br</a></li>
                <li>Em caso de documentação complementar, entraremos em contato</li>
            </ul>
        </div>

        <div class="contato">
            <h4>📞 Contato</h4>
            <p><strong>WhatsApp:</strong> (84) 99668-6413</p>
            <p><strong>Email:</strong> fiscalizacaosemapdf@gmail.com</p>
            <p><strong>Horário de Atendimento:</strong> Segunda a Sexta, 7h às 13h</p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="https://www.paudosferros.rn.gov.br/" class="btn">Acessar Portal da Prefeitura</a>
        </div>

        <div class="footer">
            <p>Este é um email automático, não responda a esta mensagem.</p>
            <p>© <?php echo date('Y'); ?> - Secretaria Municipal de Meio Ambiente - Prefeitura de Pau dos Ferros/RN</p>
            <p>Desenvolvido pela equipe de TI da Prefeitura</p>
        </div>
    </div>
</body>

</html>