<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleto disponível</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #334155;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f8fafc;
            padding: 20px;
        }

        .container {
            background: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .header {
            border-bottom: 3px solid #0f766e;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #0f766e;
        }

        .summary {
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            border-radius: 10px;
            padding: 18px;
            margin: 20px 0;
        }

        .summary strong {
            color: #115e59;
        }

        .cta {
            text-align: center;
            margin: 28px 0;
        }

        .cta a {
            display: inline-block;
            background: linear-gradient(135deg, #0f766e, #0ea5a4);
            color: #ffffff;
            padding: 14px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
        }

        .note {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 14px 16px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }

        .footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Pagamento por boleto disponível</h1>
            <p style="margin:8px 0 0;">Secretaria Municipal de Meio Ambiente - Pau dos Ferros/RN</p>
        </div>

        <p>Olá, <strong><?php echo htmlspecialchars($nome_destinatario); ?></strong>.</p>

        <p>Seu requerimento avançou para a etapa de pagamento. Acesse a página abaixo para visualizar o boleto disponível e anexar o comprovante após o pagamento.</p>

        <div class="summary">
            <div><strong>Protocolo interno:</strong> #<?php echo htmlspecialchars($protocolo); ?></div>
            <div><strong>Tipo de solicitação:</strong> <?php echo htmlspecialchars($tipo_alvara); ?></div>
        </div>

        <?php if (!empty($instrucoes)): ?>
            <div class="note">
                <strong>Instruções da equipe:</strong><br>
                <?php echo nl2br(htmlspecialchars($instrucoes)); ?>
            </div>
        <?php endif; ?>

        <div class="cta">
            <a href="<?php echo htmlspecialchars($url_pagamento); ?>">Abrir página de pagamento</a>
        </div>

        <div class="note">
            Após o pagamento, envie o comprovante em PDF pela própria página. Isso agiliza a conferência e a continuidade do processo.
        </div>

        <div class="footer">
            Este é um email automático. Não responda a esta mensagem.<br>
            © <?php echo date('Y'); ?> - Secretaria Municipal de Meio Ambiente
        </div>
    </div>
</body>
</html>
