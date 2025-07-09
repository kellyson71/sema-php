<?php

/**
 * ===================================================================
 * 🚨 DETECTOR DE FALSOS POSITIVOS NO SISTEMA DE EMAIL
 * ===================================================================
 * 
 * Este script executa testes específicos para detectar e corrigir 
 * falsos positivos no sistema de envio de emails.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/database.php';

// Configuração de teste
$email_teste_real = "seuemail@gmail.com"; // ALTERE PARA UM EMAIL REAL PARA TESTE
$executar_teste_real = false; // Altere para true apenas quando quiser testar envio real

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 Detector de Falsos Positivos - Sistema de Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .test-group {
            margin: 30px 0;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
        }

        .test-passed {
            border-color: #28a745;
            background: #f8fff9;
        }

        .test-failed {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .test-warning {
            border-color: #ffc107;
            background: #fffbf0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .status-ok {
            color: #28a745;
            font-weight: bold;
        }

        .status-error {
            color: #dc3545;
            font-weight: bold;
        }

        .status-warning {
            color: #ffc107;
            font-weight: bold;
        }

        .code {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
        }

        h1 {
            color: #dc3545;
            text-align: center;
        }

        h2 {
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }

        .metric {
            display: inline-block;
            margin: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
            min-width: 120px;
        }

        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #495057;
        }

        .metric-label {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>🚨 Detector de Falsos Positivos</h1>
        <p style="text-align: center; color: #6c757d;">Sistema de Verificação de Emails - SEMA-PHP</p>

        <?php

        // Função para exibir resultado de teste
        function exibirResultadoTeste($nome_teste, $passou, $mensagem, $detalhes = '')
        {
            $classe = $passou ? 'test-passed' : 'test-failed';
            $icone = $passou ? '✅' : '❌';
            $status = $passou ? 'PASSOU' : 'FALHOU';

            echo "<div class='test-group $classe'>";
            echo "<h3>$icone $nome_teste - $status</h3>";
            echo "<p>$mensagem</p>";
            if ($detalhes) {
                echo "<div class='code'>$detalhes</div>";
            }
            echo "</div>";

            return $passou;
        }

        // Função para exibir alerta
        function exibirAlerta($tipo, $mensagem)
        {
            echo "<div class='alert alert-$tipo'>$mensagem</div>";
        }

        $total_testes = 0;
        $testes_passaram = 0;

        echo "<h2>🔍 Verificação de Falsos Positivos</h2>";

        // TESTE 1: Verificar se EMAIL_TEST_MODE está mascarando falhas
        $total_testes++;
        echo "<h3>Teste 1: Verificação do Modo de Teste</h3>";

        if (EMAIL_TEST_MODE) {
            exibirAlerta('warning', '⚠️ EMAIL_TEST_MODE está ATIVO - Todos os emails retornarão sucesso mesmo sem enviar!');

            // Testar se função ainda retorna true mesmo com dados inválidos
            $resultado_invalido = sendMail('email_invalido_sem_arroba', 'Nome Teste', 'Assunto', 'Mensagem', null);

            if ($resultado_invalido) {
                if (exibirResultadoTeste(
                    "Falso Positivo Detectado",
                    false,
                    "Sistema retorna sucesso para email inválido em modo de teste",
                    "Email testado: 'email_invalido_sem_arroba' (sem @) - Retornou: SUCCESS"
                )) $testes_passaram++;
            } else {
                if (exibirResultadoTeste(
                    "Validação de Email",
                    true,
                    "Sistema corretamente rejeita emails inválidos mesmo em modo teste",
                    "Email testado: 'email_invalido_sem_arroba' - Retornou: ERROR"
                )) $testes_passaram++;
            }
        } else {
            if (exibirResultadoTeste(
                "Modo de Teste",
                true,
                "EMAIL_TEST_MODE está desativado - emails serão enviados realmente",
                "EMAIL_TEST_MODE = false"
            )) $testes_passaram++;
        }

        // TESTE 2: Verificar configurações SMTP
        $total_testes++;
        echo "<h3>Teste 2: Configurações SMTP</h3>";

        $configuracoes_vazias = [];
        if (empty(SMTP_HOST)) $configuracoes_vazias[] = 'SMTP_HOST';
        if (empty(SMTP_USERNAME)) $configuracoes_vazias[] = 'SMTP_USERNAME';
        if (empty(SMTP_PASSWORD)) $configuracoes_vazias[] = 'SMTP_PASSWORD';
        if (empty(EMAIL_FROM)) $configuracoes_vazias[] = 'EMAIL_FROM';

        if (empty($configuracoes_vazias)) {
            if (exibirResultadoTeste(
                "Configurações SMTP",
                true,
                "Todas as configurações SMTP estão preenchidas",
                "HOST: " . SMTP_HOST . " | USER: " . SMTP_USERNAME . " | FROM: " . EMAIL_FROM
            )) $testes_passaram++;
        } else {
            if (exibirResultadoTeste(
                "Configurações SMTP",
                false,
                "Configurações SMTP faltando podem causar falsos negativos",
                "Faltando: " . implode(', ', $configuracoes_vazias)
            )) $testes_passaram++;
        }

        // TESTE 3: Verificar logs de teste vs logs reais
        $total_testes++;
        echo "<h3>Teste 3: Análise de Logs de Email</h3>";

        try {
            $db = new Database();

            // Verificar se a tabela tem a coluna eh_teste
            $tem_coluna_teste = false;
            try {
                $result = $db->query("SHOW COLUMNS FROM email_logs LIKE 'eh_teste'");
                $tem_coluna_teste = $result->rowCount() > 0;
            } catch (Exception $e) {
                // Coluna não existe
            }

            if ($tem_coluna_teste) {
                // Buscar estatísticas de teste vs real
                $result = $db->query("
            SELECT 
                eh_teste,
                status,
                COUNT(*) as total,
                COUNT(DISTINCT email_destino) as emails_unicos
            FROM email_logs 
            WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY eh_teste, status
        ");

                $stats = $result->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>";
                echo "<tr><th>Tipo</th><th>Status</th><th>Total</th><th>Emails Únicos</th><th>Análise</th></tr>";

                $problemas_detectados = [];

                foreach ($stats as $stat) {
                    $tipo = $stat['eh_teste'] ? 'TESTE' : 'REAL';
                    $analise = '';
                    $classe_status = 'status-ok';

                    // Detectar padrões suspeitos
                    if ($stat['eh_teste'] && $stat['status'] === 'SUCESSO' && $stat['total'] > 20) {
                        $analise = '⚠️ Muitos testes de sucesso';
                        $classe_status = 'status-warning';
                        $problemas_detectados[] = "Excesso de testes de sucesso ($stat[total])";
                    } elseif (!$stat['eh_teste'] && $stat['status'] === 'SUCESSO' && $stat['emails_unicos'] == 1 && $stat['total'] > 10) {
                        $analise = '🚨 Suspeito: muito sucesso para 1 email';
                        $classe_status = 'status-error';
                        $problemas_detectados[] = "Múltiplos sucessos para mesmo email real";
                    } elseif (!$stat['eh_teste'] && $stat['status'] === 'ERRO') {
                        $analise = '❌ Erros em emails reais';
                        $classe_status = 'status-error';
                        $problemas_detectados[] = "Erros em envios reais";
                    } else {
                        $analise = '✅ Normal';
                    }

                    echo "<tr>";
                    echo "<td>$tipo</td>";
                    echo "<td>$stat[status]</td>";
                    echo "<td>$stat[total]</td>";
                    echo "<td>$stat[emails_unicos]</td>";
                    echo "<td class='$classe_status'>$analise</td>";
                    echo "</tr>";
                }

                echo "</table>";

                if (empty($problemas_detectados)) {
                    if (exibirResultadoTeste(
                        "Análise de Logs",
                        true,
                        "Padrões de log estão normais - sem falsos positivos detectados",
                        "Análise dos últimos 7 dias não revelou padrões suspeitos"
                    )) $testes_passaram++;
                } else {
                    if (exibirResultadoTeste(
                        "Análise de Logs",
                        false,
                        "Padrões suspeitos detectados que podem indicar falsos positivos",
                        "Problemas: " . implode(', ', $problemas_detectados)
                    )) $testes_passaram++;
                }
            } else {
                if (exibirResultadoTeste(
                    "Estrutura do Banco",
                    false,
                    "Tabela email_logs não tem coluna 'eh_teste' - não pode distinguir testes de emails reais",
                    "Execute: database/melhorar_logs_email.sql"
                )) $testes_passaram++;
            }
        } catch (Exception $e) {
            if (exibirResultadoTeste(
                "Conexão com Banco",
                false,
                "Erro ao conectar com banco de dados",
                $e->getMessage()
            )) $testes_passaram++;
        }

        // TESTE 4: Teste de conectividade SMTP (apenas se não estiver em modo teste)
        $total_testes++;
        echo "<h3>Teste 4: Conectividade SMTP Real</h3>";

        if (!EMAIL_TEST_MODE && !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD)) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port = SMTP_PORT;
                $mail->Timeout = 10; // 10 segundos timeout

                if ($mail->smtpConnect()) {
                    if (exibirResultadoTeste(
                        "Conectividade SMTP",
                        true,
                        "Conexão SMTP estabelecida com sucesso - credenciais válidas",
                        "Servidor: " . SMTP_HOST . ":" . SMTP_PORT . " (" . SMTP_SECURE . ")"
                    )) $testes_passaram++;
                    $mail->smtpClose();
                } else {
                    if (exibirResultadoTeste(
                        "Conectividade SMTP",
                        false,
                        "Falha na conexão SMTP - pode causar falsos negativos",
                        "Verifique credenciais e configurações de rede"
                    )) $testes_passaram++;
                }
            } catch (Exception $e) {
                if (exibirResultadoTeste(
                    "Conectividade SMTP",
                    false,
                    "Erro na conexão SMTP: " . $e->getMessage(),
                    "Possível problema: firewall, credenciais ou configuração"
                )) $testes_passaram++;
            }
        } else {
            if (exibirResultadoTeste(
                "Conectividade SMTP",
                false,
                "Não foi possível testar SMTP - modo teste ativo ou credenciais não configuradas",
                "Configure SMTP_USERNAME, SMTP_PASSWORD e desative EMAIL_TEST_MODE"
            )) $testes_passaram++;
        }

        // TESTE 5: Teste de envio real (opcional)
        $total_testes++;
        echo "<h3>Teste 5: Envio Real de Email</h3>";

        if ($executar_teste_real && !EMAIL_TEST_MODE && !empty($email_teste_real) && filter_var($email_teste_real, FILTER_VALIDATE_EMAIL)) {

            exibirAlerta('info', '📧 Executando teste de envio real para: ' . $email_teste_real);

            $assunto_teste = "Teste Real do Sistema SEMA-PHP - " . date('Y-m-d H:i:s');
            $mensagem_teste = "
    <h2>Teste de Envio Real</h2>
    <p>Este é um teste real do sistema de envio de emails do SEMA-PHP.</p>
    <p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>
    <p><strong>Servidor:</strong> " . SMTP_HOST . "</p>
    <p>Se você recebeu este email, o sistema está funcionando corretamente.</p>
    ";

            $resultado_real = sendMail($email_teste_real, 'Teste Real', $assunto_teste, $mensagem_teste, 99999);

            if ($resultado_real) {
                if (exibirResultadoTeste(
                    "Envio Real",
                    true,
                    "Email de teste enviado com sucesso",
                    "Verifique a caixa de entrada de: $email_teste_real"
                )) $testes_passaram++;

                exibirAlerta('success', '✅ Verifique sua caixa de entrada (e spam) em: ' . $email_teste_real);
            } else {
                if (exibirResultadoTeste(
                    "Envio Real",
                    false,
                    "Falha no envio real - sistema pode ter problemas",
                    "Verifique logs do sistema para mais detalhes"
                )) $testes_passaram++;
            }
        } else {
            if (exibirResultadoTeste(
                "Envio Real",
                false,
                "Teste de envio real não executado",
                "Para executar: configure \$email_teste_real e \$executar_teste_real = true"
            )) $testes_passaram++;
        }

        // RESUMO FINAL
        echo "<h2>📊 Resumo do Diagnóstico</h2>";

        $percentual_sucesso = round(($testes_passaram / $total_testes) * 100);

        echo "<div style='text-align: center; margin: 30px 0;'>";
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$testes_passaram/$total_testes</div>";
        echo "<div class='metric-label'>Testes Passaram</div>";
        echo "</div>";

        echo "<div class='metric'>";
        echo "<div class='metric-value'>$percentual_sucesso%</div>";
        echo "<div class='metric-label'>Taxa de Sucesso</div>";
        echo "</div>";
        echo "</div>";

        if ($percentual_sucesso >= 80) {
            exibirAlerta('success', '✅ Sistema de email está funcionando bem com poucos falsos positivos detectados.');
        } elseif ($percentual_sucesso >= 60) {
            exibirAlerta('warning', '⚠️ Sistema de email tem alguns problemas que podem causar falsos positivos. Recomenda-se revisar as configurações.');
        } else {
            exibirAlerta('danger', '❌ Sistema de email tem sérios problemas com múltiplos falsos positivos detectados. Necessária intervenção imediata.');
        }

        echo "<h3>🔧 Ações Recomendadas:</h3>";
        echo "<ul>";

        if (EMAIL_TEST_MODE) {
            echo "<li>🔄 Desative EMAIL_TEST_MODE em config.php para testes reais</li>";
        }

        if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
            echo "<li>⚙️ Configure credenciais SMTP válidas em config.php</li>";
        }

        if (!$tem_coluna_teste) {
            echo "<li>🗄️ Execute database/melhorar_logs_email.sql para melhorar tracking de emails</li>";
        }

        echo "<li>📊 Monitore regularmente os logs em admin/logs_email.php</li>";
        echo "<li>✉️ Execute testes reais periodicamente alterando \$executar_teste_real = true</li>";
        echo "<li>🔍 Verifique se emails estão chegando na caixa de entrada dos usuários reais</li>";

        echo "</ul>";

        ?>

        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <p><strong>🚨 Importante:</strong> Este diagnóstico deve ser executado regularmente para garantir a confiabilidade do sistema de emails.</p>
            <p><small>Última execução: <?php echo date('Y-m-d H:i:s'); ?></small></p>
        </div>

    </div>

</body>

</html>