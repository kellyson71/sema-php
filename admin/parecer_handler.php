<?php
require_once 'conexao.php';
require_once '../includes/parecer_service.php';
require_once '../includes/email_service.php';
require_once '../includes/functions.php';


verificaLogin();

header('Content-Type: application/json');

try {
    // Aceita JSON no body, URLSearchParams no body ($_POST) ou query string ($_GET)
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        // Frontend enviou application/x-www-form-urlencoded (URLSearchParams)
        $input = $_POST;
    }
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
        case 'validar_codigo_assinatura':
            // Estas ações foram descontinuadas e movidas para o fluxo principal de Login do sistema.
            echo json_encode(['success' => false, 'error' => 'Ação descontinuada. Verificações de segurança integradas ao Login.']);
            break;

        case 'listar_templates':
            $requerimento_id = (int)($input['requerimento_id'] ?? $_GET['requerimento_id'] ?? 0);

            // Função auxiliar: extrair texto de prévia do HTML do template
            $extrairPreview = function($caminhoHtml) {
                if (!file_exists($caminhoHtml)) return '';
                $html = file_get_contents($caminhoHtml);
                // Pegar só o conteúdo da div #conteudo ou do body
                if (preg_match('/<div[^>]+id=["\']conteudo["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
                    $txt = strip_tags($m[1]);
                } else {
                    $txt = strip_tags($html);
                }
                $txt = preg_replace('/\s+/', ' ', trim($txt));
                // Limpar placeholders {{variavel}}
                $txt = preg_replace('/\{\{[^}]+\}\}/', '…', $txt);
                return mb_substr($txt, 0, 220);
            };

            // Mapa de descrições por slug do nome
            $mapaDescricoes = [
                'em_branco'                          => 'Documento em branco para redação livre no editor.',
                'parecer_tecnico_alvara_construcao'  => 'Parecer técnico para Alvará de Construção com fundamentação legal (Lei 2117/2025 e NBR 12721).',
                'parecer_tecnico_alvara_construcao_ambiental' => 'Parecer técnico ambiental complementar ao alvará de construção.',
                'parecer_tecnico_desmembramento'     => 'Parecer técnico para processo de desmembramento de lote urbano.',
                'parecer_tecnico_desmembramento_ambiental' => 'Análise ambiental para desmembramento de terreno.',
                'parecer_tecnico_habite_se'          => 'Parecer técnico de Habite-se para edificação concluída.',
                'parecer_tecnico_habite_se_ambiental'=> 'Análise ambiental para emissão do Habite-se.',
                'licenca_previa_projeto'             => 'Licença prévia de projeto com campos obrigatórios e condicionantes.',
                'licenca_atividade_economica'        => 'Viabilidade ambiental para Licença de Atividade Econômica (Lei 311/1972).',
            ];

            // Mapa de ícones por slug
            $mapaIcones = [
                'em_branco'                          => ['icon' => 'fa-file-alt',        'cor' => 'text-secondary', 'badge' => 'Livre'],
                'parecer_tecnico_alvara_construcao'  => ['icon' => 'fa-hard-hat',        'cor' => 'text-warning',   'badge' => 'Construção'],
                'parecer_tecnico_alvara_construcao_ambiental' => ['icon' => 'fa-leaf',   'cor' => 'text-success',   'badge' => 'Ambiental'],
                'parecer_tecnico_desmembramento'     => ['icon' => 'fa-map-marked-alt',  'cor' => 'text-info',      'badge' => 'Desmembramento'],
                'parecer_tecnico_desmembramento_ambiental' => ['icon' => 'fa-leaf',      'cor' => 'text-success',   'badge' => 'Ambiental'],
                'parecer_tecnico_habite_se'          => ['icon' => 'fa-home',            'cor' => 'text-primary',   'badge' => 'Habite-se'],
                'parecer_tecnico_habite_se_ambiental'=> ['icon' => 'fa-leaf',            'cor' => 'text-success',   'badge' => 'Ambiental'],
                'licenca_previa_projeto'             => ['icon' => 'fa-clipboard-check', 'cor' => 'text-primary',   'badge' => 'Licença'],
                'licenca_atividade_economica'        => ['icon' => 'fa-store',           'cor' => 'text-warning',   'badge' => 'Econômico'],
            ];

            // 1. Meus Rascunhos (Banco de Dados)
            $meusRascunhos = [];
            if ($requerimento_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT id, nome, data_atualizacao 
                    FROM parecer_rascunhos 
                    WHERE usuario_id = ? AND requerimento_id = ? 
                    ORDER BY data_atualizacao DESC
                ");
                $stmt->execute([$_SESSION['admin_id'], $requerimento_id]);
                $dbRascunhos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($dbRascunhos as $r) {
                    $meusRascunhos[] = [
                        'id'       => 'db_draft:' . $r['id'],
                        'nome'     => $r['nome'],
                        'data'     => date('d/m/Y H:i', strtotime($r['data_atualizacao'])),
                        'data_ts'  => strtotime($r['data_atualizacao']),
                        'assinante'=> 'Você',
                        'label'    => $r['nome'],
                        'origem'   => 'db'
                    ];
                }
            }

            // 2. Histórico Legado (Arquivos JSON)
            $historicoDocs = [];
            $pastaPareceres = dirname(__DIR__) . '/uploads/pareceres/' . $requerimento_id . '/';

            if (is_dir($pastaPareceres)) {
                $arquivos = glob($pastaPareceres . '*.json');
                foreach ($arquivos as $arquivo) {
                    $dados = json_decode(file_get_contents($arquivo), true);
                    if ($dados) {
                        $nomeArquivo   = basename($arquivo, '.json');
                        $timestamp     = filemtime($arquivo);
                        $dataFmt       = date('d/m/Y H:i', $timestamp);
                        $nomeExibicao  = str_replace(['parecer_', 'rascunho_', 'template_oficial_', 'a4_'], '', $nomeArquivo);
                        $nomeExibicao  = ucwords(str_replace('_', ' ', $nomeExibicao));
                        $nomeExibicao  = preg_replace('/ [0-9]{14}$/', '', $nomeExibicao);

                        $historicoDocs[] = [
                            'id'       => 'draft:' . $nomeArquivo . '.json',
                            'nome'     => $nomeExibicao,
                            'data'     => $dataFmt,
                            'data_ts'  => $timestamp,
                            'assinante'=> $dados['dados_assinatura']['assinante_nome'] ?? '...',
                            'label'    => $nomeExibicao . ' (Arquivo Antigo)',
                            'origem'   => 'file'
                        ];
                    }
                }
            }

            // 3. Unificar e ordenar histórico
            $historicoUnificado = array_merge($meusRascunhos, $historicoDocs);
            usort($historicoUnificado, function($a, $b) { return $b['data_ts'] - $a['data_ts']; });
            $historicoRecente = array_slice($historicoUnificado, 0, 5);

            // 4. Templates Padrão
            $templatesDiretorio = realpath(__DIR__ . '/templates');
            if ($templatesDiretorio) {
                $templatesDiretorio = rtrim($templatesDiretorio, '/') . '/';
            }
            $templates = [];

            // Template em branco (sempre primeiro)
            $templates[] = [
                'nome'          => 'em_branco',
                'tipo'          => 'html',
                'label_amigavel'=> 'Documento em Branco',
                'descricao'     => $mapaDescricoes['em_branco'],
                'icone'         => $mapaIcones['em_branco']['icon'],
                'icone_cor'     => $mapaIcones['em_branco']['cor'],
                'badge'         => $mapaIcones['em_branco']['badge'],
                'preview'       => 'Crie um documento do zero, sem modelo predefinido. O editor abrirá em branco para redação livre.',
                'caminho'       => ''
            ];

            if ($templatesDiretorio && is_dir($templatesDiretorio)) {
                $arquivosHtml = glob($templatesDiretorio . '*.html');
                if ($arquivosHtml) {
                    foreach ($arquivosHtml as $arquivo) {
                        $nomeBase = basename($arquivo, '.html');
                        if ($nomeBase === 'modelo_base') continue;

                        // Slug normalizado (sem espaços) para lookup nos mapas
                        $slug = preg_replace('/\s*-\s*/', '_', $nomeBase); // "nome - ambiental" → "nome_ambiental"
                        $slug = trim($slug, '_');

                        $iconeInfo = $mapaIcones[$slug] ?? $mapaIcones[$nomeBase] ?? ['icon' => 'fa-file-signature', 'cor' => 'text-secondary', 'badge' => 'Parecer'];

                        $templates[] = [
                            'nome'          => $nomeBase, // Nome REAL do arquivo (para carregar o template)
                            'tipo'          => 'html',
                            'label_amigavel'=> ucwords(str_replace(['_', ' - '], [' ', ' | '], $nomeBase)),
                            'descricao'     => $mapaDescricoes[$slug] ?? $mapaDescricoes[$nomeBase] ?? 'Modelo disponível para edição no editor online.',
                            'icone'         => $iconeInfo['icon'],
                            'icone_cor'     => $iconeInfo['cor'],
                            'badge'         => $iconeInfo['badge'],
                            'preview'       => $extrairPreview($arquivo),
                        ];
                    }
                }

                // DOCXs (se houver)
                $arquivosDocx = glob($templatesDiretorio . '*.docx');
                if ($arquivosDocx) {
                    foreach ($arquivosDocx as $arquivo) {
                        $nomeBase = basename($arquivo, '.docx');
                        $templates[] = [
                            'nome'          => $nomeBase,
                            'tipo'          => 'docx',
                            'label_amigavel'=> ucwords(str_replace('_', ' ', $nomeBase)),
                            'descricao'     => 'Modelo no formato Word.',
                            'icone'         => 'fa-file-word',
                            'icone_cor'     => 'text-primary',
                            'badge'         => 'DOCX',
                            'preview'       => '',
                        ];
                    }
                }
            } else {
                error_log('[listar_templates] Diretório de templates não encontrado: ' . __DIR__ . '/templates');
            }

            // Ordenar: em_branco primeiro, demais por nome
            usort($templates, function($a, $b) {
                if ($a['nome'] === 'em_branco') return -1;
                if ($b['nome'] === 'em_branco') return 1;
                return strcmp($a['nome'], $b['nome']);
            });

            echo json_encode([
                'success'         => true,
                'historico_recente' => $historicoRecente,
                'templates'       => $templates
            ]);
            break;

        // Ação removed: salvar_rascunho (agora é automático ao assinar)

        case 'carregar_template':
            $template = $input['template'] ?? '';
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);

            if (empty($template) || $requerimento_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }
            
            // A. Template em Branco
            if ($template === 'em_branco') {
                 echo json_encode([
                    'success' => true,
                    'html' => '', 
                    'is_draft' => false,
                    'nome_rascunho' => 'Novo Parecer',
                    'dados' => [] 
                ]);
                break;
            }

            // B. Verificar se é Rascunho de Banco de Dados
            if (strpos($template, 'db_draft:') === 0) {
                $rascunhoId = (int)substr($template, 9);
                
                $stmt = $pdo->prepare("SELECT conteudo_html, nome FROM parecer_rascunhos WHERE id = ?");
                $stmt->execute([$rascunhoId]);
                $rascunho = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$rascunho) {
                    throw new Exception('Rascunho não encontrado no banco de dados');
                }

                echo json_encode([
                    'success' => true,
                    'html' => $rascunho['conteudo_html'],
                    'is_draft' => true,
                    'nome_rascunho' => $rascunho['nome'],
                    'dados' => [] 
                ]);
                break; 
            }

            // B. Verificar se é um draft (rascunho/documento anterior)
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

            // C. Verificar se é Template Padrão (.html em admin/templates)
            // Se o arquivo existir, deixamos fluir para o preenchimento de dados
            $templatesDiretorio = __DIR__ . '/templates/';
            $caminhoArquivoHtml = $templatesDiretorio . $template . '.html';
            
            // Se não existir e não for DOCX, vai dar erro no ParecerService.
            // Mas vamos deixar o fluxo seguir para buscar os dados do requerimento.

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

            // Tentar carregar template
            $templatePath = '';
            if (file_exists($caminhoArquivoHtml)) {
                $templatePath = $caminhoArquivoHtml;
            } else {
                // Tenta via serviço (pode ser DOCX)
                try {
                    $templatePath = $parecerService->carregarTemplate($template);
                } catch(Exception $e) {
                     // Se falhou e era para ser um arquivo fixo, lança erro claro
                     throw new Exception("Template não encontrado: $template");
                }
            }

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

        case 'salvar_preview':
            $html = $input['html'] ?? '';
            $template = $input['template'] ?? '';

            if (empty($html) || empty($template)) {
                throw new Exception('Parâmetros inválidos');
            }

            $_SESSION['parecer_preview_html'] = $html;
            $_SESSION['parecer_preview_template'] = $template;

            echo json_encode([
                'success' => true
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

            // VERIFICAÇÃO DE SEGURANÇA: Sessão de 8h
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

            registrarHistoricoAssinatura($pdo, [
                'documento_id' => $resultadoAssinatura['documento_id'] ?? null,
                'requerimento_id' => $requerimento_id,
                'admin_id' => $_SESSION['admin_id'] ?? null,
                'evento' => 'assinatura',
                'origem' => $input['origem'] ?? 'tecnico',
                'status' => 'sucesso',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
                'host' => $_SERVER['HTTP_HOST'] ?? null,
                'nome_arquivo' => $resultadoPreliminar['nome'] ?? null,
                'hash_documento' => $hashFinal ?? null
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

            // VERIFICAÇÃO DE SEGURANÇA: Sessão de 8h
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
                    
                    // Tentar achar o template estático primeiro (para evitar erro Template não encontrado)
                    $templatesDiretorio = dirname(__DIR__) . '/assets/doc/';
                    $caminhoArquivoHtml = $templatesDiretorio . $template . '.html';

                    if (strpos($template, 'draft:') === 0 || strpos($template, 'db_draft:') === 0) {
                        // Se for rascunho, usa o template oficial padrão para recuperar o fundo
                        if (strpos($template, 'licenca_previa') !== false) {
                            $templatePath = $templatesDiretorio . 'licenca_previa_projeto.html';
                        } else {
                            $templatePath = $templatesDiretorio . 'template_oficial_a4.html';
                        }
                        
                        if (!file_exists($templatePath)) {
                             // Fallback final - tenta achar qualquer um disponivel
                             $templatePath = $templatesDiretorio . 'template_oficial_a4.html';
                        }
                    } elseif (file_exists($caminhoArquivoHtml)) {
                        $templatePath = $caminhoArquivoHtml; // Achou direto
                    } else {
                         // Fallback para o serviço
                        $templatePath = $parecerService->carregarTemplate($template);
                    }
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

            // AUTO-SAVE: Salvar cópia na tabela parecer_rascunhos
            try {
                // Nome padrão inicial
                $nomeRascunho = 'Parecer Assinado: ' . date('d/m/Y H:i');
                
                // Buscar nome do Requerente para compor o título
                $nomeRequerente = 'Requerente';
                $stmtCheck = $pdo->prepare("SELECT requerente_nome FROM requerimentos WHERE id = ?");
                $stmtCheck->execute([$requerimento_id]);
                $dadosReq = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($dadosReq && !empty($dadosReq['requerente_nome'])) {
                    // Pegar primeiro nome + último (ou apenas primeiro se curto) para não ficar enorme
                    $parts = explode(' ', trim($dadosReq['requerente_nome']));
                    $nomeRequerente = $parts[0];
                    if (count($parts) > 1) {
                         $nomeRequerente .= ' ' . end($parts);
                    }
                }

                if (!empty($template)) {
                     $nomeLimpo = str_replace(['db_draft:', 'draft:'], '', $template);
                     $nomeLimpo = pathinfo($nomeLimpo, PATHINFO_FILENAME);
                     
                     // Formatar nome do template de forma mais legível
                     $nomeTemplate = ucwords(str_replace('_', ' ', $nomeLimpo));
                     // Remover termos redundantes
                     $nomeTemplate = str_ireplace(['parecer tecnico', 'template oficial', 'ambiental'], '', $nomeTemplate);
                     $nomeTemplate = trim($nomeTemplate);
                     if (empty($nomeTemplate)) $nomeTemplate = 'Parecer';

                     // Novo padrão: [Nome Template] - [Nome Requerente] (Assinado)
                     $nomeRascunho = "$nomeTemplate - $nomeRequerente (Assinado)";
                }
                
                $dadosJson = json_encode([
                    'template_origem' => $template, 
                    'assinado' => true, 
                    'documento_id' => $documentoId
                ]);

                // Salvar o HTML FINAL (editado) para reuso
                $htmlParaSalvar = $input['html'] ?? $html;

                $stmtSave = $pdo->prepare("
                    INSERT INTO parecer_rascunhos (usuario_id, requerimento_id, nome, conteudo_html, dados_json, data_criacao, data_atualizacao)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmtSave->execute([$_SESSION['admin_id'], $requerimento_id, $nomeRascunho, $htmlParaSalvar, $dadosJson]);
                
            } catch (Exception $e) {
                error_log("Erro no Auto-Save de rascunho: " . $e->getMessage());
            }

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

            registrarHistoricoAssinatura($pdo, [
                'documento_id' => $documentoId ?? null,
                'requerimento_id' => $requerimento_id,
                'admin_id' => $_SESSION['admin_id'] ?? null,
                'evento' => 'assinatura',
                'origem' => $input['origem'] ?? 'tecnico',
                'status' => 'sucesso',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
                'host' => $_SERVER['HTTP_HOST'] ?? null,
                'nome_arquivo' => $nomeArquivoHtml ?? null,
                'hash_documento' => $hashFinal ?? null
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
