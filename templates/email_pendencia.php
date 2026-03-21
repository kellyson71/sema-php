<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentação Pendente - SEMA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            border-radius: 5px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #009851;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .header h1 {
            color: #009851;
            font-size: 22px;
            margin: 0;
        }

        .status-box {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        .status-box .icon {
            font-size: 36px;
            margin-bottom: 8px;
        }

        .status-box .label {
            font-size: 18px;
            font-weight: bold;
        }

        .protocol-number {
            font-weight: bold;
            color: #009851;
        }

        .pendencias-box {
            background-color: #fff8e1;
            border-left: 4px solid #e67e22;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }

        .pendencias-box ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .pendencias-box ul li {
            margin-bottom: 8px;
        }

        .info-box {
            background-color: #e8f5e9;
            border-left: 4px solid #009851;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }

        .info-box ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .info-box ul li {
            margin-bottom: 6px;
        }

        .signature {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        p {
            margin: 15px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Secretaria Municipal de Meio Ambiente</h1>
            <p style="margin: 5px 0; color: #666; font-size: 14px;">Prefeitura de Pau dos Ferros - RN</p>
        </div>

        <p>Prezado(a) <strong><?= htmlspecialchars($nome_destinatario) ?></strong>,</p>

        <p>Seu requerimento de protocolo <span class="protocol-number">#<?= htmlspecialchars($protocolo) ?></span> está em análise pela nossa equipe técnica. Durante a verificação, identificamos <strong>pendências na documentação</strong> que precisam ser resolvidas para que o processo possa avançar.</p>

        <div class="status-box">
            <div class="icon">📋</div>
            <div class="label">Documentação Pendente</div>
            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9;">
                Protocolo: <strong>#<?= htmlspecialchars($protocolo) ?></strong>
                <?php if (!empty($tipo_alvara)): ?>
                    &nbsp;|&nbsp; <?= htmlspecialchars($tipo_alvara) ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="pendencias-box">
            <strong>⚠️ Pendências identificadas:</strong>
            <?php if (!empty($pendencias)): ?>
                <?php if (is_array($pendencias)): ?>
                    <ul>
                        <?php foreach ($pendencias as $pendencia): ?>
                            <li><?= nl2br(htmlspecialchars($pendencia)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin: 10px 0 0 0;"><?= nl2br(htmlspecialchars($pendencias)) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p style="margin: 10px 0 0 0;">Documentação incompleta ou incorreta. Entre em contato para mais informações.</p>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <strong>Como resolver:</strong>
            <ul>
                <li>Providencie os documentos listados acima.</li>
                <li>Entre em contato com nossa equipe pelo WhatsApp ou email informados abaixo.</li>
                <li>Após regularizar a situação, seu processo continuará a análise normalmente.</li>
            </ul>
        </div>

        <p>Em caso de dúvidas, entre em contato conosco:</p>
        <p>
            <strong>WhatsApp:</strong> (84) 99668-6413<br>
            <strong>Email:</strong> fiscalizacaosemapdf@gmail.com<br>
            <strong>Horário de Atendimento:</strong> Segunda a Sexta, 7h às 13h
        </p>

        <div class="signature">
            <p>Atenciosamente,<br>
                <strong>Setor de Fiscalização Ambiental<br>
                    Secretaria Municipal de Meio Ambiente</strong>
            </p>
        </div>

        <p style="text-align:center; font-size:11px; color:#aaa; margin-top:20px;">
            Este é um email automático, não responda a esta mensagem.<br>
            © <?= date('Y') ?> — SEMA - Secretaria Municipal de Meio Ambiente - Prefeitura de Pau dos Ferros/RN
        </p>
    </div>
</body>

</html>
