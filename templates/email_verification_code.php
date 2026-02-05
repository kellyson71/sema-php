<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificação de Segurança</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #374151; /* gray-700 */
            max-width: 600px;
            margin: 0 auto;
            background-color: #f3f4f6; /* gray-100 */
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 20px;
        }

        .logo-text {
            color: #059669; /* primary-600 */
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .content {
            margin: 20px 0;
            text-align: center;
        }

        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #111827; /* gray-900 */
        }

        .description {
            color: #4b5563; /* gray-600 */
            margin-bottom: 30px;
            font-size: 16px;
        }

        .code-box {
            background-color: #ecfdf5; /* green-50 */
            border: 2px dashed #059669; /* green-600 */
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: center;
        }

        .verification-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 36px;
            font-weight: 800;
            color: #047857; /* green-700 */
            letter-spacing: 8px;
            margin: 0;
        }

        .warning-box {
            background-color: #fff7ed; /* orange-50 */
            border-left: 4px solid #f97316; /* orange-500 */
            padding: 15px;
            margin-top: 30px;
            text-align: left;
            font-size: 14px;
            color: #c2410c; /* orange-700 */
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af; /* gray-400 */
            border-top: 1px solid #f3f4f6;
            padding-top: 20px;
        }

        .expiry-text {
            font-size: 14px;
            color: #6b7280; /* gray-500 */
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo-text">Verificação de Segurança</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Olá, <strong><?= htmlspecialchars($nome_destinatario) ?></strong></p>

            <p class="description">
                Para autorizar sua assinatura digital, confirme sua identidade utilizando o código abaixo.
            </p>

            <div class="code-box">
                <p class="verification-code"><?= htmlspecialchars($codigo) ?></p>
            </div>

            <p class="expiry-text">
                Este código expira em 15 minutos. Após validar, sua sessão fica ativa por 8 horas.
            </p>

            <div class="warning-box">
                <strong>⚠️ Importante:</strong><br>
                Nunca compartilhe este código com ninguém. A equipe de suporte nunca solicitará sua senha ou códigos de verificação.
            </div>
        </div>

        <div class="footer">
            <p>Este é um email automático de segurança.<br>
            Se você não solicitou este código, por favor entre em contato com a administração imediatamente.</p>
        </div>
    </div>
</body>
</html>
