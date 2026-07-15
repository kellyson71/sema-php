<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Sinaliza aos clientes (Gmail/Apple Mail) que o e-mail lida com tema claro/escuro,
         evitando que o dark mode inverta cores e derrube o contraste dos cards claros. -->
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Documento Final Disponível</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">

<!-- Preheader: texto de pré-visualização mostrado ao lado do assunto na caixa de
     entrada. Fica oculto no corpo. Os &zwnj;&nbsp; no fim evitam que o cliente
     "puxe" trechos do HTML seguinte para a prévia. -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;opacity:0;color:transparent;height:0;width:0;">
    Seu documento do processo #<?php echo htmlspecialchars($protocolo); ?> está pronto para download na SEMA.
    <?php echo str_repeat('&zwnj;&nbsp;', 30); ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f4f8;">
    <tr>
        <td align="center" style="padding:32px 16px 24px;">

            <!-- Container principal -->
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(1,34,84,0.14);">

                <!-- ── Header verde ── -->
                <tr>
                    <td style="background:linear-gradient(135deg,#009640 0%,#007a30 100%);background-color:#009640;padding:26px 32px 22px;">
                        <!-- Logo institucional (versão branca, legível sobre o verde) -->
                        <img src="https://sema.protocolosead.com/assets/img/logo_sema_email.png"
                             alt="SEMA — Secretaria Municipal do Meio Ambiente — Prefeitura de Pau dos Ferros/RN"
                             width="188" style="display:block;border:0;outline:none;text-decoration:none;width:188px;max-width:60%;height:auto;margin-bottom:16px;">
                        <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;line-height:1.25;letter-spacing:-0.02em;">
                            <?php echo htmlspecialchars(tituloAmigavel($tipo_alvara)); ?>
                        </h1>
                        <p style="margin:6px 0 0;color:#ffffff;font-size:14px;font-weight:600;">
                            Seu documento está pronto
                        </p>
                    </td>
                </tr>

                <!-- ── Body branco ── -->
                <tr>
                    <td style="background:#ffffff;padding:32px 32px 24px;">

                        <p style="margin:0 0 8px;font-size:15px;color:#334155;line-height:1.6;">
                            Olá, <strong><?php echo htmlspecialchars($nome_destinatario); ?></strong>.
                        </p>

                        <p style="margin:0 0 24px;font-size:15px;color:#475569;line-height:1.65;">
                            A análise do seu processo foi concluída e o documento já pode ser baixado.
                            Na página de download você também confere quem assinou e a autenticidade do arquivo.
                        </p>

                        <!-- ── Info cards ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
                            <tr>
                                <td style="padding:0 8px 0 0;width:50%;vertical-align:top;">
                                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;">
                                        <p style="margin:0 0 4px;color:#15803d;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Protocolo</p>
                                        <p style="margin:0;color:#166534;font-size:15px;font-weight:800;">#<?php echo htmlspecialchars($protocolo); ?></p>
                                    </div>
                                </td>
                                <td style="padding:0 0 0 8px;width:50%;vertical-align:top;">
                                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;">
                                        <p style="margin:0 0 4px;color:#1d4ed8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Emitido em</p>
                                        <p style="margin:0;color:#1e40af;font-size:15px;font-weight:800;line-height:1.3;"><?php echo date('d/m/Y'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
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

                        <!-- ── O que está sendo entregue ── -->
                        <p style="margin:0 0 10px;font-size:14px;color:#334155;line-height:1.6;">
                            <?php echo count($documentos) > 1
                                ? 'Estes são os ' . count($documentos) . ' documentos do seu processo:'
                                : 'Este é o documento do seu processo:'; ?>
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:22px;">
                            <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td style="padding:11px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fafafa;">
                                    <p style="margin:0;color:#1e293b;font-size:14px;font-weight:700;line-height:1.35;">
                                        📄&nbsp; <?php echo htmlspecialchars(!empty($doc['rotulo']) ? $doc['rotulo'] : $doc['nome']); ?>
                                    </p>
                                    <?php if (!empty($doc['rotulo'])): ?>
                                    <p style="margin:2px 0 0 22px;color:#94a3b8;font-size:11px;line-height:1.4;word-break:break-all;">
                                        <?php echo htmlspecialchars($doc['nome']); ?>
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><td style="height:8px;line-height:8px;">&nbsp;</td></tr>
                            <?php endforeach; ?>
                        </table>

                        <?php if (!empty($url_portal)): ?>
                        <!-- ── Ação única: abrir a página segura de download ──
                             Um botão por arquivo virava um paredão de botões verdes iguais, e o
                             download direto pulava a página — então a SEMA nunca registrava a
                             entrega. Com um CTA só, o acesso fica rastreado (visualizado_em).
                             O bloco VML (<!--[if mso]>) desenha o botão no Outlook desktop, que
                             ignora padding/border-radius em <a>. -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:4px;">
                            <tr>
                                <td align="center">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo htmlspecialchars($url_portal); ?>" style="height:52px;v-text-anchor:middle;width:100%;" arcsize="23%" fillcolor="#009640" stroke="f">
                                    <w:anchorlock/>
                                    <center style="color:#ffffff;font-family:'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:bold;">
                                        Abrir e baixar <?php echo count($documentos) > 1 ? 'meus documentos' : 'meu documento'; ?>
                                    </center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-- -->
                                    <a href="<?php echo htmlspecialchars($url_portal); ?>"
                                       style="display:inline-block;background:linear-gradient(135deg,#009640,#007a30);background-color:#009640;color:#ffffff;padding:15px 32px;border-radius:12px;text-decoration:none;font-weight:800;font-size:16px;box-shadow:0 4px 14px rgba(0,150,64,0.35);width:100%;box-sizing:border-box;text-align:center;">
                                        Abrir e baixar <?php echo count($documentos) > 1 ? 'meus documentos' : 'meu documento'; ?>
                                    </a>
                                    <!--<![endif]-->
                                </td>
                            </tr>
                        </table>
                        <p style="margin:10px 0 0;font-size:12px;color:#94a3b8;line-height:1.6;text-align:center;">
                            Se o botão não funcionar, copie e cole este endereço no navegador:<br>
                            <span style="color:#64748b;word-break:break-all;"><?php echo htmlspecialchars($url_portal); ?></span>
                        </p>
                        <?php endif; ?>

                        <!-- ── Autenticidade ── -->
                        <p style="margin:16px 0 0;font-size:12px;color:#64748b;line-height:1.6;text-align:center;">
                            🛡️ Todos os documentos têm <strong>assinatura digital verificável</strong> —
                            confira a autenticidade na própria página de download.
                        </p>

                        <?php if (!empty($validade_dias)): ?>
                        <p style="margin:10px 0 0;font-size:12px;color:#64748b;line-height:1.6;text-align:center;">
                            🔒 Este link é pessoal e dá acesso aos seus documentos — não repasse este e-mail.
                            Ele fica disponível por <strong><?php echo (int) $validade_dias; ?> dias</strong>;
                            baixe e guarde os arquivos.
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($instrucoes)): ?>
                        <!-- ── Observações de quem entregou (Triagem, Fiscalização ou Secretário) ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;margin-bottom:24px;">
                            <tr>
                                <td style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 10px 10px 0;padding:14px 18px;">
                                    <p style="margin:0 0 6px;color:#92400e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">
                                        📋 Observações da equipe técnica
                                    </p>
                                    <p style="margin:0;color:#78350f;font-size:14px;line-height:1.6;">
                                        <?php echo nl2br(htmlspecialchars($instrucoes)); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>

                        <!-- ── Contato ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:<?php echo empty($instrucoes) ? '20px' : '0'; ?>">
                            <tr>
                                <td style="border-top:1px solid #e2e8f0;padding-top:20px;">
                                    <p style="margin:0 0 10px;color:#64748b;font-size:13px;">Em caso de dúvidas, fale conosco:</p>
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
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

                                    <!-- ── Assinatura institucional ── -->
                                    <p style="margin:18px 0 0;color:#334155;font-size:13px;line-height:1.55;">
                                        Atenciosamente,<br>
                                        <strong style="color:#007a30;">Secretaria Municipal do Meio Ambiente</strong><br>
                                        <span style="color:#94a3b8;">Prefeitura de Pau dos Ferros — RN</span>
                                    </p>
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
