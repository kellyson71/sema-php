<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento Final Disponível</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f4f8;">
    <tr>
        <td align="center" style="padding:32px 16px 24px;">

            <!-- Container principal -->
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(1,34,84,0.14);">

                <!-- ── Header verde ── -->
                <tr>
                    <td style="background:linear-gradient(135deg,#009640 0%,#007a30 100%);padding:0;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:28px 32px 22px;">
                                    <p style="margin:0 0 4px;color:rgba(255,255,255,0.75);font-size:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:600;">
                                        Secretaria Municipal de Meio Ambiente
                                    </p>
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;line-height:1.25;letter-spacing:-0.02em;">
                                        Documento Final Disponível
                                    </h1>
                                    <p style="margin:6px 0 0;color:rgba(255,255,255,0.72);font-size:13px;">
                                        Prefeitura de Pau dos Ferros &mdash; RN
                                    </p>
                                </td>
                                <td style="padding:28px 32px 22px;text-align:right;vertical-align:middle;">
                                    <div style="display:inline-block;background:rgba(255,255,255,0.15);border-radius:50%;width:56px;height:56px;line-height:56px;text-align:center;font-size:26px;">
                                        📄
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- ── Body branco ── -->
                <tr>
                    <td style="background:#ffffff;padding:32px 32px 24px;">

                        <p style="margin:0 0 8px;font-size:15px;color:#334155;line-height:1.6;">
                            Olá, <strong><?php echo htmlspecialchars($nome_destinatario); ?></strong>.
                        </p>

                        <p style="margin:0 0 24px;font-size:15px;color:#475569;line-height:1.65;">
                            Seu documento final está disponível para visualização e download.
                            Utilize o(s) link(s) abaixo para abrir o arquivo diretamente.
                        </p>

                        <!-- ── Info cards ── -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
                            <tr>
                                <td style="padding:0 8px 0 0;width:50%;vertical-align:top;">
                                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;">
                                        <p style="margin:0 0 4px;color:#15803d;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Protocolo</p>
                                        <p style="margin:0;color:#166534;font-size:15px;font-weight:800;">#<?php echo htmlspecialchars($protocolo); ?></p>
                                    </div>
                                </td>
                                <td style="padding:0 0 0 8px;width:50%;vertical-align:top;">
                                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;">
                                        <p style="margin:0 0 4px;color:#1d4ed8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Unidade</p>
                                        <p style="margin:0;color:#1e40af;font-size:13px;font-weight:700;line-height:1.3;">SEMA &mdash; Meio Ambiente</p>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                            <tr>
                                <td style="padding:0 8px 0 0;width:50%;vertical-align:top;">
                                    <div style="background:#fafafa;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;">
                                        <p style="margin:0 0 4px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Requerente</p>
                                        <p style="margin:0;color:#1e293b;font-size:14px;font-weight:700;line-height:1.3;"><?php echo htmlspecialchars($nome_destinatario); ?></p>
                                    </div>
                                </td>
                                <td style="padding:0 0 0 8px;width:50%;vertical-align:top;">
                                    <div style="background:#fafafa;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;">
                                        <p style="margin:0 0 4px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Tipo de Processo</p>
                                        <p style="margin:0;color:#1e293b;font-size:13px;font-weight:600;line-height:1.3;"><?php echo htmlspecialchars($tipo_alvara); ?></p>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px;font-size:14px;color:#334155;line-height:1.6;">
                            Clique no link abaixo para visualizar e baixar o seu documento:
                        </p>

                        <!-- ── Links diretos para os PDFs ── -->
                        <?php foreach ($documentos as $doc): ?>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;">
                            <tr>
                                <td align="center">
                                    <a href="<?php echo htmlspecialchars($doc['url']); ?>"
                                       style="display:inline-block;background:linear-gradient(135deg,#009640,#007a30);color:#ffffff;padding:14px 32px;border-radius:12px;text-decoration:none;font-weight:800;font-size:15px;letter-spacing:0.01em;box-shadow:0 4px 14px rgba(0,150,64,0.35);width:100%;box-sizing:border-box;text-align:center;">
                                        📎&nbsp; <?php echo htmlspecialchars($doc['nome']); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <?php endforeach; ?>

                        <?php if (!empty($url_portal)): ?>
                        <!-- ── Página de acompanhamento (mesmo link seguro) ── -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:6px;">
                            <tr>
                                <td align="center" style="padding:6px 0 2px;">
                                    <a href="<?php echo htmlspecialchars($url_portal); ?>"
                                       style="color:#007a30;font-size:13px;font-weight:700;text-decoration:underline;">
                                        Ver todos os documentos deste processo
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>

                        <?php if (!empty($validade_dias)): ?>
                        <p style="margin:14px 0 0;font-size:12px;color:#64748b;line-height:1.6;text-align:center;">
                            🔒 Estes links são pessoais e ficam disponíveis por
                            <strong><?php echo (int) $validade_dias; ?> dias</strong>.
                            Baixe e guarde os arquivos. Não repasse este e-mail a terceiros.
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($instrucoes)): ?>
                        <!-- ── Observações do Secretário ── -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;margin-bottom:24px;">
                            <tr>
                                <td style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 10px 10px 0;padding:14px 18px;">
                                    <p style="margin:0 0 6px;color:#92400e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">
                                        📋 Observações do Secretário
                                    </p>
                                    <p style="margin:0;color:#78350f;font-size:14px;line-height:1.6;">
                                        <?php echo nl2br(htmlspecialchars($instrucoes)); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>

                        <!-- ── Contato ── -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:<?php echo empty($instrucoes) ? '20px' : '0'; ?>">
                            <tr>
                                <td style="border-top:1px solid #e2e8f0;padding-top:20px;">
                                    <p style="margin:0 0 10px;color:#64748b;font-size:13px;">Em caso de dúvidas, fale conosco:</p>
                                    <table cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding:0 16px 0 0;color:#475569;font-size:13px;">
                                                📱 <strong>(84) 99668-6413</strong>
                                            </td>
                                            <td style="color:#475569;font-size:13px;">
                                                ✉️ fiscalizacaosemapdf@gmail.com
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="padding-top:4px;color:#94a3b8;font-size:12px;">
                                                Atendimento: Segunda a Sexta, 7h às 13h
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- ── Footer ── -->
                <tr>
                    <td style="background:#013d86;padding:18px 32px;text-align:center;">
                        <p style="margin:0;color:rgba(255,255,255,0.55);font-size:12px;line-height:1.6;">
                            Este é um e-mail automático &mdash; não responda a esta mensagem.<br>
                            &copy; <?php echo date('Y'); ?> &mdash; Secretaria Municipal de Meio Ambiente &mdash; Prefeitura de Pau dos Ferros/RN
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
