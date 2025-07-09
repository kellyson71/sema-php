<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protocolo Oficial da Prefeitura</title>
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

        .content {
            margin: 20px 0;
            text-align: left;
        }

        .protocol-number {
            font-weight: bold;
            color: #009851;
        }

        .link {
            color: #009851;
            text-decoration: none;
        }

        .signature {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .highlight {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }

        ul {
            padding-left: 20px;
            margin: 15px 0;
        }

        ul li {
            margin-bottom: 8px;
            line-height: 1.4;
        }

        p {
            margin: 15px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="content">
            <p>Prezado(a) <strong><?= htmlspecialchars($nome_destinatario) ?></strong>,</p>

            <p>Informamos que seu requerimento foi analisado pela equipe técnica da Secretaria do Meio Ambiente.</p>

            <p><strong>PROCESSO INDEFERIDO</strong></p>

            <p>Infelizmente, o processo de protocolo <span class="protocol-number">#<?= htmlspecialchars($protocolo) ?></span> foi indeferido pelos seguintes motivos:</p>

            <p><strong><?= nl2br(htmlspecialchars($motivo_indeferimento)) ?></strong></p>

            <?php if (!empty($orientacoes_adicionais)): ?>
                <p><strong>Orientações para Correção:</strong></p>
                <p><?= nl2br(htmlspecialchars($orientacoes_adicionais)) ?></p>
            <?php endif; ?>

            <p><strong>Para dar continuidade ao processo:</strong></p>
            <ul>
                <li>Envie um novo requerimento através do nosso sistema online</li>
                <li>Corrija todos os pontos indicados acima</li>
                <li>Apresente toda a documentação novamente, conforme as exigências atuais</li>
            </ul>

            <div class="signature">
                <p>Atenciosamente,<br>
                    <strong>Secretaria Municipal de Meio Ambiente</strong>
                </p>
            </div>
        </div>
    </div>
</body>

</html>