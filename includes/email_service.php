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
}
