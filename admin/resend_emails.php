<?php
/**
 * Script de Reenvio de Emails
 * 
 * Este script permite reenviar emails que foram enviados em modo de teste.
 * Apenas administradores autenticados podem acessar esta página.
 */

// Iniciar buffer de saída para capturar qualquer output indesejado
ob_start();

require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/database.php');
require_once(__DIR__ . '/../includes/email_service.php');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$emailService = new EmailService();

// Processar requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Limpar qualquer output anterior
    ob_clean();
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_test_emails') {
        try {
            // Buscar emails marcados como teste
            $query = "SELECT 
                        el.id,
                        el.requerimento_id,
                        el.email_destino,
                        el.assunto,
                        el.data_envio,
                        el.usuario_envio,
                        r.protocolo,
                        r.tipo_alvara,
                        req.nome as requerente_nome
                      FROM email_logs el
                      LEFT JOIN requerimentos r ON el.requerimento_id = r.id
                      LEFT JOIN requerentes req ON r.requerente_id = req.id
                      WHERE el.eh_teste = 1 AND el.status = 'SUCESSO'
                      ORDER BY el.data_envio DESC";
            
            $result = $db->query($query);
            $emails = [];
            
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $emails[] = $row;
            }
            
            echo json_encode(['success' => true, 'emails' => $emails]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar emails: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'resend_email') {
        try {
            $email_id = intval($_POST['email_id']);
            
            // Buscar dados do email original
            $query = "SELECT 
                        el.*,
                        r.protocolo,
                        r.tipo_alvara,
                        r.endereco_objetivo,
                        r.data_envio as requerimento_data,
                        req.nome as requerente_nome,
                        req.email as requerente_email
                      FROM email_logs el
                      LEFT JOIN requerimentos r ON el.requerimento_id = r.id
                      LEFT JOIN requerentes req ON r.requerente_id = req.id
                      WHERE el.id = :id AND el.eh_teste = 1";
            
            $result = $db->query($query, ['id' => $email_id]);
            $email_data = $result->fetch(PDO::FETCH_ASSOC);
            
            if (!$email_data) {
                echo json_encode(['success' => false, 'message' => 'Email não encontrado']);
                exit;
            }
            
            // Verificar se o assunto contém informações sobre protocolo oficial ou confirmação
            $is_protocol_official = strpos($email_data['assunto'], 'Protocolo Oficial') !== false;
            $is_confirmation = strpos($email_data['assunto'], 'Confirmação de Requerimento') !== false;
            
            $success = false;
            $error_message = '';
            
            // Desabilitar error_log temporariamente para evitar output
            $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
                // Silenciar erros durante o envio
                return true;
            });
            
            if ($is_confirmation) {
                // Reenviar email de confirmação de protocolo
                $dados_requerimento = [
                    'id' => $email_data['requerimento_id'],
                    'data_envio' => $email_data['requerimento_data'],
                    'endereco_objetivo' => $email_data['endereco_objetivo']
                ];
                
                $success = $emailService->enviarEmailProtocolo(
                    $email_data['email_destino'],
                    $email_data['requerente_nome'],
                    $email_data['protocolo'],
                    $email_data['tipo_alvara'],
                    $dados_requerimento
                );
            } elseif ($is_protocol_official) {
                // Extrair protocolo oficial do assunto
                preg_match('/#(.+)$/', $email_data['assunto'], $matches);
                $protocolo_oficial = $matches[1] ?? 'N/A';
                
                $success = $emailService->enviarEmailProtocoloOficial(
                    $email_data['email_destino'],
                    $email_data['requerente_nome'],
                    $protocolo_oficial,
                    $email_data['requerimento_id']
                );
            } else {
                // Tipo de email não reconhecido
                $error_message = 'Tipo de email não suportado para reenvio automático';
            }
            
            // Restaurar error handler
            if ($old_error_handler) {
                set_error_handler($old_error_handler);
            }
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Email reenviado com sucesso!'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $error_message ?: 'Falha ao reenviar email. Verifique os logs do servidor.'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenvio de Emails - SEMA</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-box strong {
            color: #856404;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        .emails-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .logs-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 600px;
            overflow-y: auto;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .panel-header h2 {
            color: #333;
            font-size: 18px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-success {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-success:hover {
            background: #218838;
        }

        .email-table {
            width: 100%;
            border-collapse: collapse;
        }

        .email-table thead {
            background: #f8f9fa;
        }

        .email-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .email-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            color: #333;
        }

        .email-table tbody tr:hover {
            background: #f8f9fa;
        }

        .email-table input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .log-entry {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #ccc;
            background: #f8f9fa;
            font-size: 13px;
        }

        .log-entry.success {
            border-left-color: #28a745;
            background: #d4edda;
        }

        .log-entry.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }

        .log-entry.info {
            border-left-color: #17a2b8;
            background: #d1ecf1;
        }

        .log-entry .timestamp {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 13px;
            opacity: 0.9;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Reenvio de Emails de Teste</h1>
            <p>Secretaria Municipal de Meio Ambiente - Sistema de Recuperação de Emails</p>
        </div>

        <div class="warning-box">
            <span style="font-size: 24px;">⚠️</span>
            <div>
                <strong>Atenção:</strong> Esta ferramenta reenvia emails que foram enviados em modo de teste. 
                Certifique-se de que o modo de teste está desativado em produção (<code>EMAIL_TEST_MODE = false</code>).
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="number" id="total-emails">0</div>
                <div class="label">Emails de Teste</div>
            </div>
            <div class="stat-card">
                <div class="number" id="selected-count">0</div>
                <div class="label">Selecionados</div>
            </div>
            <div class="stat-card">
                <div class="number" id="sent-count">0</div>
                <div class="label">Reenviados</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="emails-panel">
                <div class="panel-header">
                    <h2>Emails Marcados como Teste</h2>
                    <button class="btn btn-primary" id="resend-selected" disabled>
                        Reenviar Selecionados
                    </button>
                </div>
                
                <div id="emails-container">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Carregando emails...</p>
                    </div>
                </div>
            </div>

            <div class="logs-panel">
                <div class="panel-header">
                    <h2>Logs de Reenvio</h2>
                </div>
                <div id="logs-container">
                    <div class="log-entry info">
                        <div class="timestamp">Sistema iniciado</div>
                        <div>Aguardando seleção de emails para reenvio...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let emails = [];
        let selectedEmails = new Set();
        let sentCount = 0;

        // Carregar emails ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadEmails();
        });

        function loadEmails() {
            fetch('resend_emails.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_test_emails'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    emails = data.emails;
                    renderEmails();
                    updateStats();
                    addLog('success', `${emails.length} emails de teste carregados`);
                }
            })
            .catch(error => {
                addLog('error', 'Erro ao carregar emails: ' + error.message);
            });
        }

        function renderEmails() {
            const container = document.getElementById('emails-container');
            
            if (emails.length === 0) {
                container.innerHTML = '<div class="loading"><p>Nenhum email de teste encontrado.</p></div>';
                return;
            }

            let html = `
                <table class="email-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Destinatário</th>
                            <th>Assunto</th>
                            <th>Protocolo</th>
                            <th>Data</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            emails.forEach(email => {
                const subject = email.assunto.replace('[TESTE] ', '');
                const date = new Date(email.data_envio).toLocaleString('pt-BR');
                
                html += `
                    <tr>
                        <td><input type="checkbox" class="email-checkbox" data-id="${email.id}"></td>
                        <td>${email.email_destino}</td>
                        <td>${subject}</td>
                        <td>${email.protocolo || 'N/A'}</td>
                        <td>${date}</td>
                        <td><button class="btn btn-success" onclick="resendSingle(${email.id})">Reenviar</button></td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;

            // Event listeners
            document.getElementById('select-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.email-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                    if (this.checked) {
                        selectedEmails.add(parseInt(cb.dataset.id));
                    } else {
                        selectedEmails.clear();
                    }
                });
                updateStats();
            });

            document.querySelectorAll('.email-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    const id = parseInt(this.dataset.id);
                    if (this.checked) {
                        selectedEmails.add(id);
                    } else {
                        selectedEmails.delete(id);
                    }
                    updateStats();
                });
            });
        }

        function updateStats() {
            document.getElementById('total-emails').textContent = emails.length;
            document.getElementById('selected-count').textContent = selectedEmails.size;
            document.getElementById('sent-count').textContent = sentCount;
            
            const resendBtn = document.getElementById('resend-selected');
            resendBtn.disabled = selectedEmails.size === 0;
        }

        function addLog(type, message) {
            const logsContainer = document.getElementById('logs-container');
            const timestamp = new Date().toLocaleTimeString('pt-BR');
            
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry ${type}`;
            logEntry.innerHTML = `
                <div class="timestamp">${timestamp}</div>
                <div>${message}</div>
            `;
            
            logsContainer.insertBefore(logEntry, logsContainer.firstChild);
        }

        function resendSingle(emailId) {
            resendEmail(emailId);
        }

        document.getElementById('resend-selected').addEventListener('click', function() {
            if (selectedEmails.size === 0) return;
            
            this.disabled = true;
            addLog('info', `Iniciando reenvio de ${selectedEmails.size} emails...`);
            
            const emailIds = Array.from(selectedEmails);
            let index = 0;
            
            function sendNext() {
                if (index >= emailIds.length) {
                    addLog('success', `Processo concluído! ${sentCount} emails reenviados.`);
                    document.getElementById('resend-selected').disabled = false;
                    return;
                }
                
                resendEmail(emailIds[index], () => {
                    index++;
                    setTimeout(sendNext, 500); // Delay entre envios
                });
            }
            
            sendNext();
        });

        function resendEmail(emailId, callback) {
            const email = emails.find(e => e.id === emailId);
            
            fetch('resend_emails.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=resend_email&email_id=${emailId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    sentCount++;
                    updateStats();
                    addLog('success', `✓ Email reenviado para ${email.email_destino}`);
                } else {
                    addLog('error', `✗ Falha ao reenviar para ${email.email_destino}: ${data.message}`);
                }
                
                if (callback) callback();
            })
            .catch(error => {
                addLog('error', `✗ Erro ao reenviar para ${email.email_destino}: ${error.message}`);
                if (callback) callback();
            });
        }
    </script>
</body>
</html>
