<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Documento disponível — SEMA</title>
</head>
<?php
// Nome do tipo em Title Case ("ALVARÁ DE HABITE-SE" -> "Alvará de Habite-se").
// O caller passa o nome legível vindo de $tipos_alvara; tituloAmigavel só ajusta
// a caixa. Se por acaso vier um slug cru, ainda assim fica apresentável.
$tipoLegivel = tituloAmigavel(str_replace('_', ' ', $tipo_alvara));
?>
<body style="margin:0;padding:0;background-color:#eef1f5;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;">

<!-- Preheader: prévia mostrada ao lado do assunto na caixa de entrada. -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;opacity:0;color:transparent;height:0;width:0;">
    Comunicado oficial da SEMA — o documento do processo #<?php echo htmlspecialchars($protocolo); ?> já está disponível para download.
    <?php echo str_repeat('&zwnj;&nbsp;', 30); ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef1f5;">
    <tr>
        <td align="center" style="padding:32px 16px 40px;">

            <!-- Container -->
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e2e6ec;border-radius:10px;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,0.06),0 10px 28px rgba(16,24,40,0.06);">

                <!-- ── Cabeçalho institucional ── -->
                <tr>
                    <td style="background-color:#0a6b34;padding:24px 36px;">
                        <img src="https://sema.protocolosead.com/assets/img/logo_sema_email.png"
                             alt="SEMA — Secretaria Municipal do Meio Ambiente — Prefeitura de Pau dos Ferros/RN"
                             width="176" style="display:block;border:0;outline:none;text-decoration:none;width:176px;max-width:58%;height:auto;">
                    </td>
                </tr>
                <!-- Filete tricolor: eco discreto da marca (amarelo/verde/azul do logo) -->
                <tr>
                    <td style="font-size:0;line-height:0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                            <td width="34%" style="height:4px;background-color:#f2b705;font-size:0;line-height:0;">&nbsp;</td>
                            <td width="33%" style="height:4px;background-color:#0a6b34;font-size:0;line-height:0;">&nbsp;</td>
                            <td width="33%" style="height:4px;background-color:#0b4a8f;font-size:0;line-height:0;">&nbsp;</td>
                        </tr></table>
                    </td>
                </tr>

                <!-- ── Corpo ── -->
                <tr>
                    <td style="padding:34px 36px 8px;">

                        <p style="margin:0 0 4px;color:#8a94a2;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;">
                            Comunicado oficial
                        </p>
                        <h1 style="margin:0 0 22px;color:#101828;font-size:21px;font-weight:700;line-height:1.3;">
                            <?php echo htmlspecialchars($tipoLegivel); ?> &mdash; documento disponível
                        </h1>

                        <p style="margin:0 0 14px;font-size:15px;color:#3f4a56;line-height:1.65;">
                            Prezado(a) <strong style="color:#101828;"><?php echo htmlspecialchars($nome_destinatario); ?></strong>,
                        </p>
                        <p style="margin:0 0 26px;font-size:15px;color:#475569;line-height:1.7;">
                            informamos que a análise do seu processo foi concluída e
                            <?php echo count($documentos) > 1
                                ? 'os documentos correspondentes estão disponíveis'
                                : 'o documento correspondente está disponível'; ?>
                            para download. O acesso é feito por meio de página segura, onde também é possível
                            conferir os signatários e a autenticidade de cada arquivo.
                        </p>

                        <!-- ── Ficha do processo (tabela de dados, sem balões coloridos) ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e9ee;border-radius:8px;border-collapse:separate;overflow:hidden;margin-bottom:28px;">
                            <tr>
                                <td width="42%" style="background:#f7f9fb;padding:11px 16px;font-size:12px;color:#64748b;font-weight:600;border-bottom:1px solid #eef1f4;">Protocolo</td>
                                <td style="padding:11px 16px;font-size:13px;color:#101828;font-weight:700;border-bottom:1px solid #eef1f4;">#<?php echo htmlspecialchars($protocolo); ?></td>
                            </tr>
                            <tr>
                                <td style="background:#f7f9fb;padding:11px 16px;font-size:12px;color:#64748b;font-weight:600;border-bottom:1px solid #eef1f4;">Emitido em</td>
                                <td style="padding:11px 16px;font-size:13px;color:#1f2933;font-weight:600;border-bottom:1px solid #eef1f4;"><?php echo date('d/m/Y'); ?></td>
                            </tr>
                            <tr>
                                <td style="background:#f7f9fb;padding:11px 16px;font-size:12px;color:#64748b;font-weight:600;border-bottom:1px solid #eef1f4;">Tipo de processo</td>
                                <td style="padding:11px 16px;font-size:13px;color:#1f2933;font-weight:600;border-bottom:1px solid #eef1f4;"><?php echo htmlspecialchars($tipoLegivel); ?></td>
                            </tr>
                            <tr>
                                <td style="background:#f7f9fb;padding:11px 16px;font-size:12px;color:#64748b;font-weight:600;">Requerente</td>
                                <td style="padding:11px 16px;font-size:13px;color:#1f2933;font-weight:600;"><?php echo htmlspecialchars($nome_destinatario); ?></td>
                            </tr>
                        </table>

                        <!-- ── Documentos ── -->
                        <p style="margin:0 0 12px;font-size:12px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;">
                            <?php echo count($documentos) > 1 ? count($documentos) . ' documentos' : 'Documento'; ?>
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
                            <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td style="padding:0 0 8px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e9ee;border-radius:8px;">
                                        <tr>
                                            <td width="52" style="padding:13px 0 13px 14px;vertical-align:middle;">
                                                <span style="display:inline-block;background:#fdecec;color:#c0392b;font-size:10px;font-weight:800;letter-spacing:0.04em;padding:4px 7px;border-radius:4px;">PDF</span>
                                            </td>
                                            <td style="padding:11px 14px 11px 6px;vertical-align:middle;">
                                                <p style="margin:0;color:#1f2933;font-size:14px;font-weight:600;line-height:1.35;">
                                                    <?php echo htmlspecialchars(!empty($doc['rotulo']) ? $doc['rotulo'] : $doc['nome']); ?>
                                                </p>
                                                <?php if (!empty($doc['rotulo'])): ?>
                                                <p style="margin:1px 0 0;color:#9aa4b0;font-size:11px;line-height:1.4;word-break:break-all;">
                                                    <?php echo htmlspecialchars($doc['nome']); ?>
                                                </p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>

                        <?php if (!empty($url_portal)): ?>
                        <!-- ── Botão de acesso (VML garante renderização no Outlook) ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
                            <tr>
                                <td align="center">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo htmlspecialchars($url_portal); ?>" style="height:48px;v-text-anchor:middle;width:320px;" arcsize="14%" fillcolor="#0a6b34" stroke="f">
                                    <w:anchorlock/>
                                    <center style="color:#ffffff;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:bold;">
                                        Acessar e baixar <?php echo count($documentos) > 1 ? 'documentos' : 'documento'; ?>
                                    </center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-- -->
                                    <a href="<?php echo htmlspecialchars($url_portal); ?>"
                                       style="display:inline-block;background-color:#0a6b34;color:#ffffff;padding:14px 34px;border-radius:7px;text-decoration:none;font-weight:700;font-size:15px;letter-spacing:0.01em;box-shadow:0 2px 5px rgba(10,107,52,0.25);">
                                        Acessar e baixar <?php echo count($documentos) > 1 ? 'documentos' : 'documento'; ?>
                                    </a>
                                    <!--<![endif]-->
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 26px;font-size:12px;color:#9aa4b0;line-height:1.6;text-align:center;">
                            Caso o botão não funcione, copie e cole no navegador:<br>
                            <span style="color:#6b7580;word-break:break-all;"><?php echo htmlspecialchars($url_portal); ?></span>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($instrucoes)): ?>
                        <!-- ── Observações da equipe técnica ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:26px;">
                            <tr>
                                <td style="background:#f7f9fb;border:1px solid #e5e9ee;border-left:3px solid #0a6b34;border-radius:6px;padding:14px 18px;">
                                    <p style="margin:0 0 5px;color:#334155;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;">
                                        Observações da equipe técnica
                                    </p>
                                    <p style="margin:0;color:#475569;font-size:14px;line-height:1.65;">
                                        <?php echo nl2br(htmlspecialchars($instrucoes)); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>

                        <!-- ── Avisos (autenticidade / validade) em nota discreta ── -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:4px;">
                            <tr>
                                <td style="border-top:1px solid #eef1f4;padding-top:18px;">
                                    <p style="margin:0 0 6px;font-size:12px;color:#64748b;line-height:1.65;">
                                        <strong style="color:#475569;">Autenticidade.</strong>
                                        Todos os documentos possuem assinatura digital verificável diretamente na página de download.
                                    </p>
                                    <?php if (!empty($validade_dias)): ?>
                                    <p style="margin:0;font-size:12px;color:#64748b;line-height:1.65;">
                                        <strong style="color:#475569;">Acesso.</strong>
                                        Este link é pessoal — não repasse este e-mail. Permanece disponível por
                                        <strong><?php echo (int) $validade_dias; ?> dias</strong>; recomendamos baixar e guardar os arquivos.
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- ── Assinatura / contato ── -->
                <tr>
                    <td style="padding:22px 36px 26px;border-top:1px solid #eef1f4;">
                        <p style="margin:0 0 3px;color:#1f2933;font-size:14px;font-weight:700;">
                            Secretaria Municipal do Meio Ambiente
                        </p>
                        <p style="margin:0 0 12px;color:#8a94a2;font-size:12px;">
                            Prefeitura Municipal de Pau dos Ferros — RN
                        </p>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:0 22px 0 0;color:#475569;font-size:13px;">
                                    <span style="color:#8a94a2;">Telefone</span>&nbsp; (84) 99668-6413
                                </td>
                                <td style="color:#475569;font-size:13px;">
                                    <span style="color:#8a94a2;">E-mail</span>&nbsp; fiscalizacaosemapdf@gmail.com
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding-top:6px;color:#9aa4b0;font-size:12px;">
                                    Atendimento: segunda a sexta, das 7h às 13h
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- ── Rodapé ── -->
                <tr>
                    <td style="background:#0f2740;padding:16px 36px;">
                        <p style="margin:0;color:rgba(255,255,255,0.55);font-size:11px;line-height:1.7;">
                            Mensagem automática — não é necessário responder este e-mail.<br>
                            &copy; <?php echo date('Y'); ?> Secretaria Municipal de Meio Ambiente · Prefeitura de Pau dos Ferros/RN
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
