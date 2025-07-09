<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processo Indeferido - SEMA</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }

        .email-container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #dc2626;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }

        .title {
            color: #dc2626;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin: 5px 0 0 0;
        }

        .content {
            margin: 25px 0;
        }

        .alert-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }

        .alert-title {
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 8px;
        }

        .protocol-info {
            background: #f3f4f6;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }

        .info-label {
            font-weight: bold;
            color: #374151;
        }

        .info-value {
            color: #6b7280;
        }

        .motivo-section {
            background: #fffbeb;
            border: 1px solid #fed7aa;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }

        .motivo-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .motivo-text {
            color: #451a03;
            line-height: 1.7;
            white-space: pre-line;
        }

        .orientacoes-section {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }

        .orientacoes-title {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .orientacoes-text {
            color: #1e3a8a;
            line-height: 1.7;
            white-space: pre-line;
        }

        .next-steps {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }

        .next-steps-title {
            font-weight: bold;
            color: #0369a1;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }

        .next-steps li {
            margin: 8px 0;
            color: #0c4a6e;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        .signature {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .contact-info {
            background: #f9fafb;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #374151;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .email-container {
                padding: 20px;
            }

            .info-row {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <h1 class="title">Processo Indeferido</h1>
            <p class="subtitle">Secretaria do Meio Ambiente</p>
        </div>

        <div class="content">
            <p>Olá, <strong><?php echo htmlspecialchars($nome_destinatario); ?></strong>,</p>

            <p>Informamos que seu requerimento foi analisado pela equipe técnica da Secretaria do Meio Ambiente.</p>

            <div class="alert-box">
                <div class="alert-title">⚠️ Processo Indeferido</div>
                <p>Infelizmente, seu requerimento <strong>não foi aprovado</strong> pelos motivos descritos abaixo.</p>
            </div>

            <div class="protocol-info">
                <div class="info-row">
                    <span class="info-label">Protocolo:</span>
                    <span class="info-value">#<?php echo htmlspecialchars($protocolo); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipo de Alvará:</span>
                    <span class="info-value"><?php echo htmlspecialchars($tipo_alvara); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Data da Análise:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>

            <div class="motivo-section">
                <div class="motivo-title">📋 Motivo do Indeferimento</div>
                <div class="motivo-text"><?php echo htmlspecialchars($motivo_indeferimento); ?></div>
            </div>

            <?php if (!empty($orientacoes_adicionais)): ?>
                <div class="orientacoes-section">
                    <div class="orientacoes-title">💡 Orientações Adicionais</div>
                    <div class="orientacoes-text"><?php echo htmlspecialchars($orientacoes_adicionais); ?></div>
                </div>
            <?php endif; ?>

            <div class="next-steps">
                <div class="next-steps-title">🔄 Próximos Passos</div>
                <p>Para dar continuidade ao seu processo, você poderá:</p>
                <ul>
                    <li><strong>Enviar um novo requerimento</strong> através do nosso sistema online</li>
                    <li><strong>Corrigir os pontos</strong> citados no motivo do indeferimento</li>
                    <li><strong>Apresentar nova documentação</strong> se necessário</li>
                    <li><strong>Entrar em contato</strong> conosco em caso de dúvidas</li>
                </ul>
            </div>

            <p>Caso tenha dúvidas sobre os motivos do indeferimento ou necessite de esclarecimentos adicionais, entre em contato conosco pelos canais oficiais.</p>

            <div class="signature">
                <p><strong>Atenciosamente,</strong></p>
                <p><strong>Secretaria do Meio Ambiente</strong><br>
                    Prefeitura Municipal</p>
            </div>

            <div class="contact-info">
                <strong>📞 Contato:</strong><br>
                Email: sema@prefeitura.gov.br<br>
                Telefone: (XX) XXXX-XXXX<br>
                <br>
                <strong>🌐 Portal Online:</strong><br>
                Acesse nosso sistema para novos requerimentos
            </div>
        </div>

        <div class="footer">
            <p>Esta é uma mensagem automática. Por favor, não responda este email.</p>
            <p><small>Secretaria do Meio Ambiente - Prefeitura Municipal</small></p>
        </div>
    </div>
</body>

</html>