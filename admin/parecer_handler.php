<?php
require_once 'conexao.php';
require_once '../includes/parecer_service.php';
require_once '../includes/email_service.php';
require_once '../includes/functions.php';


verificaLogin();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? '';

    $parecerService = new ParecerService();

    switch ($action) {
        case 'verificar_sessao_assinatura':
            $sessaoValida = false;
            $tempoRestante = 0;
            
            if (isset($_SESSION['assinatura_auth_valid_until'])) {
                $agora = time();
                if ($agora < $_SESSION['assinatura_auth_valid_until']) {
                    $sessaoValida = true;
                    $tempoRestante = $_SESSION['assinatura_auth_valid_until'] - $agora;
                }
            }
            
            echo json_encode([
                'success' => true,
                'sessao_valida' => $sessaoValida,
                'tempo_restante' => $tempoRestante
            ]);
            break;

        case 'enviar_codigo_assinatura':
            // Gerar código de 6 dígitos
            $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Salvar na sessão (expira em 15 min)
            $_SESSION['assinatura_otp_code'] = $codigo;
            $_SESSION['assinatura_otp_expires'] = time() + (15 * 60);
            
            // Obter dados do admin
            $stmt = $pdo->prepare("SELECT nome, email FROM administradores WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin || empty($admin['email'])) {
                echo json_encode(['success' => false, 'error' => 'Email do administrador não encontrado.']);
                exit;
            }
            
            // Enviar email
            $emailService = new EmailService();
            $enviado = $emailService->enviarEmailCodigoVerificacao($admin['email'], $admin['nome'], $codigo);
            
            if ($enviado) {
                // Mascarar email para exibir no frontend
                $emailMascarado = preg_replace('/(?<=.).(?=.*@)/', '*', $admin['email']);
                echo json_encode(['success' => true, 'email_mascarado' => $emailMascarado]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao enviar email. Tente novamente.']);
            }
            break;

        case 'validar_codigo_assinatura':
            $codigoRecebido = $input['codigo'] ?? '';
            
            if (empty($codigoRecebido)) {
                echo json_encode(['success' => false, 'error' => 'Código não informado.']);
                exit;
            }
            
            if (!isset($_SESSION['assinatura_otp_code']) || !isset($_SESSION['assinatura_otp_expires'])) {
                echo json_encode(['success' => false, 'error' => 'Nenhum código gerado ou código expirado.']);
                exit;
            }
            
            if (time() > $_SESSION['assinatura_otp_expires']) {
                unset($_SESSION['assinatura_otp_code']);
                unset($_SESSION['assinatura_otp_expires']);
                echo json_encode(['success' => false, 'error' => 'Código expirado. Solicite um novo.']);
                exit;
            }
            
            if ($codigoRecebido === $_SESSION['assinatura_otp_code']) {
                // Código correto! Definir sessão de 3 horas
                $_SESSION['assinatura_auth_valid_until'] = time() + (3 * 60 * 60);
                
                // Limpar OTP
                unset($_SESSION['assinatura_otp_code']);
                unset($_SESSION['assinatura_otp_expires']);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Código incorreto.']);
            }
            break;

        case 'listar_templates':
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);
            $templates = $parecerService->listarTemplates();
            $templatesList = array_map(function($t) {
                return is_array($t) ? $t['nome'] : $t;
            }, $templates);

            // Buscar rascunhos (últimos 3 documentos gerados)
            $rascunhos = [];
            if ($requerimento_id > 0) {
                $pastaRequerimento = dirname(__DIR__) . '/uploads/pareceres/' . $requerimento_id . '/';
                if (is_dir($pastaRequerimento)) {
                    $arquivos = glob($pastaRequerimento . '*.json');
                    if ($arquivos) {
                        // Ordenar por data de modificação (mais recente primeiro)
                        usort($arquivos, function($a, $b) {
                            return filemtime($b) - filemtime($a);
                        });

                        // Pegar os 3 mais recentes
                        $recentes = array_slice($arquivos, 0, 3);
                        foreach ($recentes as $arquivo) {
                            $dados = json_decode(file_get_contents($arquivo), true);
                            if ($dados) {
                                // Tenta identificar um nome amigável para o rascunho
                                $nomeTemplate = $dados['template'] ?? 'Documento';
                                // Formatar nome do rascunho: "Rascunho: Tipo do Template (DD/MM/YYYY HH:mm)"
                                $dataCriacao = isset($dados['data_criacao']) ? date('d/m/Y H:i', strtotime($dados['data_criacao'])) : date('d/m/Y H:i', filemtime($arquivo));
                                
                                // Buscar nome do assinante
                                $nomeAssinante = $dados['dados_assinatura']['assinante_nome'] ?? 
                                                 $dados['dados_assinatura']['assinante_nome_completo'] ?? 
                                                 'Desconhecido';
                                
                                $rascunhos[] = [
                                    'id' => 'draft:' . basename($arquivo),
                                    'nome' => $nomeTemplate,
                                    'data' => $dataCriacao,
                                    'assinante' => $nomeAssinante,
                                    'label' => "{$nomeTemplate} - {$nomeAssinante} ({$dataCriacao})"
                                ];
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'templates' => $templatesList,
                'templates_detalhados' => $templates,
                'rascunhos' => $rascunhos
            ]);
            break;

        case 'carregar_template':
            $template = $input['template'] ?? '';
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);

            if (empty($template) || $requerimento_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            // Verificar se é um draft (rascunho/documento anterior)
            if (strpos($template, 'draft:') === 0) {
                $nomeArquivoDraft = substr($template, 6); // Remove 'draft:'
                $pastaRequerimento = dirname(__DIR__) . '/uploads/pareceres/' . $requerimento_id . '/';
                $caminhoDraft = $pastaRequerimento . $nomeArquivoDraft;

                if (!file_exists($caminhoDraft)) {
                    throw new Exception('Rascunho não encontrado');
                }

                $dadosDraft = json_decode(file_get_contents($caminhoDraft), true);
                if (!$dadosDraft) {
                    throw new Exception('Erro ao ler dados do rascunho');
                }

                // Prioriza retornar o html_completo (do editor) se existir, senão html_com_assinatura
                $html = $dadosDraft['html_completo'] ?? $dadosDraft['html_com_assinatura'] ?? '';

                if (empty($html)) {
                     // Fallback para ler o arquivo html correspondente se não estiver no json
                     $caminhoHtmlRelativo = $dadosDraft['caminho_html'] ?? '';
                     if ($caminhoHtmlRelativo) {
                         $caminhoHtmlAbsoluto = dirname(__DIR__) . '/uploads/' . $caminhoHtmlRelativo;
                         if (file_exists($caminhoHtmlAbsoluto)) {
                             $html = file_get_contents($caminhoHtmlAbsoluto);
                         }
                     }
                }

                echo json_encode([
                    'success' => true,
                    'html' => $html,
                    'is_draft' => true,
                    'dados' => [] // Drafts já vêm preenchidos
                ]);
                break; // Sai do switch/case
            }

            // --- Lógica original para templates padrão abaixo ---

            $stmt = $pdo->prepare("
                SELECT r.*,
                       req.nome as requerente_nome,
                       req.cpf_cnpj as requerente_cpf_cnpj,
                       req.telefone as requerente_telefone,
                       req.email as requerente_email,
                       p.nome as proprietario_nome,
                       p.cpf_cnpj as proprietario_cpf_cnpj
                FROM requerimentos r
                JOIN requerentes req ON r.requerente_id = req.id
                LEFT JOIN proprietarios p ON r.proprietario_id = p.id
                WHERE r.id = ?
            ");
            $stmt->execute([$requerimento_id]);
            $requerimento = $stmt->fetch();

            if (!$requerimento) {
                throw new Exception('Requerimento não encontrado');
            }

            $templatePath = $parecerService->carregarTemplate($template);

            $stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, email, cpf, cargo, matricula_portaria FROM administradores WHERE id = ?");
            $stmtAdmin->execute([$_SESSION['admin_id']]);
            $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            $dados = $parecerService->preencherDados($requerimento, $adminData);
            $html = $parecerService->substituirVariaveisDocx($templatePath, $dados);

            echo json_encode([
                'success' => true,
                'html' => $html,
                'dados' => $dados
            ]);
            break;

        case 'gerar_pdf':
            $html = $input['html'] ?? '';
            $template = $input['template'] ?? '';
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);

            if (empty($html) || empty($template) || $requerimento_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            $resultado = $parecerService->salvarParecer($requerimento_id, $html, $template);

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'],
                $requerimento_id,
                "Gerou parecer técnico usando template: {$template}"
            ]);

            echo json_encode([
                'success' => true,
                'arquivo' => $resultado['nome'],
                'caminho' => $resultado['caminho_relativo'],
                'mensagem' => 'Parecer gerado com sucesso!'
            ]);
            break;

        case 'listar_pareceres':
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);

            if ($requerimento_id <= 0) {
                throw new Exception('ID do requerimento inválido');
            }

            $pareceres = $parecerService->listarPareceres($requerimento_id);

            echo json_encode([
                'success' => true,
                'pareceres' => $pareceres
            ]);
            break;

        case 'download_parecer':
            $arquivo = $_GET['arquivo'] ?? '';
            $requerimento_id = (int)($_GET['requerimento_id'] ?? 0);

            if (empty($arquivo) || $requerimento_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            $parecerService->downloadParecer($requerimento_id, $arquivo);
            break;

        case 'excluir_parecer':
            $arquivo = $input['arquivo'] ?? '';
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);

            if (empty($arquivo) || $requerimento_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            $sucesso = $parecerService->excluirParecer($requerimento_id, $arquivo);

            if ($sucesso) {
                $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                $stmt->execute([
                    $_SESSION['admin_id'],
                    $requerimento_id,
                    "Excluiu parecer técnico: {$arquivo}"
                ]);
            }

            echo json_encode([
                'success' => $sucesso,
                'mensagem' => $sucesso ? 'Parecer excluído com sucesso!' : 'Erro ao excluir parecer'
            ]);
            break;

        case 'validar_senha':
            $senha = $input['senha'] ?? '';

            if (empty($senha)) {
                throw new Exception('Senha não fornecida');
            }

            // Buscar senha do admin logado
            $stmt = $pdo->prepare("SELECT senha FROM administradores WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || !password_verify($senha, $admin['senha'])) {
                echo json_encode(['success' => false, 'error' => 'Senha incorreta']);
                exit;
            }

            echo json_encode(['success' => true]);
            break;

        case 'gerar_pdf_com_assinatura':
            require_once '../includes/assinatura_digital_service.php';
            require_once '../includes/qrcode_service.php';

            $html = $input['html'] ?? '';
            $template = $input['template'] ?? '';
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);
            $assinatura = $input['assinatura'] ?? null;
            $tipoAssinatura = $input['tipo_assinatura'] ?? '';
            $adminNome = $input['admin_nome'] ?? '';
            $adminCpf = $input['admin_cpf'] ?? '';
            $adminCargo = $input['admin_cargo'] ?? '';
            $dataAssinatura = $input['data_assinatura'] ?? '';

            // VERIFICAÇÃO DE SEGURANÇA: Sessão de 3h (Adicionado para evitar bypass)
            if (!isset($_SESSION['assinatura_auth_valid_until']) || time() > $_SESSION['assinatura_auth_valid_until']) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Sessão de assinatura expirada. Por favor, realize a verificação novamente.',
                    'code' => 'SESSION_EXPIRED'
                ]);
                exit;
            }

            if (empty($html) || empty($template) || $requerimento_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            $stmt = $pdo->prepare("SELECT nome, nome_completo, email, cpf, cargo, matricula_portaria FROM administradores WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            $nomeCompleto = $adminData['nome_completo'] ?? $adminNome;
            $emailAdmin = $adminData['email'] ?? '';
            $matriculaPortaria = $adminData['matricula_portaria'] ?? '';
            $adminCpf = $adminData['cpf'] ?? $adminCpf;
            $adminCargo = $adminData['cargo'] ?? $adminCargo;

            $resultadoPreliminar = $parecerService->salvarParecer($requerimento_id, $html, $template);

            $assinaturaService = new AssinaturaDigitalService($pdo);

            $dadosAssinatura = [
                'requerimento_id' => $requerimento_id,
                'tipo_documento' => 'parecer',
                'nome_arquivo' => $resultadoPreliminar['nome'],
                'caminho_arquivo' => $resultadoPreliminar['caminho'],
                'assinante_id' => $_SESSION['admin_id'],
                'assinante_nome' => $nomeCompleto,
                'assinante_cpf' => $adminCpf,
                'assinante_cargo' => $adminCargo,
                'assinante_email' => $emailAdmin,
                'assinante_matricula_portaria' => $matriculaPortaria,
                'tipo_assinatura' => $tipoAssinatura,
                'assinatura_visual' => is_string($assinatura) ? $assinatura : json_encode($assinatura)
            ];

            $resultadoAssinatura = $assinaturaService->registrarAssinatura($dadosAssinatura);

            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $scriptDir = dirname($scriptName);
            $basePath = '';
            if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') {
                $basePath = rtrim($scriptDir, '/\\');
                if (strpos($basePath, '/admin') !== false) {
                    $basePath = str_replace('/admin', '', $basePath);
                }
                if (strpos($basePath, '\\admin') !== false) {
                    $basePath = str_replace('\\admin', '', $basePath);
                }
                $basePath = rtrim($basePath, '/\\');
            }
            $urlVerificacao = $protocolo . '://' . $host . $basePath . '/consultar/verificar.php?id=' . $resultadoAssinatura['documento_id'];

            $qrCodeDataUri = QRCodeService::gerarQRCode($urlVerificacao);

            $blocoAssinatura = '<div style="margin-top: 50px; border-top: 1px solid #999; padding-top: 30px; page-break-inside: avoid;">';

            if ($tipoAssinatura === 'desenho') {
                $blocoAssinatura .= '<div style="text-align: center; margin: 20px 0;">';
                $blocoAssinatura .= '<img src="' . $assinatura . '" style="max-width: 250px; height: auto;" />';
                $blocoAssinatura .= '</div>';
            } else {
                $assinaturaData = json_decode($dadosAssinatura['assinatura_visual'], true);
                $blocoAssinatura .= '<div style="text-align: center; margin: 20px 0; font-family: ' . $assinaturaData['fonte'] . '; font-size: 32px;">';
                $blocoAssinatura .= htmlspecialchars($assinaturaData['texto']);
                $blocoAssinatura .= '</div>';
            }

            $blocoAssinatura .= '<div style="text-align: center; font-size: 11px; color: #333; line-height: 1.6;">';
            $blocoAssinatura .= '<strong>' . htmlspecialchars($nomeCompleto) . '</strong><br>';
            if (!empty($adminCpf)) {
                $blocoAssinatura .= 'CPF: ' . htmlspecialchars($adminCpf) . '<br>';
            }
            $blocoAssinatura .= htmlspecialchars($adminCargo) . '<br>';
            if (!empty($matriculaPortaria)) {
                $blocoAssinatura .= htmlspecialchars($matriculaPortaria) . '<br>';
            }
            $blocoAssinatura .= htmlspecialchars(date('d/m/Y \à\s H:i'));
            $blocoAssinatura .= '</div>';

            $blocoAssinatura .= '<div style="text-align: center; margin-top: 15px;">';
            $blocoAssinatura .= '<img src="' . $qrCodeDataUri . '" style="width: 80px; height: 80px;" /><br>';
            $blocoAssinatura .= '<small style="font-size: 8px; color: #666;">Documento assinado digitalmente<br>ID: ' . substr($resultadoAssinatura['documento_id'], 0, 16) . '...<br>';
            $blocoAssinatura .= '<a href="' . htmlspecialchars($urlVerificacao) . '" style="color: #0066cc;">Verificar autenticidade</a></small>';
            $blocoAssinatura .= '</div></div>';

            $html = str_replace('</body>', $blocoAssinatura . '</body>', $html);
            if (strpos($html, '</body>') === false) {
                $html .= $blocoAssinatura;
            }

            $parecerService->converterHtmlParaPdf($html, $resultadoPreliminar['caminho']);

            $hashFinal = hash_file('sha256', $resultadoPreliminar['caminho']);
            $assinaturaFinal = $assinaturaService->assinarHash($hashFinal);

            // Salvar HTML formatado nos metadados JSON
            $htmlOriginal = $input['html'] ?? '';
            $caminhoJson = dirname($resultadoPreliminar['caminho']) . '/' . pathinfo($resultadoPreliminar['nome'], PATHINFO_FILENAME) . '.json';
            $metadados = [
                'documento_id' => $resultadoAssinatura['documento_id'],
                'requerimento_id' => $requerimento_id,
                'template' => $template,
                'html_completo' => $htmlOriginal, // HTML formatado do TinyMCE antes de adicionar assinatura
                'html_com_assinatura' => $html, // HTML final com assinatura
                'dados_assinatura' => [
                    'assinante_id' => $_SESSION['admin_id'],
                    'assinante_nome' => $nomeCompleto,
                    'assinante_nome_completo' => $nomeCompleto,
                    'assinante_cpf' => $adminCpf ?? '',
                    'assinante_cargo' => $adminCargo ?? 'Administrador',
                    'assinante_email' => $emailAdmin,
                    'assinante_matricula_portaria' => $matriculaPortaria,
                    'tipo_assinatura' => $tipoAssinatura,
                    'assinatura_visual' => is_string($assinatura) ? $assinatura : json_encode($assinatura),
                    'data_assinatura' => date('c'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
                ],
                'hash_documento' => $hashFinal,
                'assinatura_criptografada' => $assinaturaFinal,
                'url_verificacao' => $urlVerificacao,
                'caminho_html' => 'pareceres/' . $requerimento_id . '/' . $resultadoPreliminar['nome'],
                'caminho_json' => 'pareceres/' . $requerimento_id . '/' . pathinfo($resultadoPreliminar['nome'], PATHINFO_FILENAME) . '.json',
                'data_criacao' => date('c'),
                'admin_id' => $_SESSION['admin_id']
            ];
            file_put_contents($caminhoJson, json_encode($metadados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $stmt = $pdo->prepare("
                UPDATE assinaturas_digitais
                SET hash_documento = ?, assinatura_criptografada = ?
                WHERE documento_id = ?
            ");
            $stmt->execute([$hashFinal, $assinaturaFinal, $resultadoAssinatura['documento_id']]);

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'],
                $requerimento_id,
                "Gerou e assinou digitalmente parecer técnico (ID: {$resultadoAssinatura['documento_id']}) usando template: {$template}"
            ]);

            echo json_encode([
                'success' => true,
                'arquivo' => $resultadoPreliminar['nome'],
                'caminho' => $resultadoPreliminar['caminho_relativo'],
                'documento_id' => $resultadoAssinatura['documento_id'],
                'hash' => $hashFinal,
                'url_verificacao' => $urlVerificacao,
                'mensagem' => 'Parecer assinado digitalmente com sucesso!'
            ]);
            break;

        case 'atualizar_posicao_assinatura':
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);
            $nome_arquivo = $input['nome_arquivo'] ?? '';
            $posicaoX = floatval($input['posicao_x'] ?? 0);
            $posicaoY = floatval($input['posicao_y'] ?? 0);

            if ($requerimento_id <= 0 || empty($nome_arquivo)) {
                throw new Exception('Parâmetros inválidos');
            }

            $pastaRequerimento = dirname(__DIR__) . '/uploads/pareceres/' . $requerimento_id . '/';
            $nomeBase = pathinfo($nome_arquivo, PATHINFO_FILENAME);
            $caminhoJson = $pastaRequerimento . $nomeBase . '.json';

            if (!file_exists($caminhoJson)) {
                throw new Exception('Arquivo de metadados não encontrado');
            }

            $metadados = json_decode(file_get_contents($caminhoJson), true);
            if (!$metadados) {
                throw new Exception('Erro ao ler metadados');
            }

            // Atualizar posição
            $metadados['posicao_assinatura'] = [
                'x' => $posicaoX,
                'y' => $posicaoY
            ];

            if (file_put_contents($caminhoJson, json_encode($metadados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo JSON']);
            }
            break;

        case 'gerar_pdf_com_assinatura_posicionada':
            require_once '../includes/assinatura_digital_service.php';
            require_once '../includes/qrcode_service.php';

            $html = $input['html'] ?? '';
            $template = $input['template'] ?? '';
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);
            $assinatura = $input['assinatura'] ?? null;
            $tipoAssinatura = $input['tipo_assinatura'] ?? '';
            $adminNome = $input['admin_nome'] ?? '';
            $adminCpf = $input['admin_cpf'] ?? '';
            $adminCargo = $input['admin_cargo'] ?? '';
            $dataAssinatura = $input['data_assinatura'] ?? '';
            $posicaoX = floatval($input['posicao_x'] ?? 0.7);
            $posicaoY = floatval($input['posicao_y'] ?? 0.85);

            // VERIFICAÇÃO DE SEGURANÇA: Sessão de 3h
            if (!isset($_SESSION['assinatura_auth_valid_until']) || time() > $_SESSION['assinatura_auth_valid_until']) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Sessão de assinatura expirada. Por favor, realize a verificação novamente.',
                    'code' => 'SESSION_EXPIRED'
                ]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT nome, nome_completo, email, cpf, cargo, matricula_portaria FROM administradores WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            $nomeCompleto = $adminData['nome_completo'] ?? $adminNome;
            $emailAdmin = $adminData['email'] ?? '';
            $matriculaPortaria = $adminData['matricula_portaria'] ?? '';
            $adminCpf = $adminData['cpf'] ?? $adminCpf;
            $adminCargo = $adminData['cargo'] ?? $adminCargo;

            error_log("=== HANDLER: gerar_pdf_com_assinatura_posicionada ===");
            error_log("Template: " . $template);
            error_log("Requerimento ID: " . $requerimento_id);
            error_log("HTML recebido (primeiros 500 chars): " . substr($html, 0, 500));
            error_log("Posição X: " . $posicaoX . ", Y: " . $posicaoY);

            if (empty($html) || empty($template) || $requerimento_id <= 0) {
                error_log("ERRO: Parâmetros inválidos");
                throw new Exception('Parâmetros inválidos');
            }

            $parecerService = new ParecerService();

            $documentoId = bin2hex(random_bytes(32));

            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $scriptDir = dirname($scriptName);
            $basePath = '';
            if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') {
                $basePath = rtrim($scriptDir, '/\\');
                if (strpos($basePath, '/admin') !== false) {
                    $basePath = str_replace('/admin', '', $basePath);
                }
                if (strpos($basePath, '\\admin') !== false) {
                    $basePath = str_replace('\\admin', '', $basePath);
                }
                $basePath = rtrim($basePath, '/\\');
            }
            $urlVerificacao = $protocolo . '://' . $host . $basePath . '/consultar/verificar.php?id=' . $documentoId;

            $qrCodeDataUri = QRCodeService::gerarQRCode($urlVerificacao);

            $posicaoXPercent = ($posicaoX * 100) . '%';
            $posicaoYPercent = ($posicaoY * 100) . '%';

            $ehTemplateA4 = strpos($template, 'template_oficial_a4') !== false || strpos($template, 'licenca_previa_projeto') !== false || strpos($template, 'parecer_tecnico') !== false;

            // Para templates A4, reconstruir estrutura se necessário (HTML do TinyMCE pode não ter estrutura completa)
            if ($ehTemplateA4) {
                $parser = new DOMDocument();
                libxml_use_internal_errors(true);
                @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
                libxml_clear_errors();

                // Verificar se já tem estrutura #documento
                $documentoDiv = $parser->getElementById('documento');
                if (!$documentoDiv) {
                    // Reconstruir estrutura A4 com conteúdo do TinyMCE
                    $templatePath = $parecerService->carregarTemplate($template);
                    $templateOriginal = file_get_contents($templatePath);

                    // Extrair imagem de fundo do template original
                    $templateParser = new DOMDocument();
                    @$templateParser->loadHTML('<?xml encoding="UTF-8">' . $templateOriginal);
                    $imgFundo = $templateParser->getElementById('fundo-imagem');
                    $imgSrc = '';
                    if ($imgFundo && $imgFundo->getAttribute('src')) {
                        $imgSrc = $imgFundo->getAttribute('src');
                    }

                    // Criar estrutura completa com conteúdo do TinyMCE
                    $htmlConteudo = $html; // Salvar conteúdo original do TinyMCE
                    $htmlReconstruido = '<div id="documento">';
                    if ($imgSrc) {
                        $htmlReconstruido .= '<img id="fundo-imagem" src="' . htmlspecialchars($imgSrc) . '" alt="Fundo A4" />';
                    }
                    $htmlReconstruido .= '<div id="conteudo" contenteditable="true">' . $htmlConteudo . '</div>';
                    $htmlReconstruido .= '</div>';
                    $html = $htmlReconstruido;

                    // Recarregar parser com HTML reconstruído
                    @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
                    libxml_clear_errors();
                    $documentoDiv = $parser->getElementById('documento');
                }
            }

            if ($ehTemplateA4) {
                if (!isset($parser)) {
                    $parser = new DOMDocument();
                    libxml_use_internal_errors(true);
                    @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
                    libxml_clear_errors();
                }

                $documentoDiv = $parser->getElementById('documento');
                if (!$documentoDiv) {
                    $imagePath = dirname(__DIR__) . '/assets/doc/images/image1.png';
                    $imgSrc = '';
                    if (file_exists($imagePath)) {
                        $imageData = file_get_contents($imagePath);
                        $imageInfo = @getimagesize($imagePath);
                        if ($imageInfo) {
                            $mimeType = $imageInfo['mime'];
                            $base64 = base64_encode($imageData);
                            $imgSrc = 'data:' . $mimeType . ';base64,' . $base64;
                        }
                    }
                    $html = '<div id="documento" style="position: relative; width: 210mm; height: 297mm;"><img id="fundo-imagem" src="' . $imgSrc . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;" /><div id="conteudo" style="position: absolute; top: 150px; left: 60px; width: calc(100% - 120px); z-index: 2;">' . $html . '</div></div>';
                    @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
                    $documentoDiv = $parser->getElementById('documento');
                }

                if ($documentoDiv) {
                    $imgFundo = $parser->getElementById('fundo-imagem');
                    if (!$imgFundo || empty($imgFundo->getAttribute('src'))) {
                        $imagePath = dirname(__DIR__) . '/assets/doc/images/image1.png';
                        if (file_exists($imagePath)) {
                            $imageData = file_get_contents($imagePath);
                            $imageInfo = @getimagesize($imagePath);
                            if ($imageInfo) {
                                $mimeType = $imageInfo['mime'];
                                $base64 = base64_encode($imageData);
                                $imgSrc = 'data:' . $mimeType . ';base64,' . $base64;
                                if ($imgFundo) {
                                    $imgFundo->setAttribute('src', $imgSrc);
                                } else {
                                    $imgFundo = $parser->createElement('img');
                                    $imgFundo->setAttribute('id', 'fundo-imagem');
                                    $imgFundo->setAttribute('src', $imgSrc);
                                    $imgFundo->setAttribute('style', 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;');
                                    $documentoDiv->insertBefore($imgFundo, $documentoDiv->firstChild);
                                }
                            }
                        }
                    }

                    $blocoAssinatura = $parser->createElement('div');
                    $blocoAssinatura->setAttribute('id', 'area-assinatura');
                    $blocoAssinatura->setAttribute('style', 'position: relative; display: flex; align-items: center; gap: 15px; background: transparent; padding: 10px; z-index: 1000;');

                    $imgQr = $parser->createElement('img');
                    $imgQr->setAttribute('src', $qrCodeDataUri);
                    $imgQr->setAttribute('style', 'width: 60px; height: 60px; flex-shrink: 0;');
                    $blocoAssinatura->appendChild($imgQr);

                    $divDados = $parser->createElement('div');
                    $divDados->setAttribute('class', 'dados-assinante');
                    $divDados->setAttribute('style', 'font-size: 12px; text-align: left;');

                    $strong = $parser->createElement('strong', htmlspecialchars($adminNome));
                    $divDados->appendChild($strong);
                    $divDados->appendChild($parser->createElement('br'));

                    $spanCargo = $parser->createTextNode(htmlspecialchars($adminCargo));
                    $divDados->appendChild($spanCargo);
                    $divDados->appendChild($parser->createElement('br'));

                    if (!empty($adminCpf)) {
                        $spanCpf = $parser->createTextNode('CPF: ' . htmlspecialchars($adminCpf));
                        $divDados->appendChild($spanCpf);
                        $divDados->appendChild($parser->createElement('br'));
                    }

                    $spanData = $parser->createTextNode(htmlspecialchars($dataAssinatura));
                    $divDados->appendChild($spanData);
                    $divDados->appendChild($parser->createElement('br'));

                    $linkVerificacao = $parser->createElement('a', 'Verificar Autenticidade');
                    $linkVerificacao->setAttribute('href', $urlVerificacao);
                    $linkVerificacao->setAttribute('target', '_blank');
                    $linkVerificacao->setAttribute('style', 'font-size: 10px; color: #0066cc; text-decoration: underline;');
                    $divDados->appendChild($linkVerificacao);

                    $blocoAssinatura->appendChild($divDados);
                    $documentoDiv->appendChild($blocoAssinatura);

                    $html = $parser->saveHTML();
                } else {
                    $blocoAssinatura = '<div id="area-assinatura" style="position: relative; display: flex; align-items: center; gap: 15px; background: transparent; padding: 10px; z-index: 1000;">';
                    $blocoAssinatura .= '<img src="' . $qrCodeDataUri . '" style="width: 60px; height: 60px; flex-shrink: 0;" />';
                    $blocoAssinatura .= '<div style="font-size: 12px; text-align: left;">';
                    $blocoAssinatura .= '<strong>' . htmlspecialchars($adminNome) . '</strong><br>';
                    $blocoAssinatura .= htmlspecialchars($adminCargo) . '<br>';
                    if (!empty($adminCpf)) {
                        $blocoAssinatura .= 'CPF: ' . htmlspecialchars($adminCpf) . '<br>';
                    }
                    $blocoAssinatura .= htmlspecialchars($dataAssinatura);
                    $blocoAssinatura .= '<br>';
                    $blocoAssinatura .= '<a href="' . htmlspecialchars($urlVerificacao) . '" target="_blank" style="font-size: 10px; color: #0066cc; text-decoration: underline;">Verificar Autenticidade</a>';
                    $blocoAssinatura .= '</div>';
                    $blocoAssinatura .= '</div>';
                    $html = str_replace('</body>', $blocoAssinatura . '</body>', $html);
                    if (strpos($html, '</body>') === false) {
                        $html .= $blocoAssinatura;
                    }
                }
            } else {
                $blocoAssinatura = '<div id="area-assinatura" style="position: relative; display: flex; align-items: center; gap: 15px; background: transparent; padding: 10px; z-index: 1000;">';
                $blocoAssinatura .= '<img src="' . $qrCodeDataUri . '" style="width: 60px; height: 60px; flex-shrink: 0;" />';
                $blocoAssinatura .= '<div style="font-size: 12px; text-align: left;">';
                $blocoAssinatura .= '<strong>' . htmlspecialchars($adminNome) . '</strong><br>';
                $blocoAssinatura .= htmlspecialchars($adminCargo) . '<br>';
                if (!empty($adminCpf)) {
                    $blocoAssinatura .= 'CPF: ' . htmlspecialchars($adminCpf) . '<br>';
                }
                $blocoAssinatura .= htmlspecialchars($dataAssinatura);
                $blocoAssinatura .= '<br>';
                $blocoAssinatura .= '<a href="' . htmlspecialchars($urlVerificacao) . '" target="_blank" style="font-size: 10px; color: #0066cc; text-decoration: underline;">Verificar Autenticidade</a>';
                $blocoAssinatura .= '</div>';
                $blocoAssinatura .= '</div>';

                $parser = new DOMDocument();
                @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
                $body = $parser->getElementsByTagName('body')->item(0);

                if (!$body) {
                    $html = '<body>' . $html . '</body>';
                    @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
                    $body = $parser->getElementsByTagName('body')->item(0);
                }

                if ($body) {
                    $fragment = $parser->createDocumentFragment();
                    $fragment->appendXML($blocoAssinatura);
                    $body->appendChild($fragment);
                    $html = $parser->saveHTML();
                } else {
                    $html = str_replace('</body>', $blocoAssinatura . '</body>', $html);
                    if (strpos($html, '</body>') === false) {
                        $html .= $blocoAssinatura;
                    }
                }
            }

            $pastaRequerimento = dirname(__DIR__) . '/uploads/pareceres/' . $requerimento_id . '/';

            if (!is_dir($pastaRequerimento)) {
                mkdir($pastaRequerimento, 0755, true);
            }

            $timestamp = date('YmdHis');
            $nomeBase = 'parecer_' . pathinfo($template, PATHINFO_FILENAME) . '_' . $timestamp;
            $nomeArquivoHtml = $nomeBase . '.html';
            $nomeArquivoJson = $nomeBase . '.json';

            $caminhoHtml = $pastaRequerimento . $nomeArquivoHtml;
            $caminhoJson = $pastaRequerimento . $nomeArquivoJson;

            if (stripos($html, '<!DOCTYPE') === false) {
                if (stripos($html, '<html') === false) {
                    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
                } else {
                    if (stripos($html, '<head') === false && stripos($html, '<html') !== false) {
                        $html = preg_replace('/<html[^>]*>/i', '$0<head><meta charset="UTF-8"></head>', $html);
                    }
                    if (stripos($html, '<body') === false && stripos($html, '</head>') !== false) {
                        $html = preg_replace('/<\/head>/i', '</head><body>', $html);
                        if (stripos($html, '</html>') === false) {
                            $html .= '</body></html>';
                        }
                    }
                }
            }

            file_put_contents($caminhoHtml, $html);

            $hashFinal = hash_file('sha256', $caminhoHtml);
            $assinaturaService = new AssinaturaDigitalService($pdo);
            $assinaturaFinal = $assinaturaService->assinarHash($hashFinal);

            // Salvar HTML formatado do TinyMCE nos metadados (antes de processar assinatura)
            $htmlOriginalTinyMCE = $input['html'] ?? $html; // HTML original do TinyMCE
            $jsonCompleto = [
                'documento_id' => $documentoId,
                'requerimento_id' => $requerimento_id,
                'template' => $template,
                'html_completo' => $htmlOriginalTinyMCE, // HTML formatado do TinyMCE antes de adicionar assinatura
                'html_com_assinatura' => $html, // HTML final com assinatura
                'dados_assinatura' => [
                    'assinante_id' => $_SESSION['admin_id'],
                    'assinante_nome' => $nomeCompleto,
                    'assinante_nome_completo' => $nomeCompleto,
                    'assinante_cpf' => $adminCpf ?? '',
                    'assinante_cargo' => $adminCargo ?? 'Administrador',
                    'assinante_email' => $emailAdmin,
                    'assinante_matricula_portaria' => $matriculaPortaria,
                    'tipo_assinatura' => $tipoAssinatura,
                    'assinatura_visual' => is_string($assinatura) ? $assinatura : json_encode($assinatura),
                    'data_assinatura' => date('c'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
                ],
                'posicao_assinatura' => [
                    'x' => $posicaoX,
                    'y' => $posicaoY
                ],
                'hash_documento' => $hashFinal,
                'assinatura_criptografada' => $assinaturaFinal,
                'url_verificacao' => $urlVerificacao,
                'caminho_html' => 'pareceres/' . $requerimento_id . '/' . $nomeArquivoHtml,
                'caminho_json' => 'pareceres/' . $requerimento_id . '/' . $nomeArquivoJson,
                'data_criacao' => date('c'),
                'admin_id' => $_SESSION['admin_id']
            ];

            file_put_contents($caminhoJson, json_encode($jsonCompleto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $stmt = $pdo->prepare("
                INSERT INTO assinaturas_digitais (
                    documento_id, requerimento_id, tipo_documento, nome_arquivo,
                    caminho_arquivo, hash_documento, assinante_id, assinante_nome,
                    assinante_cpf, assinante_cargo, tipo_assinatura, assinatura_visual,
                    assinatura_criptografada, timestamp_assinatura, ip_assinante
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");

            $stmt->execute([
                $documentoId,
                $requerimento_id,
                'parecer',
                $nomeArquivoHtml,
                $caminhoHtml,
                $hashFinal,
                $_SESSION['admin_id'],
                $nomeCompleto,
                $adminCpf ?? null,
                $adminCargo ?? 'Administrador',
                $tipoAssinatura,
                is_string($assinatura) ? $assinatura : json_encode($assinatura),
                $assinaturaFinal,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'],
                $requerimento_id,
                "Gerou e assinou digitalmente parecer técnico (ID: {$documentoId}) usando template: {$template}"
            ]);

            $urlViewer = 'parecer_viewer.php?id=' . $documentoId;

            echo json_encode([
                'success' => true,
                'arquivo' => $nomeArquivoHtml,
                'caminho' => 'pareceres/' . $requerimento_id . '/' . $nomeArquivoHtml,
                'documento_id' => $documentoId,
                'hash' => $hashFinal,
                'url_verificacao' => $urlVerificacao,
                'url_viewer' => $urlViewer,
                'mensagem' => 'Parecer assinado digitalmente com sucesso!'
            ]);
            break;

        case 'excluir_documento_assinado':
            $documento_id = $input['documento_id'] ?? '';
            $permanente = (bool)($input['permanente'] ?? false);

            if (empty($documento_id)) {
                throw new Exception('ID do documento não informado');
            }

            // Buscar dados do documento para saber o caminho do arquivo
            $stmt = $pdo->prepare("SELECT caminho_arquivo, requerimento_id FROM assinaturas_digitais WHERE documento_id = ?");
            $stmt->execute([$documento_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                // Se não está no banco, talvez já tenha sido excluído
                echo json_encode(['success' => true]); 
                exit;
            }

            $caminhoArquivo = $doc['caminho_arquivo'];
            $requerimento_id = $doc['requerimento_id'];

            // Se for permanente, apagar o arquivo físico e o JSON de metadados
            if ($permanente) {
                if (!empty($caminhoArquivo) && file_exists($caminhoArquivo)) {
                    @unlink($caminhoArquivo);
                }
                
                // Tenta apagar o arquivo JSON também
                $caminhoJson = str_replace('.html', '.json', $caminhoArquivo);
                if (!empty($caminhoJson) && file_exists($caminhoJson)) {
                    @unlink($caminhoJson);
                }
            }

            // Remover do banco de dados
            $stmt = $pdo->prepare("DELETE FROM assinaturas_digitais WHERE documento_id = ?");
            $success = $stmt->execute([$documento_id]);

            if ($success) {
                // Registrar no histórico
                $acaoDesc = $permanente ? "Excluiu permanentemente o documento assinado (ID: $documento_id)" : "Removeu da listagem o documento assinado (ID: $documento_id)";
                $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                $stmt->execute([
                    $_SESSION['admin_id'],
                    $requerimento_id,
                    $acaoDesc
                ]);
            }

            echo json_encode([
                'success' => $success,
                'mensagem' => $success ? 'Excluído com sucesso!' : 'Erro ao realizar a exclusão'
            ]);
            break;

        default:
            throw new Exception('Ação não reconhecida');
    }

} catch (Exception $e) {
    error_log("ERRO FATAL no parecer_handler: " . $e->getMessage());
    error_log("Trace completo: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ]);
}
