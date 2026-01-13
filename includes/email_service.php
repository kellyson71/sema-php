<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/database.php');

/**
 * Registra o log de email no banco de dados
 */
function logEmail($requerimento_id, $email_destino, $assunto, $mensagem, $status, $erro = null, $eh_teste = false)
{
    try {
        // Validar dados obrigatórios
        if (empty($email_destino)) {
            error_log("Erro ao registrar log de email: email_destino é obrigatório");
            return false;
        }

        $db = new Database();

        // Pega o usuário da sessão
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $usuario = isset($_SESSION['admin_nome']) ? $_SESSION['admin_nome'] : 'Sistema';

        // Detectar automaticamente se é teste baseado no email ou assunto
        $eh_teste_auto = $eh_teste ||
            strpos($email_destino, '@example.com') !== false ||
            strpos($email_destino, 'teste') !== false ||
            strpos($email_destino, 'test') !== false ||
            strpos($assunto, '[TESTE]') !== false ||
            strpos($assunto, 'teste') !== false ||
            strpos($assunto, 'test') !== false ||
            EMAIL_TEST_MODE;

        $data = [
            'requerimento_id' => $requerimento_id,
            'email_destino' => $email_destino,
            'assunto' => $assunto,
            'mensagem' => $mensagem,
            'usuario_envio' => $usuario,
            'status' => $status,
            'erro' => $erro,
            'eh_teste' => $eh_teste_auto ? 1 : 0,
            'detalhes_envio' => $eh_teste_auto ? 'Enviado em modo de teste' : 'Enviado via SMTP: ' . SMTP_HOST
        ];

        return $db->insert('email_logs', $data);
    } catch (Exception $e) {
        error_log("Erro ao registrar log de email: " . $e->getMessage());
        return false;
    }
}

/**
 * Função principal para envio de emails
 */
function sendMail($email, $nome, $assunto, $mensagem, $requerimento_id = null)
{
    error_log("Função sendMail chamada para: $email");

    if (EMAIL_TEST_MODE) {
        error_log("=== EMAIL EM MODO DE TESTE ===");
        error_log("Para: " . $email);
        error_log("Nome: " . $nome);
        error_log("Assunto: " . $assunto);
        error_log("Mensagem: " . $mensagem);
        error_log("============================");

        if ($requerimento_id) {
            // Marcar claramente como teste nos logs
            logEmail($requerimento_id, $email, "[TESTE] " . $assunto, $mensagem, 'SUCESSO', 'Enviado em modo de teste - não foi enviado realmente', true);
        }
        return true;
    }

    // Validações básicas antes de tentar enviar
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido: " . $email;
        error_log($erro);
        if ($requerimento_id) {
            logEmail($requerimento_id, $email, $assunto, $mensagem, 'ERRO', $erro);
        }
        return false;
    }

    if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        $erro = "Credenciais SMTP não configuradas";
        error_log($erro);
        if ($requerimento_id) {
            logEmail($requerimento_id, $email, $assunto, $mensagem, 'ERRO', $erro);
        }
        return false;
    }

    try {
        error_log("Iniciando envio de email para: " . $email);

        $mail = new PHPMailer(true);

        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->Port = SMTP_PORT;

        // Configurações do remetente e destinatário
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($email, $nome);

        // Configurações da mensagem
        $mail->Subject = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
        $mail->isHTML(true);
        $mail->Body = mb_convert_encoding($mensagem, 'UTF-8', 'UTF-8');

        if (!$mail->send()) {
            $erro = $mail->ErrorInfo;
            error_log("Erro ao enviar email: " . $erro);
            if ($requerimento_id) {
                logEmail($requerimento_id, $email, $assunto, $mensagem, 'ERRO', $erro, false);
            }
            return false;
        } else {
            error_log("Email enviado com sucesso para: " . $email);
            if ($requerimento_id) {
                // Registrar sucesso com informações adicionais
                $detalhes_sucesso = "Email enviado via SMTP: " . SMTP_HOST;
                logEmail($requerimento_id, $email, $assunto, $mensagem, 'SUCESSO', $detalhes_sucesso, false);
            }
            return true;
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
        error_log("Exceção ao enviar email: " . $erro);
        if ($requerimento_id) {
            logEmail($requerimento_id, $email, $assunto, $mensagem, 'ERRO', $erro, false);
        }
        return false;
    }
}

/**
 * Classe para gerenciar envio de emails (mantida para compatibilidade)
 */
class EmailService
{
    /**
     * Enviar email de confirmação de protocolo
     * 
     * @param string $to_email Email do destinatário
     * @param string $to_name Nome do destinatário
     * @param string $protocolo_interno Protocolo interno gerado pelo sistema
     * @param string $tipo_alvara Tipo de alvará solicitado
     * @param array $dados_requerimento Dados do requerimento
     * @return bool True se enviado com sucesso, false caso contrário
     */
    public function enviarEmailProtocolo($to_email, $to_name, $protocolo_interno, $tipo_alvara, $dados_requerimento = [])
    {
        try {
            $subject = "Confirmação de Requerimento - Protocolo #{$protocolo_interno}";

            // Carregar o template de email
            $body = $this->carregarTemplateProtocolo($to_name, $protocolo_interno, $tipo_alvara, $dados_requerimento);

            // Buscar ID do requerimento pelo protocolo
            $requerimento_id = null;
            if (isset($dados_requerimento['id'])) {
                $requerimento_id = $dados_requerimento['id'];
            }

            return sendMail($to_email, $to_name, $subject, $body, $requerimento_id);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de protocolo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email com protocolo oficial da prefeitura
     * 
     * @param string $to_email Email do destinatário
     * @param string $to_name Nome do destinatário
     * @param string $protocolo_oficial Protocolo oficial da prefeitura
     * @param int $requerimento_id ID do requerimento
     * @return bool True se enviado com sucesso, false caso contrário
     */
    public function enviarEmailProtocoloOficial($to_email, $to_name, $protocolo_oficial, $requerimento_id = null)
    {
        try {
            $subject = "Protocolo Oficial da Prefeitura - #{$protocolo_oficial}";

            $body = $this->carregarTemplateProtocoloOficial($to_name, $protocolo_oficial);

            return sendMail($to_email, $to_name, $subject, $body, $requerimento_id);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de protocolo oficial: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email de indeferimento do processo
     * 
     * @param string $to_email Email do destinatário
     * @param string $to_name Nome do destinatário
     * @param string $protocolo Protocolo do requerimento
     * @param string $tipo_alvara Tipo de alvará solicitado
     * @param string $motivo_indeferimento Motivo do indeferimento
     * @param string $orientacoes_adicionais Orientações adicionais para o requerente
     * @param int $requerimento_id ID do requerimento
     * @return bool True se enviado com sucesso, false caso contrário
     */
    public function enviarEmailIndeferimento($to_email, $to_name, $protocolo, $tipo_alvara, $motivo_indeferimento, $orientacoes_adicionais = '', $requerimento_id = null)
    {
        try {
            $subject = "[SEMA] Protocolo #{$protocolo} - Processo Indeferido";

            $body = $this->carregarTemplateIndeferimento($to_name, $protocolo, $tipo_alvara, $motivo_indeferimento, $orientacoes_adicionais);

            return sendMail($to_email, $to_name, $subject, $body, $requerimento_id);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de indeferimento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Carregar template de email para confirmação de protocolo
     */
    private function carregarTemplateProtocolo($nome, $protocolo, $tipo_alvara, $dados = [])
    {
        ob_start();
        include __DIR__ . '/../templates/email_protocolo.php';
        return ob_get_clean();
    }

    /**
     * Carregar template de email para protocolo oficial
     */
    private function carregarTemplateProtocoloOficial($nome_destinatario, $protocolo_oficial)
    {
        ob_start();
        include __DIR__ . '/../templates/email_protocolo_oficial.php';
        return ob_get_clean();
    }

    /**
     * Carregar template de email para indeferimento de processo
     */
    private function carregarTemplateIndeferimento($nome_destinatario, $protocolo, $tipo_alvara, $motivo_indeferimento, $orientacoes_adicionais)
    {
        ob_start();
        include __DIR__ . '/../templates/email_indeferimento.php';
        return ob_get_clean();
    }

    /**
     * Enviar email com código de verificação para assinatura
     * 
     * @param string $to_email Email do destinatário
     * @param string $to_name Nome do destinatário
     * @param string $codigo Código de verificação de 6 dígitos
     * @return bool True se enviado com sucesso, false caso contrário
     */
    public function enviarEmailCodigoVerificacao($to_email, $to_name, $codigo)
    {
        try {
            $subject = "Código de Verificação para Assinatura Digital - SEMA";

            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #2D8661, #134E5E); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .code-box { background: white; border: 2px dashed #2D8661; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
                    .code { font-size: 32px; font-weight: bold; color: #2D8661; letter-spacing: 8px; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔐 Código de Verificação</h1>
                    </div>
                    <div class='content'>
                        <p>Olá, <strong>{$to_name}</strong>!</p>
                        <p>Você solicitou um código de verificação para assinar digitalmente um documento no sistema SEMA.</p>
                        
                        <div class='code-box'>
                            <p style='margin: 0; font-size: 14px; color: #666;'>Seu código de verificação é:</p>
                            <div class='code'>{$codigo}</div>
                        </div>

                        <div class='warning'>
                            <strong>⚠️ Importante:</strong>
                            <ul style='margin: 10px 0; padding-left: 20px;'>
                                <li>Este código é válido por <strong>15 minutos</strong></li>
                                <li>Não compartilhe este código com ninguém</li>
                                <li>Se você não solicitou este código, ignore este email</li>
                            </ul>
                        </div>

                        <p>Após validar o código, você terá <strong>3 horas</strong> para assinar documentos sem precisar validar novamente.</p>
                        
                        <div class='footer'>
                            <p>Este é um email automático, por favor não responda.</p>
                            <p>© " . date('Y') . " SEMA - Secretaria Municipal de Meio Ambiente</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";

            // Não passa requerimento_id pois é um email de verificação do sistema
            return sendMail($to_email, $to_name, $subject, $body, null);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de código de verificação: " . $e->getMessage());
            return false;
        }
    }
}
