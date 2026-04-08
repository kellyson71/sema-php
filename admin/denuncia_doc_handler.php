<?php
require_once 'conexao.php';
require_once '../includes/config.php';

verificaLogin();

header('Content-Type: application/json');

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {

        // ─── Listar templates disponíveis para denúncias ────────────────
        case 'listar_templates_denuncia':
            $templatesDir = realpath(__DIR__ . '/templates') . '/';

            $templatesInfo = [
                'denuncia_notificacao' => [
                    'label'   => 'Notificação Fiscal',
                    'icon'    => 'fa-exclamation-circle',
                    'cor'     => 'text-warning',
                    'badge'   => 'Notificação',
                    'desc'    => 'Notificação oficial expedida pela fiscalização ambiental ao infrator identificado.',
                    'preview' => 'Notifica o proprietário/responsável a regularizar a situação ambiental no prazo estabelecido.',
                ],
                'denuncia_auto_infracao' => [
                    'label'   => 'Auto de Infração Ambiental',
                    'icon'    => 'fa-ban',
                    'cor'     => 'text-danger',
                    'badge'   => 'Auto de Infração',
                    'desc'    => 'Auto de infração com dados do autuado, base legal e penalidade aplicada.',
                    'preview' => 'Documenta a infração ambiental, aplica multa e determina prazo para defesa.',
                ],
                'denuncia_tac' => [
                    'label'   => 'Termo de Ajustamento de Conduta (TAC)',
                    'icon'    => 'fa-handshake',
                    'cor'     => 'text-primary',
                    'badge'   => 'TAC',
                    'desc'    => 'Acordo administrativo para substituição de penalidade por medidas compensatórias ou reparadoras.',
                    'preview' => 'Formaliza o compromisso do autuado em cumprir medidas compensatórias como alternativa à multa.',
                ],
                'denuncia_termo_compromisso' => [
                    'label'   => 'Termo de Compromisso Ambiental',
                    'icon'    => 'fa-leaf',
                    'cor'     => 'text-success',
                    'badge'   => 'Compromisso',
                    'desc'    => 'Termo de compromisso de recuperação ambiental com obrigações, prazos e penalidades.',
                    'preview' => 'Define as obrigações de recuperação ambiental, prazos e consequências do descumprimento.',
                ],
                'denuncia_relatorio_vistoria' => [
                    'label'   => 'Relatório de Vistoria Ambiental',
                    'icon'    => 'fa-clipboard-list',
                    'cor'     => 'text-info',
                    'badge'   => 'Relatório',
                    'desc'    => 'Relatório de vistoria com histórico, constatações e providências adotadas.',
                    'preview' => 'Documenta os fatos constatados na vistoria ambiental realizada no local.',
                ],
                'denuncia_parecer_ambiental' => [
                    'label'   => 'Parecer Técnico Ambiental',
                    'icon'    => 'fa-microscope',
                    'cor'     => 'text-secondary',
                    'badge'   => 'Parecer',
                    'desc'    => 'Parecer técnico da fiscalização com constatações, fundamentação legal e conclusão.',
                    'preview' => 'Emite parecer técnico fundamentado sobre a infração ambiental identificada.',
                ],
            ];

            $templates = [];
            foreach ($templatesInfo as $nome => $info) {
                $arquivo = $templatesDir . $nome . '.html';
                if (file_exists($arquivo)) {
                    $templates[] = [
                        'nome'    => $nome,
                        'label'   => $info['label'],
                        'icon'    => $info['icon'],
                        'cor'     => $info['cor'],
                        'badge'   => $info['badge'],
                        'desc'    => $info['desc'],
                        'preview' => $info['preview'],
                    ];
                }
            }

            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        // ─── Carregar template com dados da denúncia preenchidos ─────────
        case 'carregar_template_denuncia':
            $template    = $input['template'] ?? '';
            $denuncia_id = (int)($input['denuncia_id'] ?? 0);

            if (empty($template) || $denuncia_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            // Buscar dados da denúncia
            $stmt = $pdo->prepare("
                SELECT d.*, a.nome as admin_nome, a.nome_completo as admin_nome_completo,
                       a.cargo as admin_cargo, a.matricula_portaria as admin_matricula
                FROM denuncias d
                LEFT JOIN administradores a ON d.admin_id = a.id
                WHERE d.id = ?
            ");
            $stmt->execute([$denuncia_id]);
            $denuncia = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$denuncia) {
                throw new Exception('Denúncia não encontrada');
            }

            // Buscar admin logado atual
            $stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, cargo, matricula_portaria FROM administradores WHERE id = ?");
            $stmtAdmin->execute([$_SESSION['admin_id']]);
            $adminAtual = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            // Montar variáveis
            $nomeAdmin = $adminAtual['nome_completo'] ?: $adminAtual['nome'];
            $dataReg   = !empty($denuncia['data_registro'])
                ? date('d/m/Y', strtotime($denuncia['data_registro']))
                : date('d/m/Y');

            $variaveis = [
                '{{numero_denuncia}}'   => str_pad($denuncia['id'], 6, '0', STR_PAD_LEFT),
                '{{infrator_nome}}'     => $denuncia['infrator_nome'] ?? '',
                '{{infrator_cpf_cnpj}}' => $denuncia['infrator_cpf_cnpj'] ?: 'Não informado',
                '{{infrator_endereco}}' => $denuncia['infrator_endereco'] ?: 'Não informado',
                '{{observacoes}}'       => htmlspecialchars($denuncia['observacoes'] ?? ''),
                '{{data_registro}}'     => $dataReg,
                '{{data_atual}}'        => date('d') . ' de ' . _mesExtenso(date('n')) . ' de ' . date('Y'),
                '{{fiscal_nome}}'       => $nomeAdmin,
                '{{admin_cargo}}'       => $adminAtual['cargo'] ?: 'Fiscal de Meio Ambiente',
            ];

            // Carregar HTML do template
            $templatePath = realpath(__DIR__ . '/templates') . '/' . basename($template) . '.html';
            if (!file_exists($templatePath)) {
                throw new Exception('Template não encontrado: ' . $template);
            }

            $html = file_get_contents($templatePath);
            // Substituir variáveis
            $html = str_replace(array_keys($variaveis), array_values($variaveis), $html);

            echo json_encode([
                'success' => true,
                'html'    => $html,
                'dados'   => $variaveis,
            ]);
            break;

        // ─── Gerar PDF da denúncia ────────────────────────────────────────
        case 'gerar_pdf_denuncia':
            $html        = $input['html'] ?? '';
            $denuncia_id = (int)($input['denuncia_id'] ?? 0);
            $template    = $input['template'] ?? 'documento';

            if (empty($html) || $denuncia_id <= 0) {
                throw new Exception('Parâmetros inválidos');
            }

            // Verificar que a denúncia existe
            $stmtD = $pdo->prepare("SELECT id, infrator_nome FROM denuncias WHERE id = ?");
            $stmtD->execute([$denuncia_id]);
            $denunciaCheck = $stmtD->fetch();
            if (!$denunciaCheck) {
                throw new Exception('Denúncia não encontrada');
            }

            // Salvar HTML temporário na sessão para renderização
            $_SESSION['denuncia_pdf_html']     = $html;
            $_SESSION['denuncia_pdf_id']       = $denuncia_id;
            $_SESSION['denuncia_pdf_template'] = $template;
            $_SESSION['denuncia_pdf_time']     = time();

            $baseUrl = rtrim(BASE_URL, '/');
            echo json_encode([
                'success'  => true,
                'pdf_url'  => $baseUrl . '/admin/gerar_pdf_denuncia.php?denuncia_id=' . $denuncia_id . '&t=' . time(),
            ]);
            break;

        default:
            throw new Exception('Ação desconhecida: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function _mesExtenso(int $mes): string {
    $meses = ['','janeiro','fevereiro','março','abril','maio','junho',
              'julho','agosto','setembro','outubro','novembro','dezembro'];
    return $meses[$mes] ?? '';
}
