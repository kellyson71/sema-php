<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processo Indeferido - SEMA</title>
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
            border-bottom: 3px solid #dc2626;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }

        .titulo {
            color: #dc2626;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .status-indeferido {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        .status-titulo {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }

        .protocolo-numero {
            font-size: 18px;
            font-weight: bold;
            color: #dc2626;
            margin: 10px 0;
        }

        .motivo-box {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }

        .motivo-title {
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 10px;
        }

        .orientacoes-box {
            background-color: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }

        .orientacoes-title {
            font-weight: bold;
            color: #f59e0b;
            margin-bottom: 10px;
        }

        .info-box {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }

        .info-title {
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 10px;
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

        .signature {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 class="titulo">Secretaria Municipal de Meio Ambiente</h1>
            <p style="margin: 5px 0; color: #666;">Prefeitura de Pau dos Ferros - RN</p>
        </div>

        <p><strong>Prezado(a) <?php echo htmlspecialchars($nome_destinatario); ?>,</strong></p>

        <p>Informamos que seu requerimento foi analisado pela equipe técnica da Secretaria do Meio Ambiente.</p>

        <div class="status-indeferido">
            <div class="status-titulo">PROCESSO INDEFERIDO</div>
        </div>

        <p>Infelizmente, o processo de protocolo <span class="protocolo-numero">#<?php echo htmlspecialchars($protocolo_oficial); ?></span> foi indeferido pelos seguintes motivos:</p>

        <div class="motivo-box">
            <div class="motivo-title">📋 Motivos do Indeferimento:</div>
            <p><?php echo nl2br(htmlspecialchars($motivo_indeferimento)); ?></p>
        </div>

        <?php if (!empty($orientacoes_adicionais)): ?>
            <div class="orientacoes-box">
                <div class="orientacoes-title">💡 Orientações para Correção:</div>
                <p><?php echo nl2br(htmlspecialchars($orientacoes_adicionais)); ?></p>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <div class="info-title">🔄 Para dar continuidade ao processo:</div>
            <ul>
                <li>Envie um novo requerimento através do nosso sistema online</li>
                <li>Corrija todos os pontos indicados acima</li>
                <li>Apresente toda a documentação novamente, conforme as exigências atuais</li>
            </ul>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="https://www.paudosferros.rn.gov.br/" class="btn">Acessar Portal da Prefeitura</a>
        </div>

        <div class="signature">
            <p>Atenciosamente,</p>
            <p><strong>Secretaria Municipal de Meio Ambiente</strong></p>
        </div>

        <div class="footer">
            <p>Este é um email automático, não responda a esta mensagem.</p>
            <p>© <?php echo date('Y'); ?> - Secretaria Municipal de Meio Ambiente - Prefeitura de Pau dos Ferros/RN</p>
            <p>Desenvolvido pela equipe de TI da Prefeitura</p>
        </div>
    </div>
</body>

</html>