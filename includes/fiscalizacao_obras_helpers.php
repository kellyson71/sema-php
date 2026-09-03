<?php

/**
 * Rótulos legíveis dos tipos de documento de fiscalização de obras.
 * Espelham os modelos usados hoje pelo fiscal (notificações.pdf).
 */
const FISCALIZACAO_OBRAS_TIPOS = [
    'notificacao_fiscal_obras' => 'Notificação Fiscal/Obras',
    'notificacao_descarte_material' => 'Notificação Descarte de Material',
    'embargo' => 'Embargo',
    'interdicao' => 'Interdição',
    'outro' => 'Outro',
];

const FISCALIZACAO_OBRAS_STATUS = [
    'pendente' => 'Pendente',
    'notificado' => 'Notificado',
    'protocolado' => 'Processo Protocolado na Prefeitura',
    'autuado' => 'Autuado (Auto de Infração)',
    'alvara_emitido' => 'Alvará Emitido',
    'multado' => 'Multado',
    'interditado' => 'Interditado',
    'multa_paga' => 'Multa Paga',
    'encaminhado_outra_secretaria' => 'Encaminhado para outra Secretaria',
    'finalizado' => 'Finalizado',
    'outro' => 'Outro',
];

/**
 * Checklist de artigos do Código de Obras (Lei Municipal nº 2.117/2025) por
 * tipo de documento, transcrito literalmente dos modelos em uso pelo fiscal.
 * O admin marca os que se aplicam ao caso; ficam em artigos_selecionados (JSON).
 */
const FISCALIZACAO_OBRAS_ARTIGOS = [
    'notificacao_fiscal_obras' => [
        'art_236' => 'Art. 236 - Iniciar a execução ou demolição de obras sem licença ou alvará do respectivo órgão que fiscaliza. Penalidade: multa em 100% do valor da taxa de alvará e embargo. Se a obra ou serviço não puder ser licenciado: multa e demolição.',
        'art_229' => 'Art. 229 - Deixar de observar as regras relativas a alinhamento, índices de ocupação, de utilização e de conforto, recuos, gabaritos, aberturas, acessos ou vedar divisas, quando proibido. Penalidade: multa classe 2 e demolição/reparação.',
        'art_241' => 'Art. 241 - Deixar de garantir a proteção com tapumes ou aparadeiras nas obras e/ou serviços quando exigidos neste Código. Penalidade: multa, Classe 3 e colocação da proteção.',
        'art_243' => 'Art. 243 - Invasão a terrenos do Município: multa, Classe 2 e desapropriação.',
        'art_225' => 'Art. 225 - Instalar mobiliários ou equipamentos que impliquem em bloquear, obstruir ou dificultar os acessos às rampas de uso exclusivo de portadores de necessidades especiais. Penalidade: multa classe 2 e reparação. (Orientação sobre passeios calçadas Seção I - GUIAS, PASSEIOS: Art. 53)',
    ],
    'notificacao_descarte_material' => [
        'art_221' => 'Art. 221 - Jogar entulhos nas vias, logradouros públicos, estradas vicinais, terrenos, dentre outros locais não autorizados. Penalidade: multa classe 3 por dia e remoção.',
        'art_242' => 'Art. 242 - A permanência de qualquer material de construção ou entulhos nas vias e logradouros públicos sem a devida licença da Secretaria Municipal de Meio Ambiente. Penalidade: multa, Classe 3 e retirada do material.',
        'art_185' => 'Art. 185 - Os resíduos de construção civil não poderão ser dispostos no passeio nem na faixa de rolamento das vias. Em obras em execução, deve-se dispor os materiais em contêiners.',
        'art_186' => 'Art. 186 - §2° Os geradores de resíduos de construção terão o prazo máximo de 5 dias para efetuar a retirada dos entulhos, prazo este contado a partir da notificação para remoção do material.',
    ],
    'embargo' => [
        'art_236' => 'Art. 236 - Iniciar a execução ou demolição de obras sem licença ou alvará do respectivo órgão que fiscaliza. Penalidade: multa em 100% do valor da taxa de alvará e embargo. Se a obra ou serviço não puder ser licenciado: multa e demolição.',
        'art_245' => 'Art. 245 - Dar-se-ão embargos sempre que se verificar execução de obra: I - Sem licença, quando indispensável; II - Em desacordo com o projeto aprovado; III - Com inobservância de alinhamento ou de nivelamento, fixados pela Prefeitura; IV - Quando causar prejuízo ao interesse ou patrimônio públicos.',
    ],
    'interdicao' => [],
    'outro' => [],
];

function fiscalizacaoObrasArtigosDoTipo(string $tipo): array
{
    return FISCALIZACAO_OBRAS_ARTIGOS[$tipo] ?? [];
}

function fiscalizacaoObrasTipoLabel(string $tipo): string
{
    return FISCALIZACAO_OBRAS_TIPOS[$tipo] ?? ucwords(str_replace('_', ' ', $tipo));
}

function fiscalizacaoObrasStatusLabel(string $status): string
{
    return FISCALIZACAO_OBRAS_STATUS[$status] ?? ucwords(str_replace('_', ' ', $status));
}

/**
 * Soma $prazoDias dias úteis (seg-sex) a partir de $dataEmissao.
 * Não considera feriados municipais — só fins de semana. Limitação conhecida:
 * revisar se a Secretaria pedir um calendário de feriados mais preciso.
 */
function calcularVencimentoDiasUteis(string $dataEmissao, int $prazoDias): string
{
    $data = new DateTime($dataEmissao);
    $restantes = $prazoDias;

    while ($restantes > 0) {
        $data->modify('+1 day');
        $diaSemana = (int) $data->format('N'); // 1 (segunda) a 7 (domingo)
        if ($diaSemana < 6) {
            $restantes--;
        }
    }

    return $data->format('Y-m-d');
}

/**
 * Alerta de proximidade do vencimento, recalculado a cada carregamento
 * (não precisa de job/cron porque o prazo é contado em dias).
 * Retorna ['nivel' => 'ok'|'atencao'|'vencido', 'dias' => int, 'label' => string]
 */
function calcularAlertaPrazo(?string $dataVencimento): array
{
    if (!$dataVencimento) {
        return ['nivel' => 'indefinido', 'dias' => null, 'label' => 'Sem prazo definido'];
    }

    $hoje = new DateTime('today');
    $venc = new DateTime($dataVencimento);
    $dias = (int) $hoje->diff($venc)->format('%r%a');

    if ($dias < 0) {
        return ['nivel' => 'vencido', 'dias' => $dias, 'label' => 'Prazo Vencido'];
    }
    if ($dias <= 5) {
        return ['nivel' => 'atencao', 'dias' => $dias, 'label' => 'Reta Final'];
    }
    return ['nivel' => 'ok', 'dias' => $dias, 'label' => 'No Prazo'];
}

/**
 * Aloca o próximo número sequencial de um tipo de documento no ano informado.
 * Cada tipo de notificação tem sua própria contagem (bate com os modelos em
 * uso: cada um tem seu "Nº ___/ano"), separada da numeração de requerimentos
 * (document_number_sequences é compartilhada, então o template_key aqui leva
 * um prefixo próprio pra não colidir com os templates de parecer).
 */
function proximoNumeroNotificacao(PDO $pdo, string $tipoDocumento, ?int $ano = null): int
{
    $ano ??= (int) date('Y');
    $templateKey = 'notificacao_obras:' . $tipoDocumento;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO document_number_sequences (template_key, ano, ultimo_numero)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE ultimo_numero = ultimo_numero + 1
        ");
        $stmt->execute([$templateKey, $ano]);

        $stmt = $pdo->prepare("
            SELECT ultimo_numero FROM document_number_sequences
            WHERE template_key = ? AND ano = ?
        ");
        $stmt->execute([$templateKey, $ano]);
        $numero = (int) $stmt->fetchColumn();

        $pdo->commit();
        return $numero;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Cadastra uma notificação de obras. $dados espera as chaves da tabela
 * notificacoes_obras (tipo_documento, notificado_nome, descricao_fato,
 * data_emissao, prazo_dias, origem, fiscal_id, etc). Calcula o número oficial
 * e o vencimento automaticamente.
 */
function inserirNotificacaoObras(PDO $pdo, array $dados): int
{
    $ano = (int) date('Y', strtotime($dados['data_emissao']));
    $numero = proximoNumeroNotificacao($pdo, $dados['tipo_documento'], $ano);

    $vencimento = null;
    if (!empty($dados['prazo_dias'])) {
        $vencimento = calcularVencimentoDiasUteis($dados['data_emissao'], (int) $dados['prazo_dias']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO notificacoes_obras (
            tipo_documento, numero, ano, origem, notificado_nome, notificado_cpf_cnpj,
            proprietario_nome, endereco, bairro, numero_imovel, descricao_fato,
            artigos_selecionados, prazo_dias, data_emissao, data_vencimento, status,
            observacoes, denuncia_origem_id, pdf_upload_path, fiscal_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $dados['tipo_documento'],
        $numero,
        $ano,
        $dados['origem'] ?? 'gerado_sistema',
        $dados['notificado_nome'],
        $dados['notificado_cpf_cnpj'] ?? null,
        $dados['proprietario_nome'] ?? null,
        $dados['endereco'] ?? null,
        $dados['bairro'] ?? null,
        $dados['numero_imovel'] ?? null,
        $dados['descricao_fato'],
        $dados['artigos_selecionados'] ?? null,
        $dados['prazo_dias'] ?? null,
        $dados['data_emissao'],
        $vencimento,
        $dados['status'] ?? 'pendente',
        $dados['observacoes'] ?? null,
        $dados['denuncia_origem_id'] ?? null,
        $dados['pdf_upload_path'] ?? null,
        $dados['fiscal_id'],
    ]);

    return (int) $pdo->lastInsertId();
}

function buscarNotificacaoObras(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM notificacoes_obras WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Lista notificações com filtro opcional de status/tipo, ordenadas pelo
 * vencimento mais próximo primeiro (quem está pra vencer aparece no topo).
 */
function listarNotificacoesObras(PDO $pdo, array $filtros = []): array
{
    $where = [];
    $params = [];

    if (!empty($filtros['status'])) {
        $where[] = 'status = ?';
        $params[] = $filtros['status'];
    }
    if (!empty($filtros['tipo_documento'])) {
        $where[] = 'tipo_documento = ?';
        $params[] = $filtros['tipo_documento'];
    }

    $sql = "SELECT * FROM notificacoes_obras";
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY (data_vencimento IS NULL), data_vencimento ASC, id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function atualizarStatusNotificacaoObras(PDO $pdo, int $id, string $status, ?string $observacoes = null): void
{
    if ($observacoes !== null) {
        $stmt = $pdo->prepare("UPDATE notificacoes_obras SET status = ?, observacoes = ? WHERE id = ?");
        $stmt->execute([$status, $observacoes, $id]);
        return;
    }
    $stmt = $pdo->prepare("UPDATE notificacoes_obras SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

function registrarDocumentoAssinadoNotificacao(PDO $pdo, int $notificacaoId, string $documentoId): void
{
    $stmt = $pdo->prepare("UPDATE notificacoes_obras SET documento_id = ? WHERE id = ?");
    $stmt->execute([$documentoId, $notificacaoId]);
}

/**
 * Monta o HTML do documento de uma notificação a partir do template
 * correspondente em admin/templates_obras/, preenchendo {{variaveis}}.
 * Usado tanto na pré-visualização quanto na geração do PDF assinado.
 */
function renderizarNotificacaoObrasHtml(array $notificacao): string
{
    $arquivoTemplate = file_exists(__DIR__ . '/../admin/templates_obras/' . $notificacao['tipo_documento'] . '.html')
        ? $notificacao['tipo_documento'] . '.html'
        : 'generico.html';

    $template = file_get_contents(__DIR__ . '/../admin/templates_obras/' . $arquivoTemplate);

    $artigosDisponiveis = fiscalizacaoObrasArtigosDoTipo($notificacao['tipo_documento']);
    $selecionados = $notificacao['artigos_selecionados'] ? json_decode($notificacao['artigos_selecionados'], true) : [];
    $selecionados = is_array($selecionados) ? $selecionados : [];

    $artigosHtml = '';
    foreach ($artigosDisponiveis as $codigo => $texto) {
        $marca = in_array($codigo, $selecionados, true) ? '☑' : '☐';
        $artigosHtml .= '<div class="item">' . $marca . ' ' . htmlspecialchars($texto) . '</div>';
    }

    $vars = [
        'titulo_documento' => fiscalizacaoObrasTipoLabel($notificacao['tipo_documento']),
        'numero_documento_ano' => $notificacao['numero'] . '/' . $notificacao['ano'],
        'notificado_nome' => htmlspecialchars($notificacao['notificado_nome'] ?? ''),
        'notificado_cpf_cnpj' => htmlspecialchars($notificacao['notificado_cpf_cnpj'] ?? 'Não informado'),
        'bairro' => htmlspecialchars($notificacao['bairro'] ?? 'Não informado'),
        'numero_imovel' => htmlspecialchars($notificacao['numero_imovel'] ?? 'S/N'),
        'endereco' => htmlspecialchars($notificacao['endereco'] ?? 'Não informado'),
        'descricao_fato' => nl2br(htmlspecialchars($notificacao['descricao_fato'] ?? '')),
        'artigos_html' => $artigosHtml,
        'prazo_dias' => $notificacao['prazo_dias'] ?? '____',
        'ano_atual' => date('Y'),
    ];

    return strtr($template, array_combine(
        array_map(fn($k) => '{{' . $k . '}}', array_keys($vars)),
        array_values($vars)
    ));
}

/**
 * Busca os dados de uma denúncia (setor obras_urbanismo) para pré-preencher
 * o formulário de nova notificação. Não grava nada — o fiscal ainda escolhe
 * tipo de documento, artigos e prazo antes de emitir (ver admin/fiscalizacao_obras/nova.php).
 */
function dadosDenunciaParaNotificacao(PDO $pdo, int $denunciaId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM denuncias WHERE id = ? LIMIT 1");
    $stmt->execute([$denunciaId]);
    $denuncia = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$denuncia) {
        return null;
    }

    return [
        'notificado_nome' => $denuncia['infrator_nome'],
        'notificado_cpf_cnpj' => $denuncia['infrator_cpf_cnpj'],
        'endereco' => $denuncia['infrator_endereco'],
        'descricao_fato' => $denuncia['observacoes'],
        'denuncia_origem_id' => $denunciaId,
    ];
}
