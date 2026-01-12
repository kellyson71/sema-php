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

            <p>Identificamos que o documento referente ao processo <?php if (isset($protocolo) && !empty($protocolo)): ?><strong>protocolo nº <?= htmlspecialchars($protocolo) ?></strong><?php else: ?>abaixo<?php endif; ?> não foi devidamente registrado pelo sistema.</p>

            <p>Solicitamos, por gentileza, que seja feita uma nova abertura do processo pelo sistema <a href="https://sema.protocolosead.com/" class="link">sema.protocolosead.com</a>, anexando novamente o documento para regularização.</p>

            <p>Pedimos desculpas pelo transtorno e agradecemos pela compreensão.</p>

            <div class="signature">
                <p>Atenciosamente,</p>
                <p><strong>Setor de Fiscalização Ambiental<br>
                        Secretaria Municipal de Meio Ambiente</strong></p>
            </div>
        </div>
    </div>
</body>

</html>