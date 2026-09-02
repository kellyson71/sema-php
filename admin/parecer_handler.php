<?php
require_once 'conexao.php';
require_once '../includes/parecer_service.php';
require_once '../includes/email_service.php';
require_once '../includes/functions.php';
require_once '../includes/coassinatura_helper.php';

verificaLogin();

header('Content-Type: application/json');

function validarCsrfDocumento(array $input): void
{
    $enviado = (string)($input['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || $enviado === ''
        || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        throw new Exception('Sessão de segurança expirada. Recarregue a página.');
    }
}

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
                // Remover blocos <style> e <script> ANTES do strip_tags
                $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
                $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
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
                'alvara_de_desmembramento'           => 'Alvará de Desmembramento com autorização formal e fundamentação na Lei 6.766/1979.',
                'notificacao_fiscal'                 => 'Notificação oficial expedida pela fiscalização.',
                'laudo_relatorio_tecnico'            => 'Laudo ou Relatório Técnico detalhado de vistoria.',
                'comunicados_orientacoes'            => 'Comunicados ou orientações técnicas ao requerente.',
                'auto_de_infracao'                   => 'Auto de infração para documentação de irregularidades.',
            ];

            // Nomes oficiais e amigáveis dos modelos
            $mapaLabels = [
                'alvara_de_desmembramento'           => 'Alvará de Desmembramento',
                'alvara_de_construcao'               => 'Alvará de Construção',
                'carta_habite_se'                    => 'Carta de Habite-se',
                'licenca_previa_projeto'             => 'Licença Prévia de Projeto',
                'licenca_atividade_economica'        => 'Licença de Atividade Econômica',
                'parecer_tecnico_alvara_construcao'  => 'Parecer Técnico - Alvará de Construção',
                'parecer_tecnico_alvara_construcao_ambiental' => 'Parecer Técnico Ambiental - Construção',
                'parecer_tecnico_desmembramento'     => 'Parecer Técnico - Desmembramento',
                'parecer_tecnico_desmembramento_ambiental' => 'Parecer Técnico Ambiental - Desmembramento',
                'parecer_tecnico_habite_se'          => 'Parecer Técnico - Habite-se',
                'parecer_tecnico_habite_se_ambiental'=> 'Parecer Técnico Ambiental - Habite-se',
                'notificacao_fiscal'                 => 'Notificação Fiscal',
                'laudo_relatorio_tecnico'            => 'Laudo ou Relatório Técnico',
                'comunicados_orientacoes'            => 'Comunicados e Orientações',
                'auto_de_infracao'                   => 'Auto de Infração',
                'em_branco'                          => 'Documento em Branco',
            ];

            // Mapa de ícones por slug
            $mapaIcones = [
                'em_branco'                          => ['icon' => 'fa-file-alt',        'cor' => 'text-secondary', 'badge' => 'Livre'],
                'parecer_tecnico_alvara_construcao'  => ['icon' => 'fa-hard-hat',        'cor' => 'text-warning',   'badge' => 'Construção'],
                'parecer_tecnico_alvara_construcao_ambiental' => ['icon' => 'fa-leaf',   'cor' => 'text-success',   'badge' => 'Ambiental'],
                'parecer_tecnico_desmembramento'     => ['icon' => 'fa-map-marked-alt',  'cor' => 'text-info',      'badge' => 'Desmembramento'],
                'parecer_tecnico_desmembramento_ambiental' => ['icon' => 'fa-leaf',      'cor' => 'text-success',   'badge' => 'Ambiental'],
                'parecer_tecnico_habite_se'          => ['icon' => 'fa-house',            'cor' => 'text-primary',   'badge' => 'Habite-se'],
                'parecer_tecnico_habite_se_ambiental'=> ['icon' => 'fa-leaf',            'cor' => 'text-success',   'badge' => 'Ambiental'],
                'licenca_previa_projeto'             => ['icon' => 'fa-clipboard-check', 'cor' => 'text-primary',   'badge' => 'Licença'],
                'licenca_atividade_economica'        => ['icon' => 'fa-store',           'cor' => 'text-warning',   'badge' => 'Econômico'],
                'carta_habite_se'                    => ['icon' => 'fa-house',           'cor' => 'text-primary',   'badge' => 'Habite-se'],
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

            // 2b. Documentos já assinados deste processo. São a base da
            // retificação: reabrir o texto que virou PDF, corrigir e reemitir
            // mantendo o número. Só entram os que ainda estão vigentes.
            $documentosAssinados = [];
            if ($requerimento_id > 0) {
                // Agrupado por documento_id: co-assinatura grava uma linha por
                // assinante e aqui interessa o documento, não cada assinatura.
                $stmtAss = $pdo->prepare("
                    SELECT ad.documento_id,
                           MIN(ad.tipo_documento)       AS tipo_documento,
                           MIN(ad.assinante_nome)       AS assinante_nome,
                           MIN(ad.timestamp_assinatura) AS timestamp_assinatura,
                           MIN(dn.numero)               AS numero,
                           MIN(dn.ano)                  AS ano
                    FROM assinaturas_digitais ad
                    LEFT JOIN document_numbers dn ON dn.documento_id = ad.documento_id
                    WHERE ad.requerimento_id = ?
                      AND ad.substituido_por_documento_id IS NULL
                    GROUP BY ad.documento_id
                    ORDER BY MIN(ad.timestamp_assinatura) DESC
                ");
                $stmtAss->execute([$requerimento_id]);
                $pastaAssinados = dirname(__DIR__) . '/admin/pareceres/' . $requerimento_id . '/';
                foreach ($stmtAss->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                    // Sem o HTML guardado não há o que reabrir (documentos
                    // assinados antes desta funcionalidade).
                    if (!is_file($pastaAssinados . $doc['documento_id'] . '.html')) continue;
                    $numeroTexto = $doc['numero'] ? ' Nº ' . $doc['numero'] . '/' . $doc['ano'] : '';
                    $documentosAssinados[] = [
                        'id'         => 'assinado:' . $doc['documento_id'],
                        'nome'       => ($mapaLabels[$doc['tipo_documento']] ?? ucwords(str_replace('_', ' ', $doc['tipo_documento']))) . $numeroTexto,
                        'data'       => date('d/m/Y H:i', strtotime($doc['timestamp_assinatura'])),
                        'data_ts'    => strtotime($doc['timestamp_assinatura']),
                        'assinante'  => $doc['assinante_nome'],
                        'label'      => 'Retificar: ' . ($mapaLabels[$doc['tipo_documento']] ?? $doc['tipo_documento']) . $numeroTexto,
                        'origem'     => 'assinado',
                        'numero'     => $doc['numero'] ? $doc['numero'] . '/' . $doc['ano'] : '',
                    ];
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
                            'label_amigavel'=> $mapaLabels[$slug] ?? $mapaLabels[$nomeBase] ?? ucwords(str_replace(['_', ' - '], [' ', ' | '], $nomeBase)),
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
                SELECT id, nome, descricao, icone, template_base, data_atualizacao
                FROM user_templates
                WHERE usuario_id = ?
                ORDER BY data_atualizacao DESC
            ");
            $stmtUt->execute([$_SESSION['admin_id']]);
            foreach ($stmtUt->fetchAll(PDO::FETCH_ASSOC) as $ut) {
                $userTemplates[] = [
                    'id'            => $ut['id'],
                    'nome'          => $ut['nome'],
                    'descricao'     => $ut['descricao'] ?: 'Template personalizado.',
                    'icone'         => $ut['icone'] ?: 'fa-bookmark',
                    'template_base' => $ut['template_base'],
                    'data'          => date('d/m/Y H:i', strtotime($ut['data_atualizacao'])),
                    'tipo'          => 'personalizado',
                ];
            }

            // 6. Favoritos do usuário — templates padrão favoritados
            $stmtFav = $pdo->prepare("SELECT template_nome FROM user_favorites WHERE usuario_id = ?");
            $stmtFav->execute([$_SESSION['admin_id']]);
            $favNomes = array_column($stmtFav->fetchAll(PDO::FETCH_ASSOC), 'template_nome');

            // Adicionar favoritos ao início da lista (com flag para o frontend)
            foreach ($templates as &$t) {
                $t['favoritado'] = in_array($t['nome'], $favNomes);
            }
            unset($t);

            // Montar lista de cards de favoritos para "Meus Modelos"
            $favTemplates = [];
            foreach ($templates as $t) {
                if ($t['favoritado']) {
                    $favTemplates[] = array_merge($t, ['tipo' => 'favorito']);
                }
            }
            // Favoritos primeiro, personalizados depois
            $userTemplates = array_merge($favTemplates, $userTemplates);

            // 7. Score de preenchimento: detecta o template com melhor cobertura das variáveis
            if ($requerimento_id > 0 && $templatesDiretorio) {
                $stmtScore = $pdo->prepare("
                    SELECT r.*,
                           req.nome  as requerente_nome,   req.cpf_cnpj  as requerente_cpf_cnpj,
                           req.telefone as requerente_telefone, req.email as requerente_email,
                           p.nome    as proprietario_nome, p.cpf_cnpj   as proprietario_cpf_cnpj
                    FROM requerimentos r
                    JOIN requerentes  req ON r.requerente_id = req.id
                    LEFT JOIN proprietarios p  ON r.proprietario_id = p.id
                    WHERE r.id = ?
                ");
                $stmtScore->execute([$requerimento_id]);
                $reqDados = $stmtScore->fetch(PDO::FETCH_ASSOC);

                if ($reqDados) {
                    $camposSempre  = ['protocolo', 'data_atual', 'numero_documento_ano', 'ano_atual', 'tipo_alvara'];
                    $mapaVarCampo  = [
                        'nome_requerente'                => ['requerente_nome'],
                        'cpf_cnpj_requerente'            => ['requerente_cpf_cnpj'],
                        'email_requerente'               => ['requerente_email'],
                        'telefone_requerente'            => ['requerente_telefone'],
                        'endereco_objetivo'              => ['endereco_objetivo'],
                        'nome_proprietario'              => ['proprietario_nome'],
                        'cpf_cnpj_proprietario'          => ['proprietario_cpf_cnpj'],
                        'responsavel_tecnico_nome'       => ['responsavel_tecnico_nome'],
                        'responsavel_tecnico_registro'   => ['responsavel_tecnico_registro'],
                        'responsavel_tecnico_numero'     => ['responsavel_tecnico_numero', 'responsavel_tecnico_registro'],
                        'responsavel_tecnico_tipo_documento' => ['responsavel_tecnico_tipo_documento'],
                        'especificacao'                  => ['especificacao'],
                        'art_numero'                     => ['responsavel_tecnico_numero', 'responsavel_tecnico_registro'],
                        'area_construida'                => ['area_construida', 'area_construcao', 'area_lote'],
                        'area'                           => ['area_construida', 'area_construcao', 'area_lote'],
                        'detalhes_imovel'                => ['especificacao'],
                        'area_lote'                      => ['area_lote'],
                        'nome_interessado'               => ['proprietario_nome', 'requerente_nome'],
                        'cpf_interessado'                => ['proprietario_cpf_cnpj', 'requerente_cpf_cnpj'],
                        'atividade'                      => ['atividade', 'especificacao'],
                        'cnae_descricao'                 => ['cnae_descricao', 'especificacao'],
                        'observacoes'                    => ['observacoes'],
                    ];

                    $tipoAlvaraReq = $reqDados['tipo_alvara'] ?? '';
                    $mapaTemplatesRecomendados = [
                        'desmembramento' => [
                            'alvara_de_desmembramento',
                            'parecer_tecnico_desmembramento',
                            'parecer_tecnico_desmembramento_ambiental',
                        ],
                        'construcao' => [
                            'alvara_de_construcao',
                            'parecer_tecnico_alvara_construcao',
                            'parecer_tecnico_alvara_construcao_ambiental',
                        ],
                        'construcao_obras_publicas' => [
                            'alvara_de_construcao',
                            'parecer_tecnico_alvara_construcao',
                            'parecer_tecnico_alvara_construcao_ambiental',
                        ],
                        'habite_se' => [
                            'carta_habite_se',
                            'parecer_tecnico_habite_se',
                            'parecer_tecnico_habite_se_ambiental',
                        ],
                        'habite_se_simples' => [
                            'carta_habite_se',
                            'parecer_tecnico_habite_se',
                            'parecer_tecnico_habite_se_ambiental',
                        ],
                        'habite_se_obras_publicas' => [
                            'carta_habite_se',
                            'parecer_tecnico_habite_se',
                            'parecer_tecnico_habite_se_ambiental',
                        ],
                        'licenca_previa' => [
                            'licenca_previa_projeto',
                            'parecer_tecnico_alvara_construcao_ambiental',
                        ],
                        'licenca_previa_obras' => [
                            'licenca_previa_projeto',
                            'parecer_tecnico_alvara_construcao_ambiental',
                        ],
                        'licenca_previa_ambiental' => [
                            'licenca_previa_projeto',
                            'parecer_tecnico_alvara_construcao_ambiental',
                        ],
                        'licenca_previa_instalacao' => [
                            'licenca_previa_projeto',
                            'parecer_tecnico_alvara_construcao_ambiental',
                        ],
                        'funcionamento' => [
                            'licenca_atividade_economica',
                        ],
                    ];

                    $templatesExatos = $mapaTemplatesRecomendados[$tipoAlvaraReq] ?? [];
                    $templateRecomendadoObj = null;

                    // Primeiro calcular o fill_score base de todos os templates
                    $melhorScore = -1;
                    $melhorIdx   = -1;

                    foreach ($templates as $idx => &$t) {
                        if ($t['nome'] === 'em_branco') {
                            $t['fill_score'] = 0;
                            $t['melhor_match'] = false;
                            $t['recomendado'] = false;
                            continue;
                        }

                        $caminhoHtml = $templatesDiretorio . $t['nome'] . '.html';
                        if (!file_exists($caminhoHtml)) {
                            $t['fill_score'] = 0;
                            $t['melhor_match'] = false;
                            $t['recomendado'] = false;
                            continue;
                        }

                        preg_match_all('/\{\{([^}]+)\}\}/', file_get_contents($caminhoHtml), $m);
                        $varsTemplate = array_unique($m[1]);
                        if (empty($varsTemplate)) {
                            $t['fill_score'] = 0;
                            $t['melhor_match'] = false;
                            $t['recomendado'] = false;
                            continue;
                        }

                        $preenchidas = 0;
                        foreach ($varsTemplate as $var) {
                            if (in_array($var, $camposSempre)) { $preenchidas++; continue; }
                            foreach ($mapaVarCampo[$var] ?? [$var] as $campo) {
                                if (!empty($reqDados[$campo]) && trim($reqDados[$campo]) !== '') {
                                    $preenchidas++; break;
                                }
                            }
                        }

                        $score = $preenchidas / count($varsTemplate);
                        $t['fill_score'] = (int) round($score * 100);

                        if ($score > $melhorScore) {
                            $melhorScore = $score;
                            $melhorIdx = $idx;
                        }
                    }
                    unset($t);

                    // Aplicar recomendação e 100% preenchido aos templates correspondentes ao tipo do requerimento
                    if (!empty($templatesExatos)) {
                        foreach ($templates as &$t) {
                            if (in_array($t['nome'], $templatesExatos, true)) {
                                $t['melhor_match'] = true;
                                $t['recomendado']  = true;
                                $t['fill_score']   = 100;
                                if ($templateRecomendadoObj === null) {
                                    $templateRecomendadoObj = $t;
                                }
                            } else {
                                $t['melhor_match'] = false;
                                $t['recomendado']  = false;
                            }
                        }
                        unset($t);
                    } elseif ($melhorIdx >= 0 && $melhorScore >= 0.5) {
                        $templates[$melhorIdx]['melhor_match'] = true;
                        $templates[$melhorIdx]['recomendado']  = true;
                        $templateRecomendadoObj = $templates[$melhorIdx];
                    }
                }
            }

            echo json_encode([
                'success'              => true,
                'historico_recente'    => $historicoRecente,
                'documentos_assinados' => $documentosAssinados,
                'templates'            => $templates,
                'user_templates'       => $userTemplates,
                'favoritos'            => $favNomes,
                'template_recomendado' => $templateRecomendadoObj,
            ]);
            break;

        case 'salvar_rascunho':
            // Autosave: cada administrador só pode gravar seus próprios
            // rascunhos no requerimento informado.
            $csrfEnviado = (string)($input['csrf_token'] ?? '');
            if (empty($_SESSION['csrf_token']) || !$csrfEnviado
                || !hash_equals($_SESSION['csrf_token'], $csrfEnviado)) {
                throw new Exception('Sessão de segurança expirada. Recarregue a página.');
            }
            $requerimentoId = (int)($input['requerimento_id'] ?? 0);
            $rascunhoId     = (int)($input['rascunho_id'] ?? 0);
            $nome           = trim((string)($input['nome'] ?? 'Documento em edição'));
            $conteudoHtml   = (string)($input['conteudo_html'] ?? '');
            $dadosJson      = $input['dados_json'] ?? null;

            if ($requerimentoId <= 0 || $conteudoHtml === '') {
                throw new Exception('Não há conteúdo suficiente para salvar o rascunho.');
            }
            if (mb_strlen($nome) > 255) $nome = mb_substr($nome, 0, 255);
            if ($dadosJson !== null && !is_string($dadosJson)) {
                $dadosJson = json_encode($dadosJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // O processo precisa existir e o rascunho, quando informado,
            // precisa pertencer ao usuário atual.
            $stmtReq = $pdo->prepare('SELECT id FROM requerimentos WHERE id = ? LIMIT 1');
            $stmtReq->execute([$requerimentoId]);
            if (!$stmtReq->fetchColumn()) throw new Exception('Requerimento não encontrado.');

            $rascunhoAtualExiste = false;
            if ($rascunhoId > 0) {
                $stmtExiste = $pdo->prepare('SELECT id FROM parecer_rascunhos
                    WHERE id = ? AND usuario_id = ? AND requerimento_id = ? LIMIT 1');
                $stmtExiste->execute([$rascunhoId, (int)$_SESSION['admin_id'], $requerimentoId]);
                $rascunhoAtualExiste = (bool)$stmtExiste->fetchColumn();
            }

            if ($rascunhoAtualExiste) {
                $stmt = $pdo->prepare('UPDATE parecer_rascunhos
                    SET nome = ?, conteudo_html = ?, dados_json = ?, data_atualizacao = NOW()
                    WHERE id = ? AND usuario_id = ? AND requerimento_id = ?');
                $stmt->execute([$nome, $conteudoHtml, $dadosJson, $rascunhoId,
                    (int)$_SESSION['admin_id'], $requerimentoId]);
            }

            if (!$rascunhoAtualExiste) {
                $stmt = $pdo->prepare('INSERT INTO parecer_rascunhos
                    (usuario_id, requerimento_id, nome, conteudo_html, dados_json)
                    VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([(int)$_SESSION['admin_id'], $requerimentoId, $nome,
                    $conteudoHtml, $dadosJson]);
                $rascunhoId = (int)$pdo->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'rascunho_id' => $rascunhoId,
                'salvo_em' => date('d/m/Y H:i:s')
            ]);
            break;

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
                $stmtUt = $pdo->prepare("SELECT nome, conteudo_html, template_base FROM user_templates WHERE id = ? AND usuario_id = ?");
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

                $dadosUt = $parecerService->preencherDados(
                    $requerimentoUt,
                    $adminDataUt,
                    $utRow['template_base'] ?: null,
                    $pdo
                );
                $htmlUtBruto = ParecerService::aplicarHighlights($utRow['conteudo_html'], $dadosUt);
                $htmlUt = ParecerService::extrairConteudoTemplate(ParecerService::removerEstilosTemplate($htmlUtBruto));

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

                $htmlRascunhoBruto = $rascunho['conteudo_html'] ?? '';
                $htmlRascunho = ParecerService::extrairConteudoTemplate(ParecerService::removerEstilosTemplate($htmlRascunhoBruto));
                echo json_encode([
                    'success' => true,
                    'html' => $htmlRascunho,
                    'is_draft' => true,
                    'nome_rascunho' => $rascunho['nome'],
                    'dados' => []
                ]);
                break;
            }

            // B0. Retificação: reabre o HTML de um documento já assinado.
            if (strpos($template, 'assinado:') === 0) {
                $documentoAssinadoId = preg_replace('/[^a-f0-9]/i', '', substr($template, 9));
                $stmtAss = $pdo->prepare("
                    SELECT ad.documento_id, ad.tipo_documento, ad.assinante_nome, ad.timestamp_assinatura,
                           ad.substituido_por_documento_id, dn.numero, dn.ano
                    FROM assinaturas_digitais ad
                    LEFT JOIN document_numbers dn ON dn.documento_id = ad.documento_id
                    WHERE ad.documento_id = ? AND ad.requerimento_id = ?
                    LIMIT 1
                ");
                $stmtAss->execute([$documentoAssinadoId, $requerimento_id]);
                $docAssinado = $stmtAss->fetch(PDO::FETCH_ASSOC);
                if (!$docAssinado) throw new Exception('Documento assinado não encontrado neste processo.');
                if (!empty($docAssinado['substituido_por_documento_id'])) {
                    throw new Exception('Esta versão já foi substituída por outra. Abra a versão vigente para retificar.');
                }

                $caminhoHtmlAssinado = dirname(__DIR__) . '/admin/pareceres/' . $requerimento_id . '/' . $documentoAssinadoId . '.html';
                if (!is_file($caminhoHtmlAssinado)) {
                    throw new Exception('O texto deste documento não foi guardado (assinado antes da retificação existir). Gere um documento novo.');
                }

                $htmlAssinado = ParecerService::extrairConteudoTemplate(
                    ParecerService::removerEstilosTemplate((string) file_get_contents($caminhoHtmlAssinado))
                );

                echo json_encode([
                    'success'          => true,
                    'html'             => $htmlAssinado,
                    'is_draft'         => true,
                    'nome_rascunho'    => 'Retificação',
                    'dados'            => [],
                    'retifica'         => [
                        'documento_id' => $documentoAssinadoId,
                        'template'     => $docAssinado['tipo_documento'],
                        'numero'       => $docAssinado['numero'] ? $docAssinado['numero'] . '/' . $docAssinado['ano'] : '',
                        'assinante'    => $docAssinado['assinante_nome'],
                        'assinado_em'  => date('d/m/Y H:i', strtotime($docAssinado['timestamp_assinatura'])),
                    ],
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

                $html = ParecerService::extrairConteudoTemplate(ParecerService::removerEstilosTemplate($html));
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

            $dados = $parecerService->preencherDados($requerimento, $adminData, $template, $pdo);

            // D. Tentar carregar via DocumentBuilder (definições modulares em definitions/)
            require_once __DIR__ . '/templates/engine/DocumentBuilder.php';
            $builder = new DocumentBuilder();

            if ($builder->existeDefinicao($template)) {
                $rawHtml = $builder->render($template);
                $htmlBruto = ParecerService::aplicarHighlights($rawHtml, $dados);
                $html = ParecerService::extrairConteudoTemplate(ParecerService::removerEstilosTemplate($htmlBruto));

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
                $htmlBruto = $parecerService->substituirVariaveisDocx($templatePath, $dados);
            } else {
                $rawHtml = $parecerService->prepararTemplateParaEditor($templatePath);
                $htmlBruto = ParecerService::aplicarHighlights($rawHtml, $dados);
            }

            $html = ParecerService::extrairConteudoTemplate(ParecerService::removerEstilosTemplate($htmlBruto));

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

        case 'listar_pareceres':
            $requerimento_id = (int)($input['requerimento_id'] ?? 0);
            if (!$requerimento_id) throw new Exception('requerimento_id obrigatório');

            // Assinaturas de teste da conta Kellyson (e variações) ficam ocultas para os
            // demais usuários — precaução até a limpeza definitiva desses dados.
            $souContaKellyson = stripos($_SESSION['admin_email'] ?? '', 'kellyson') !== false;
            $filtroKellyson = $souContaKellyson ? "" : "AND ad.assinante_nome NOT LIKE '%kellyson%'";
            $stmtAd = $pdo->prepare("
                SELECT ad.documento_id, ad.nome_arquivo, ad.tipo_documento, ad.assinante_nome,
                       ad.assinante_cargo, ad.assinante_cpf, ad.caminho_arquivo,
                       ad.timestamp_assinatura, ad.substituido_por_documento_id,
                       GROUP_CONCAT(DISTINCT ad2.assinante_id ORDER BY ad2.assinante_id SEPARATOR ',') AS assinantes_ids
                FROM assinaturas_digitais ad
                LEFT JOIN assinaturas_digitais ad2
                    ON (ad2.documento_id = ad.documento_id)
                WHERE ad.requerimento_id = ? $filtroKellyson
                GROUP BY ad.documento_id, ad.nome_arquivo, ad.tipo_documento, ad.assinante_nome,
                         ad.assinante_cargo, ad.assinante_cpf, ad.caminho_arquivo, ad.timestamp_assinatura,
                         ad.substituido_por_documento_id
                ORDER BY ad.timestamp_assinatura DESC
            ");
            $stmtAd->execute([$requerimento_id]);
            $rows = $stmtAd->fetchAll(PDO::FETCH_ASSOC);

            $adminIdSessao = (int) ($_SESSION['admin_id'] ?? 0);
            // A retificação reabre o HTML guardado ao lado do PDF. Documentos
            // assinados antes disso não têm o arquivo e não são retificáveis.
            $pastaHtmlAssinado = __DIR__ . '/pareceres/' . $requerimento_id . '/';
            $pareceres = array_map(function($r) use ($pdo, $adminIdSessao, $pastaHtmlAssinado) {
                $existe = !empty($r['caminho_arquivo']) && file_exists($r['caminho_arquivo']);
                $tamanho = $existe ? filesize($r['caminho_arquivo']) : 0;
                // Converter GROUP_CONCAT string para array de inteiros
                $assinantesIds = [];
                if (!empty($r['assinantes_ids'])) {
                    $assinantesIds = array_map('intval', explode(',', $r['assinantes_ids']));
                }
                $coStatus = statusAssinaturasDocumento($pdo, $r['documento_id']);
                // Verificar se o admin logado tem assinatura pendente neste doc
                $euTenhoPendente = false;
                foreach ($coStatus['pendentes'] as $p) {
                    if (($p['destinatario_id'] ?? 0) === $adminIdSessao) { $euTenhoPendente = true; break; }
                }
                return [
                    'documento_id'      => $r['documento_id'],
                    'arquivo'           => $r['nome_arquivo'],
                    'tipo'              => $r['tipo_documento'] ?? 'parecer',
                    'nome'              => $r['nome_arquivo'],
                    'assinante'         => $r['assinante_nome'],
                    'cargo'             => $r['assinante_cargo'],
                    'cpf'               => $r['assinante_cpf'],
                    'data'              => date('d/m/Y H:i', strtotime($r['timestamp_assinatura'])),
                    'tamanho'           => $tamanho,
                    'apagado'           => !$existe,
                    'assinantes'        => $assinantesIds,
                    'co_assinantes'     => $coStatus['assinantes'],
                    'co_pendentes'      => $coStatus['pendentes'],
                    'co_recusados'      => $coStatus['recusados'],
                    'co_total_assinado' => $coStatus['total_assinado'],
                    'co_total_esperado' => $coStatus['total_esperado'],
                    'co_completo'       => $coStatus['completo'],
                    'co_solicitante_id' => $coStatus['solicitante_id'],
                    'co_eu_pendente'    => $euTenhoPendente,
                    'substituido'       => !empty($r['substituido_por_documento_id']),
                    'pode_retificar'    => empty($r['substituido_por_documento_id'])
                                            && is_file($pastaHtmlAssinado . $r['documento_id'] . '.html'),
                ];
            }, $rows);

            echo json_encode(['success' => true, 'pareceres' => $pareceres]);
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
                SELECT id, nome, descricao, icone, template_base, data_atualizacao
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
                    'icone'        => $row['icone'] ?: 'fa-bookmark',
                    'template_base'=> $row['template_base'],
                    'data'         => date('d/m/Y H:i', strtotime($row['data_atualizacao'])),
                ];
            }
            echo json_encode(['success' => true, 'templates' => $listaUt]);
            break;

        case 'salvar_template_usuario':
            validarCsrfDocumento($input);
            $utNome      = trim($input['nome'] ?? '');
            $utDesc      = trim($input['descricao'] ?? '');
            $utBase      = trim($input['template_base'] ?? '');
            $utIcone     = trim($input['icone'] ?? 'fa-bookmark');
            $utHtmlBruto = $input['conteudo_html'] ?? '';
            $utIdUpdate  = (int)($input['id'] ?? 0);

            if (empty($utHtmlBruto)) throw new Exception('Conteúdo do template não pode ser vazio');

            $utHtmlTemplate = ParecerService::converterSpansParaVariaveis($utHtmlBruto);

            if ($utIdUpdate > 0) {
                // Guarda a versão anterior antes de substituir o conteúdo.
                // O try/catch mantém compatibilidade durante a implantação da
                // migration em instalações que ainda não possuem a tabela.
                try {
                    $stmtAnterior = $pdo->prepare("SELECT nome, descricao, icone, template_base, conteudo_html
                        FROM user_templates WHERE id = ? AND usuario_id = ? LIMIT 1");
                    $stmtAnterior->execute([$utIdUpdate, $_SESSION['admin_id']]);
                    $anterior = $stmtAnterior->fetch(PDO::FETCH_ASSOC);
                    if ($anterior) {
                        $stmtVersao = $pdo->prepare("SELECT COALESCE(MAX(numero_versao), 0) + 1
                            FROM user_template_versions WHERE template_id = ? AND usuario_id = ?");
                        $stmtVersao->execute([$utIdUpdate, $_SESSION['admin_id']]);
                        $numeroVersao = (int)$stmtVersao->fetchColumn();
                        $pdo->prepare("INSERT INTO user_template_versions
                            (template_id, usuario_id, numero_versao, nome, descricao, icone,
                             template_base, conteudo_html)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                                $utIdUpdate, $_SESSION['admin_id'], $numeroVersao,
                                $anterior['nome'], $anterior['descricao'], $anterior['icone'],
                                $anterior['template_base'], $anterior['conteudo_html']
                            ]);
                    }
                } catch (PDOException $versaoError) {
                    error_log('[modelos] histórico de versão indisponível: ' . $versaoError->getMessage());
                }
                $stmtSave = $pdo->prepare("
                    UPDATE user_templates SET conteudo_html = ?, template_base = ?, icone = ?, data_atualizacao = NOW()
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmtSave->execute([$utHtmlTemplate, $utBase, $utIcone, $utIdUpdate, $_SESSION['admin_id']]);
                if ($stmtSave->rowCount() === 0) throw new Exception('Template não encontrado ou sem permissão');
                echo json_encode(['success' => true, 'id' => $utIdUpdate, 'nome' => '']);
            } else {
                if (empty($utNome)) throw new Exception('Informe um nome para o template');
                $stmtSave = $pdo->prepare("
                    INSERT INTO user_templates (usuario_id, nome, descricao, icone, template_base, conteudo_html)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtSave->execute([$_SESSION['admin_id'], $utNome, $utDesc, $utIcone, $utBase, $utHtmlTemplate]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'nome' => $utNome]);
            }
            break;

        case 'excluir_template_usuario':
            validarCsrfDocumento($input);
            $utIdDel = (int)($input['id'] ?? 0);
            if ($utIdDel <= 0) throw new Exception('ID inválido');
            $stmtDel = $pdo->prepare("DELETE FROM user_templates WHERE id = ? AND usuario_id = ?");
            $stmtDel->execute([$utIdDel, $_SESSION['admin_id']]);
            echo json_encode(['success' => $stmtDel->rowCount() > 0]);
            break;

        case 'duplicar_template_usuario':
            validarCsrfDocumento($input);
            $utIdOrigem = (int)($input['id'] ?? 0);
            if ($utIdOrigem <= 0) throw new Exception('ID inválido');
            $stmtOrigem = $pdo->prepare("SELECT nome, descricao, icone, template_base, conteudo_html
                FROM user_templates WHERE id = ? AND usuario_id = ? LIMIT 1");
            $stmtOrigem->execute([$utIdOrigem, $_SESSION['admin_id']]);
            $origem = $stmtOrigem->fetch(PDO::FETCH_ASSOC);
            if (!$origem) throw new Exception('Template não encontrado ou sem permissão');

            $nomeCopia = mb_substr($origem['nome'] . ' (cópia)', 0, 255);
            $stmtCopia = $pdo->prepare("INSERT INTO user_templates
                (usuario_id, nome, descricao, icone, template_base, conteudo_html)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmtCopia->execute([$_SESSION['admin_id'], $nomeCopia, $origem['descricao'],
                $origem['icone'], $origem['template_base'], $origem['conteudo_html']]);
            $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao)
                VALUES (?, NULL, ?)")->execute([$_SESSION['admin_id'], "Duplicou o modelo: {$origem['nome']} → {$nomeCopia}"]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'nome' => $nomeCopia]);
            break;

        case 'listar_versoes_template':
            $templateId = (int)($input['id'] ?? 0);
            if ($templateId <= 0) throw new Exception('ID inválido');
            $stmtVersoes = $pdo->prepare("SELECT v.id, v.numero_versao, v.nome,
                    v.criado_em, a.nome AS autor_nome
                FROM user_template_versions v
                LEFT JOIN administradores a ON a.id = v.usuario_id
                WHERE v.template_id = ? AND v.usuario_id = ?
                ORDER BY v.numero_versao DESC");
            $stmtVersoes->execute([$templateId, $_SESSION['admin_id']]);
            echo json_encode(['success' => true, 'versoes' => $stmtVersoes->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'restaurar_versao_template':
            validarCsrfDocumento($input);
            $templateId = (int)($input['template_id'] ?? 0);
            $versaoId   = (int)($input['versao_id'] ?? 0);
            if ($templateId <= 0 || $versaoId <= 0) throw new Exception('Versão inválida');

            $stmtVersao = $pdo->prepare("SELECT v.conteudo_html, v.template_base, v.icone
                FROM user_template_versions v
                WHERE v.id = ? AND v.template_id = ? AND v.usuario_id = ? LIMIT 1");
            $stmtVersao->execute([$versaoId, $templateId, $_SESSION['admin_id']]);
            $versao = $stmtVersao->fetch(PDO::FETCH_ASSOC);
            if (!$versao) throw new Exception('Versão não encontrada ou sem permissão');

            // Preserva o estado atual antes de restaurar o histórico.
            $stmtAtual = $pdo->prepare("SELECT nome, descricao, icone, template_base, conteudo_html
                FROM user_templates WHERE id = ? AND usuario_id = ? LIMIT 1");
            $stmtAtual->execute([$templateId, $_SESSION['admin_id']]);
            $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
            if (!$atual) throw new Exception('Template não encontrado');
            $stmtProxima = $pdo->prepare("SELECT COALESCE(MAX(numero_versao), 0) + 1
                FROM user_template_versions WHERE template_id = ? AND usuario_id = ?");
            $stmtProxima->execute([$templateId, $_SESSION['admin_id']]);
            $proximaVersao = (int)$stmtProxima->fetchColumn();
            $pdo->prepare("INSERT INTO user_template_versions
                (template_id, usuario_id, numero_versao, nome, descricao, icone, template_base, conteudo_html)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                    $templateId, $_SESSION['admin_id'], $proximaVersao,
                    $atual['nome'], $atual['descricao'], $atual['icone'],
                    $atual['template_base'], $atual['conteudo_html']
                ]);

            $stmtRest = $pdo->prepare("UPDATE user_templates
                SET conteudo_html = ?, template_base = ?, icone = ?, data_atualizacao = NOW()
                WHERE id = ? AND usuario_id = ?");
            $stmtRest->execute([$versao['conteudo_html'], $versao['template_base'], $versao['icone'],
                $templateId, $_SESSION['admin_id']]);
            if ($stmtRest->rowCount() === 0) throw new Exception('Template não encontrado');
            $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao)
                VALUES (?, NULL, ?)")->execute([$_SESSION['admin_id'], "Restaurou uma versão do modelo ID {$templateId}"]);
            echo json_encode(['success' => true]);
            break;

        case 'favoritar_template':
            validarCsrfDocumento($input);
            $tplNome = trim($input['template_nome'] ?? '');
            if (empty($tplNome)) throw new Exception('template_nome obrigatório');

            // Toggle: se já existe, remove; senão, insere
            $stmtChk = $pdo->prepare("SELECT id FROM user_favorites WHERE usuario_id = ? AND template_nome = ?");
            $stmtChk->execute([$_SESSION['admin_id'], $tplNome]);
            if ($stmtChk->fetch()) {
                $pdo->prepare("DELETE FROM user_favorites WHERE usuario_id = ? AND template_nome = ?")
                    ->execute([$_SESSION['admin_id'], $tplNome]);
                echo json_encode(['success' => true, 'favoritado' => false]);
            } else {
                $pdo->prepare("INSERT INTO user_favorites (usuario_id, template_nome) VALUES (?, ?)")
                    ->execute([$_SESSION['admin_id'], $tplNome]);
                echo json_encode(['success' => true, 'favoritado' => true]);
            }
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
