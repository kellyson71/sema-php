<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processo Devolvido para Correção - SEMA</title>
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
            background: linear-gradient(135deg, #8e44ad, #6c3483);
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

        .motivo-box {
            background-color: #f3e5f5;
            border-left: 4px solid #8e44ad;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
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

        .highlight {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
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

        <p>Informamos que o seu requerimento de protocolo <span class="protocol-number">#<?= htmlspecialchars($protocolo) ?></span> foi <strong>devolvido para correção</strong>. O processo retornará à fase de análise técnica após a regularização.</p>

        <div class="status-box">
            <div class="icon">🔄</div>
            <div class="label">Devolvido para Correção</div>
            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9;">
                Protocolo: <strong>#<?= htmlspecialchars($protocolo) ?></strong>
                <?php if (!empty($tipo_alvara)): ?>
                    &nbsp;|&nbsp; <?= htmlspecialchars($tipo_alvara) ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if (!empty($motivo_reenvio)): ?>
            <div class="motivo-box">
                <strong>Motivo da devolução:</strong>
                <p style="margin: 10px 0 0 0;"><?= nl2br(htmlspecialchars($motivo_reenvio)) ?></p>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>O que fazer agora:</strong>
            <ul>
                <li>Verifique o motivo da devolução descrito acima.</li>
                <li>Corrija as informações ou documentos indicados.</li>
                <li>Entre em contato com nossa equipe caso necessite de orientações.</li>
                <li>Após a correção, o processo retomará a análise técnica normalmente.</li>
            </ul>
        </div>

        <div class="highlight">
            <strong>ℹ️ Importante:</strong> A devolução não significa que seu processo foi indeferido. É uma oportunidade para regularizar a documentação sem perder o andamento do requerimento.
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
