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
            <p>O protocolo pode ser acompanhado pelo sistema de tributos municipais no link
                <a href="https://gestor.tributosmunicipais.com.br/redesim/views/publico/prefWeb/modulos/processo/consulta/processos.xhtml" class="link">gestor.tributosmunicipais.com.br</a>
                (digite o protocolo enviado para acompanhar o processo).
            </p>

            <p>O alvará poderá ser retirado na Secretaria de Meio Ambiente / Setor de Obras quando a taxa for paga na Secretaria de Tributação.</p>

            <div class="signature">
                <p>Atenciosamente,</p>
                <p><strong>Setor de fiscalização ambiental<br>
                        Secretaria Municipal de Meio Ambiente</strong></p>
            </div>
        </div>
    </div>
</body>

</html>