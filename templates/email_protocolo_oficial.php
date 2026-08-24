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
    </style>
</head>

<body>
    <div class="container">
        <div class="content">
            <p>Prezado(a) <strong><?= htmlspecialchars($nome_destinatario) ?></strong>,</p>

            <p>Encaminhamos o número de protocolo referente ao processo requerido: <span class="protocol-number"><?= htmlspecialchars($protocolo_oficial) ?></span></p>
            
            <p>Use este número para acompanhar o andamento no
                <a href="https://gestor.tributosmunicipais.com.br/redesim/prefeitura/paudosferros/views/publico/portaldocontribuinte/index.xhtml" class="link">Portal do Contribuinte</a>.
            </p>

            <p>Se houver cobrança ou emissão de documento, você receberá outra mensagem com um link seguro e as orientações necessárias.</p>

            <div class="signature">
                <p>Atenciosamente,</p>
                <p><strong>Setor de fiscalização ambiental<br>
                        Secretaria Municipal de Meio Ambiente</strong></p>
            </div>
            <p style="font-size:12px;color:#6b7280;margin-top:24px;">Mensagem automática. Em caso de dúvida, fale com a equipe pelo WhatsApp (84) 99668-6413 ou pelo e-mail fiscalizacaosemapdf@gmail.com.</p>
        </div>
    </div>
</body>

</html>
