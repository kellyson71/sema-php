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
                'carta_habite_se'                    => 'Carta de Habite-se para edificação concluída (documento final de conclusão de obra).',
                'alvara_de_construcao'               => 'Alvará de Construção com dados do proprietário, responsável técnico e especificação da obra.',
                'alvara_de_desmembramento'           => 'Álvará de Desmembramento com autorização formal e fundamentação na Lei 6.766/1979.',
                'notificacao_fiscal'                 => 'Notificação oficial expedida pela fiscalização.',
                'laudo_relatorio_tecnico'            => 'Laudo ou Relatório Técnico detalhado de vistoria.',
                'comunicados_orientacoes'            => 'Comunicados ou orientações técnicas ao requerente.',
                'auto_de_infracao'                   => 'Auto de infração para documentação de irregularidades.',
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
                'carta_habite_se'                    => ['icon' => 'fa-house-check',     'cor' => 'text-primary',   'badge' => 'Habite-se'],
                'alvara_de_construcao'               => ['icon' => 'fa-hard-hat',        'cor' => 'text-warning',   'badge' => 'Construção'],
                'alvara_de_desmembramento'           => ['icon' => 'fa-map-marked-alt',  'cor' => 'text-info',      'badge' => 'Desmembramento'],
                'notificacao_fiscal'                 => ['icon' => 'fa-exclamation-triangle','cor' => 'text-warning', 'badge' => 'Notificação'],
                'laudo_relatorio_tecnico'            => ['icon' => 'fa-microscope',      'cor' => 'text-info',      'badge' => 'Laudo'],
                'comunicados_orientacoes'            => ['icon' => 'fa-bullhorn',        'cor' => 'text-secondary', 'badge' => 'Comunicado'],
                'auto_de_infracao'                   => ['icon' => 'fa-ban',             'cor' => 'text-danger',    'badge' => 'Auto de Infração'],
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
                            'fiscalizacao'  => in_array($slug, [
                                'alvara_de_construcao',
                                'carta_habite_se',
                                'alvara_de_desmembramento',
                                'parecer_tecnico_alvara_construcao',
                                'parecer_tecnico_alvara_construcao_ambiental',
                                'parecer_tecnico_habite_se',
                                'parecer_tecnico_habite_se_ambiental',
                                'parecer_tecnico_desmembramento',
                                'parecer_tecnico_desmembramento_ambiental',
                            ]),
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

            // Templates prioritários para usuários de fiscalização de obras
            $templatesFiscalizacao = [
                'alvara_de_construcao',
                'carta_habite_se',
                'alvara_de_desmembramento',
                'parecer_tecnico_alvara_construcao',
                'parecer_tecnico_alvara_construcao_ambiental',
                'parecer_tecnico_habite_se',
                'parecer_tecnico_habite_se_ambiental',
                'parecer_tecnico_desmembramento',
                'parecer_tecnico_desmembramento_ambiental',
            ];

            $nivelAdmin = $_SESSION['admin_nivel'] ?? '';
            $isFiscal   = in_array($nivelAdmin, ['fiscal', 'admin', 'admin_geral']);

            // Ordenar: em_branco primeiro; para fiscal, templates de obras em seguida; demais por nome
            usort($templates, function($a, $b) use ($templatesFiscalizacao, $isFiscal) {
                if ($a['nome'] === 'em_branco') return -1;
                if ($b['nome'] === 'em_branco') return 1;

                if ($isFiscal) {
                    $aIsFisc = in_array($a['nome'], $templatesFiscalizacao);
                    $bIsFisc = in_array($b['nome'], $templatesFiscalizacao);
                    if ($aIsFisc && !$bIsFisc) return -1;
                    if (!$aIsFisc && $bIsFisc) return 1;
                    // Dentro do grupo de fiscalização, manter a ordem definida no array
                    if ($aIsFisc && $bIsFisc) {
                        return array_search($a['nome'], $templatesFiscalizacao) <=> array_search($b['nome'], $templatesFiscalizacao);
                    }
                }

                return strcmp($a['nome'], $b['nome']);
            });

            // 5. Templates do usuário (personalizados)
            $userTemplates = [];
            $stmtUt = $pdo->prepare("
                SELECT id, nome, descricao, template_base, data_atualizacao
                FROM user_templates
                WHERE usuario_id = ?
                ORDER BY data_atualizacao DESC
            ");
            $stmtUt->execute([$_SESSION['admin_id']]);
            foreach ($stmtUt->fetchAll(PDO::FETCH_ASSOC) as $ut) {
                $userTemplates[] = [
                    'id'          => $ut['id'],
                    'nome'        => $ut['nome'],
                    'descricao'   => $ut['descricao'] ?: 'Template personalizado.',
                    'template_base' => $ut['template_base'],
                    'data'        => date('d/m/Y H:i', strtotime($ut['data_atualizacao'])),
                ];
            }

            echo json_encode([
                'success'           => true,
                'historico_recente' => $historicoRecente,
                'templates'         => $templates,
                'user_templates'    => $userTemplates,
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

            // A2. Template personalizado do usuário (user_tpl:{id})
            if (strpos($template, 'user_tpl:') === 0) {
                $utId = (int)substr($template, 9);
                $stmtUt = $pdo->prepare("SELECT nome, conteudo_html FROM user_templates WHERE id = ? AND usuario_id = ?");
                $stmtUt->execute([$utId, $_SESSION['admin_id']]);
                $utRow = $stmtUt->fetch(PDO::FETCH_ASSOC);
                if (!$utRow) throw new Exception('Template não encontrado ou sem permissão');

                // Buscar dados do requerimento para preencher highlights
                $stmtR = $pdo->prepare("
                    SELECT r.*,
                           req.nome as requerente_nome, req.cpf_cnpj as requerente_cpf_cnpj,
                           req.telefone as requerente_telefone, req.email as requerente_email,
                           p.nome as proprietario_nome, p.cpf_cnpj as proprietario_cpf_cnpj
                    FROM requerimentos r
                    JOIN requerentes req ON r.requerente_id = req.id
                    LEFT JOIN proprietarios p ON r.proprietario_id = p.id
                    WHERE r.id = ?
                ");
                $stmtR->execute([$requerimento_id]);
                $requerimentoUt = $stmtR->fetch();
                if (!$requerimentoUt) throw new Exception('Requerimento não encontrado');

                $stmtAdmUt = $pdo->prepare("SELECT nome, nome_completo, email, cpf, cargo, matricula_portaria FROM administradores WHERE id = ?");
                $stmtAdmUt->execute([$_SESSION['admin_id']]);
                $adminDataUt = $stmtAdmUt->fetch(PDO::FETCH_ASSOC);

                $dadosUt = $parecerService->preencherDados($requerimentoUt, $adminDataUt);
                $htmlUt  = ParecerService::aplicarHighlights($utRow['conteudo_html'], $dadosUt);

                echo json_encode([
                    'success'       => true,
                    'html'          => $htmlUt,
                    'is_draft'      => false,
                    'nome_rascunho' => $utRow['nome'],
                    'dados'         => $dadosUt,
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

                $htmlRascunho = ParecerService::extrairConteudoTemplate($rascunho['conteudo_html'] ?? '');
                echo json_encode([
                    'success' => true,
                    'html' => $htmlRascunho,
                    'is_draft' => true,
                    'nome_rascunho' => $rascunho['nome'],
                    'dados' => []
                ]);
                break; 
            }

            // B. Verificar se é um draft (rascunho/documento anterior)
            if (strpos($template, 'draft:') === 0) {
                $nomeArquivoDraft = basename(substr($template, 6)); // Remove 'draft:' e previne path traversal
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

                $html = ParecerService::extrairConteudoTemplate($html);
                echo json_encode([
                    'success' => true,
                    'html' => $html,
                    'is_draft' => true,
                    'dados' => [] // Drafts já vêm preenchidos
                ]);
                break; // Sai do switch/case
            }

            // C. Buscar dados do requerimento (necessário para qualquer template)
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

            $stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, email, cpf, cargo, matricula_portaria FROM administradores WHERE id = ?");
            $stmtAdmin->execute([$_SESSION['admin_id']]);
            $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            $dados = $parecerService->preencherDados($requerimento, $adminData);

            // D. Tentar carregar via DocumentBuilder (definições modulares em definitions/)
            require_once __DIR__ . '/templates/engine/DocumentBuilder.php';
            $builder = new DocumentBuilder();

            if ($builder->existeDefinicao($template)) {
                $rawHtml = $builder->render($template);
                $html    = ParecerService::aplicarHighlights($rawHtml, $dados);

                echo json_encode([
                    'success' => true,
                    'html'    => $html,
                    'dados'   => $dados,
                ]);
                break;
            }

            // E. Fallback: Template HTML legado ou DOCX
            $templatesDiretorio = __DIR__ . '/templates/';
            $caminhoArquivoHtml = $templatesDiretorio . $template . '.html';

            $templatePath = '';
            if (file_exists($caminhoArquivoHtml)) {
                $templatePath = $caminhoArquivoHtml;
            } else {
                try {
                    $templatePath = $parecerService->carregarTemplate($template);
                } catch(Exception $e) {
                    throw new Exception("Template não encontrado: $template");
                }
            }

            // Verificar se é DOCX — DOCX não suporta highlights, usa substituição direta
            $extTpl = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
            if ($extTpl === 'docx') {
                $html = $parecerService->substituirVariaveisDocx($templatePath, $dados);
            } else {
                $rawHtml = $parecerService->prepararTemplateParaEditor($templatePath);
                $html    = ParecerService::aplicarHighlights($rawHtml, $dados);
            }

            echo json_encode([
                'success' => true,
                'html'    => $html,
                'dados'   => $dados,
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

        case 'listar_templates_usuario':
            $stmtUt2 = $pdo->prepare("
                SELECT id, nome, descricao, template_base, data_atualizacao
                FROM user_templates
                WHERE usuario_id = ?
                ORDER BY data_atualizacao DESC
            ");
            $stmtUt2->execute([$_SESSION['admin_id']]);
            $listaUt = [];
            foreach ($stmtUt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $listaUt[] = [
                    'id'           => $row['id'],
                    'nome'         => $row['nome'],
                    'descricao'    => $row['descricao'],
                    'template_base'=> $row['template_base'],
                    'data'         => date('d/m/Y H:i', strtotime($row['data_atualizacao'])),
                ];
            }
            echo json_encode(['success' => true, 'templates' => $listaUt]);
            break;

        case 'salvar_template_usuario':
            $utNome      = trim($input['nome'] ?? '');
            $utDesc      = trim($input['descricao'] ?? '');
            $utBase      = trim($input['template_base'] ?? '');
            $utHtmlBruto = $input['conteudo_html'] ?? '';
            $utIdUpdate  = (int)($input['id'] ?? 0);

            if (empty($utHtmlBruto)) throw new Exception('Conteúdo do template não pode ser vazio');

            // Converter spans var-field de volta para {{variavel}}
            $utHtmlTemplate = ParecerService::converterSpansParaVariaveis($utHtmlBruto);

            if ($utIdUpdate > 0) {
                // UPDATE — garante que só o dono edita
                $stmtSave = $pdo->prepare("
                    UPDATE user_templates SET conteudo_html = ?, template_base = ?, data_atualizacao = NOW()
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmtSave->execute([$utHtmlTemplate, $utBase, $utIdUpdate, $_SESSION['admin_id']]);
                if ($stmtSave->rowCount() === 0) throw new Exception('Template não encontrado ou sem permissão');
                echo json_encode(['success' => true, 'id' => $utIdUpdate, 'nome' => '']);
            } else {
                if (empty($utNome)) throw new Exception('Informe um nome para o template');
                $stmtSave = $pdo->prepare("
                    INSERT INTO user_templates (usuario_id, nome, descricao, template_base, conteudo_html)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtSave->execute([$_SESSION['admin_id'], $utNome, $utDesc, $utBase, $utHtmlTemplate]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'nome' => $utNome]);
            }
            break;

        case 'excluir_template_usuario':
            $utIdDel = (int)($input['id'] ?? 0);
            if ($utIdDel <= 0) throw new Exception('ID inválido');
            $stmtDel = $pdo->prepare("DELETE FROM user_templates WHERE id = ? AND usuario_id = ?");
            $stmtDel->execute([$utIdDel, $_SESSION['admin_id']]);
            echo json_encode(['success' => $stmtDel->rowCount() > 0]);
            break;

        default:
            throw new Exception('Ação não reconhecida');
    }

} catch (Exception $e) {
    error_log("ERRO FATAL no parecer_handler: " . $e->getMessage());
    error_log("Trace completo: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
