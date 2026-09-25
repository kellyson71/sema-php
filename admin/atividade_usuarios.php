<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/atividade_admin.php';
verificaLogin();

if (!in_array($_SESSION['admin_nivel'] ?? '', ['admin', 'admin_geral'], true)) {
    header('Location: index.php');
    exit;
}

$hoje = new DateTimeImmutable('today');
$metricas = [
    'tempo' => ['Tempo ativo', 'fa-clock'],
    'acoes' => ['Ações no sistema', 'fa-bolt'],
    'paginas' => ['Páginas abertas', 'fa-window-maximize'],
];
$metrica = array_key_exists($_GET['metrica'] ?? '', $metricas) ? $_GET['metrica'] : 'tempo';
$adminSelecionado = (int) ($_GET['admin'] ?? 0);
$posthogProjeto = (string) ($_SERVER['POSTHOG_PROJECT_ID'] ?? getenv('POSTHOG_PROJECT_ID') ?: '');
// Sem o id do projeto, o PostHog redireciona o link para o projeto aberto na conta de quem clica.
$posthogApp = 'https://us.posthog.com' . ($posthogProjeto !== '' ? '/project/' . rawurlencode($posthogProjeto) : '');

$tabelasProntas = true;
try {
    $pdo->query('SELECT 1 FROM admin_atividade_diaria LIMIT 1');
    $pdo->query('SELECT 1 FROM admin_eventos LIMIT 1');
} catch (PDOException $e) {
    $tabelasProntas = false;
}

/** Ações de negócio já registradas pelo sistema (vale para todo o histórico, antes deste painel existir). */
function acoesHistoricasPorDia(PDO $pdo, ?int $adminId, string $desde): array
{
    $porDia = [];
    $fontes = ['historico_acoes' => 'data_acao', 'denuncia_historico' => 'data_registro'];
    foreach ($fontes as $tabela => $coluna) {
        try {
            $sql = "SELECT DATE({$coluna}) dia, COUNT(*) n FROM {$tabela} WHERE {$coluna} >= ?";
            $params = [$desde];
            if ($adminId !== null) {
                $sql .= ' AND admin_id = ?';
                $params[] = $adminId;
            }
            $stmt = $pdo->prepare($sql . " GROUP BY DATE({$coluna})");
            $stmt->execute($params);
            foreach ($stmt as $row) {
                $porDia[$row['dia']] = ($porDia[$row['dia']] ?? 0) + (int) $row['n'];
            }
        } catch (PDOException $e) {
            // Tabela ausente em algum ambiente: segue com as outras fontes.
        }
    }
    return $porDia;
}

function valoresPorDia(PDO $pdo, string $metrica, ?int $adminId, string $desde, bool $tabelasProntas): array
{
    if ($metrica === 'acoes') {
        return acoesHistoricasPorDia($pdo, $adminId, $desde);
    }
    if (!$tabelasProntas) {
        return [];
    }
    $coluna = $metrica === 'tempo' ? 'segundos_ativos' : 'paginas';
    $sql = "SELECT dia, SUM({$coluna}) v FROM admin_atividade_diaria WHERE dia >= ?";
    $params = [$desde];
    if ($adminId !== null) {
        $sql .= ' AND admin_id = ?';
        $params[] = $adminId;
    }
    $stmt = $pdo->prepare($sql . ' GROUP BY dia');
    $stmt->execute($params);
    $porDia = [];
    foreach ($stmt as $row) {
        $porDia[$row['dia']] = (int) $row['v'];
    }
    return $porDia;
}

function formatarValorMetrica(string $metrica, int $valor): string
{
    return match ($metrica) {
        'tempo' => atividadeFormatarDuracao($valor),
        'acoes' => $valor . ($valor === 1 ? ' ação' : ' ações'),
        default => $valor . ($valor === 1 ? ' página' : ' páginas'),
    };
}

function rotuloPaginaAdmin(string $pagina): string
{
    $nome = basename($pagina, '.php');
    $conhecidas = [
        'index' => 'Painel inicial', 'requerimentos' => 'Requerimentos', 'visualizar_requerimento' => 'Requerimento',
        'denuncias' => 'Denúncias', 'visualizar_denuncia' => 'Denúncia', 'nova_denuncia' => 'Nova denúncia',
        'processar_denuncia' => 'Salvar denúncia', 'administradores' => 'Gerenciar usuários', 'login' => 'Login',
        'logout' => 'Saída', 'editor' => 'Editor de documento', 'selecionar' => 'Escolher documento',
        'atividade_usuarios' => 'Atividade dos usuários', 'fila_setor' => 'Filas por setor', 'perfil' => 'Meu perfil',
    ];
    return $conhecidas[$nome] ?? ucfirst(str_replace('_', ' ', $nome));
}

function linkEntidade(?string $entidade, ?int $id): ?string
{
    if (!$id) {
        return null;
    }
    return match ($entidade) {
        'denuncia' => 'visualizar_denuncia.php?id=' . $id,
        'requerimento' => 'visualizar_requerimento.php?id=' . $id,
        default => null,
    };
}

$desde = $hoje->modify('-371 days')->format('Y-m-d');
$usuarios = $pdo->query("SELECT * FROM administradores ORDER BY ativo DESC, nome")->fetchAll();
$usuariosPorId = array_column($usuarios, null, 'id');
if ($adminSelecionado && !isset($usuariosPorId[$adminSelecionado])) {
    $adminSelecionado = 0;
}

$resumo = [];
if ($tabelasProntas) {
    $stmt = $pdo->prepare("SELECT admin_id,
            SUM(CASE WHEN dia = CURDATE() THEN segundos_ativos ELSE 0 END) hoje,
            SUM(CASE WHEN dia >= CURDATE() - INTERVAL 6 DAY THEN segundos_ativos ELSE 0 END) semana,
            SUM(CASE WHEN dia >= CURDATE() - INTERVAL 29 DAY THEN segundos_ativos ELSE 0 END) mes,
            SUM(segundos_ativos) total,
            SUM(CASE WHEN dia >= CURDATE() - INTERVAL 29 DAY THEN acoes ELSE 0 END) acoes_mes,
            SUM(CASE WHEN dia >= CURDATE() - INTERVAL 29 DAY THEN paginas ELSE 0 END) paginas_mes,
            SUM(CASE WHEN dia >= CURDATE() - INTERVAL 29 DAY THEN erros ELSE 0 END) erros_mes,
            COUNT(CASE WHEN dia >= CURDATE() - INTERVAL 29 DAY AND segundos_ativos > 0 THEN 1 END) dias_ativos_mes,
            MAX(ultima_atividade) ultima
        FROM admin_atividade_diaria GROUP BY admin_id");
    $stmt->execute();
    foreach ($stmt as $row) {
        $resumo[(int) $row['admin_id']] = $row;
    }
}

$porDia = valoresPorDia($pdo, $metrica, $adminSelecionado ?: null, $desde, $tabelasProntas);
$grade = atividadeGradeHeatmap($porDia, $hoje);
$totalPeriodo = array_sum($porDia);
$diasComAtividade = count(array_filter($porDia));

$eventos = [];
$diaTimeline = null;
if ($adminSelecionado && $tabelasProntas) {
    $diaTimeline = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['dia'] ?? '')) ? $_GET['dia'] : null;
    if ($diaTimeline === null) {
        $stmt = $pdo->prepare('SELECT DATE(MAX(ocorrido_em)) FROM admin_eventos WHERE admin_id = ?');
        $stmt->execute([$adminSelecionado]);
        $diaTimeline = $stmt->fetchColumn() ?: $hoje->format('Y-m-d');
    }
    $stmt = $pdo->prepare("SELECT * FROM admin_eventos
        WHERE admin_id = ? AND ocorrido_em >= ? AND ocorrido_em < ? + INTERVAL 1 DAY AND tipo <> 'ajax'
        ORDER BY ocorrido_em DESC LIMIT 400");
    $stmt->execute([$adminSelecionado, $diaTimeline, $diaTimeline]);
    $eventos = $stmt->fetchAll();
}

function urlAtividade(array $overrides = []): string
{
    global $metrica, $adminSelecionado;
    $params = array_filter(array_merge(['admin' => $adminSelecionado ?: null, 'metrica' => $metrica], $overrides), static fn($v) => $v !== null && $v !== '' && $v !== 0);
    return 'atividade_usuarios.php' . ($params ? '?' . http_build_query($params) : '');
}

$nomesNivel = ['admin' => 'Administrador', 'admin_geral' => 'Admin geral', 'secretario' => 'Secretário', 'analista' => 'Analista', 'fiscal' => 'Fiscal', 'operador' => 'Operador'];
$nomesSetor = ['meio_ambiente' => 'Meio Ambiente', 'obras_urbanismo' => 'Obras e Urbanismo', 'ambos' => 'Todas as equipes'];
$mesesCurtos = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.atv-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:18px}
.atv-card{padding:14px 16px;background:#fff;border:1px solid var(--line);border-radius:14px}
.atv-card small{display:block;color:var(--muted);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.atv-card strong{display:block;margin-top:4px;color:var(--ink);font-size:1.25rem}
.atv-panel{padding:18px 20px;margin-bottom:18px;background:#fff;border:1px solid var(--line);border-radius:16px}
.atv-panel h2{margin:0 0 4px;font-size:1rem;font-weight:800;color:var(--ink)}
.atv-panel-sub{margin:0 0 14px;color:var(--muted);font-size:.82rem}
.atv-metricas{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
.atv-metricas a{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--line);border-radius:999px;color:var(--muted);font-size:.8rem;font-weight:700;text-decoration:none}
.atv-metricas a.active{background:var(--primary);border-color:var(--primary);color:#fff}
.atv-heat-wrap{overflow-x:auto;padding-bottom:6px}
.atv-heat{display:grid;grid-template-columns:28px repeat(53,13px);grid-template-rows:16px repeat(7,13px);gap:3px;width:max-content}
.atv-mes{grid-row:1;font-size:.66rem;color:var(--muted);white-space:nowrap}
.atv-dsem{grid-column:1;font-size:.64rem;color:var(--muted);line-height:13px}
.atv-cel{width:13px;height:13px;border-radius:3px;background:#ebeff0;display:block}
.atv-cel[data-n="1"]{background:#c6e3cf}.atv-cel[data-n="2"]{background:#8cc79f}.atv-cel[data-n="3"]{background:#4f9d69}.atv-cel[data-n="4"]{background:#1f6b3b}
.atv-cel.futuro{visibility:hidden}.atv-cel.sel{outline:2px solid #c98b2e;outline-offset:1px}
a.atv-cel:hover{outline:2px solid #1f6b3b;outline-offset:1px}
.atv-legenda{display:flex;align-items:center;gap:4px;justify-content:flex-end;margin-top:8px;color:var(--muted);font-size:.72rem}
.atv-tabela{width:100%;border-collapse:collapse;font-size:.85rem}
.atv-tabela th{padding:8px 10px;border-bottom:1px solid var(--line);color:var(--muted);font-size:.7rem;font-weight:800;text-align:left;text-transform:uppercase;letter-spacing:.05em}
.atv-tabela td{padding:10px;border-bottom:1px solid #f0f3f1;vertical-align:middle}
.atv-tabela tr.inativo td{color:#9aa7a0}
.atv-tabela a{color:var(--primary);font-weight:750;text-decoration:none}
.atv-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:800}
.atv-pill.pagina{background:#eef3ef;color:#3d5446}.atv-pill.acao{background:#e7efff;color:#2c4f9e}.atv-pill.erro{background:#fbe7e5;color:#913f39}
.atv-gravacao{display:inline-flex;align-items:center;gap:5px;color:var(--primary);font-size:.78rem;font-weight:750;text-decoration:none;white-space:nowrap}.atv-gravacao:hover{text-decoration:underline}
.atv-sid{font-family:ui-monospace,monospace;font-size:.72rem;color:var(--muted)}
.atv-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px 20px;font-size:.88rem;color:var(--ink);word-break:break-word}
.atv-info small{display:block;margin-bottom:2px;color:var(--muted);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.atv-aviso{padding:14px 16px;border-radius:12px;background:#fff7e6;border:1px solid #f0d9a8;color:#7a5a17;margin-bottom:18px}
@media(max-width:760px){.atv-tabela .opcional{display:none}}
</style>

<div class="admin-page-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <h1 class="page-title"><?= $adminSelecionado ? htmlspecialchars($usuariosPorId[$adminSelecionado]['nome']) : 'Atividade dos usuários' ?></h1>
            <p class="page-subtitle"><?= $adminSelecionado
                ? htmlspecialchars(($nomesNivel[$usuariosPorId[$adminSelecionado]['nivel']] ?? $usuariosPorId[$adminSelecionado]['nivel']) . ' · ' . ($nomesSetor[$usuariosPorId[$adminSelecionado]['setor'] ?? ''] ?? ''))
                : 'Tempo ativo, ações e páginas de cada pessoa da equipe.' ?></p>
        </div>
        <?php if ($adminSelecionado): ?><div class="page-toolbar">
            <?php $podeGerenciar = ($_SESSION['admin_nivel'] ?? '') === 'admin'; ?>
            <a href="<?= $podeGerenciar ? 'administradores.php' : htmlspecialchars(urlAtividade(['admin' => null])) ?>" class="toolbar-button"><i class="fas fa-arrow-left"></i> <?= $podeGerenciar ? 'Usuários' : 'Toda a equipe' ?></a>
            <a href="<?= htmlspecialchars($posthogApp . '/person/' . rawurlencode('admin_' . $adminSelecionado) . '#activeTab=sessionRecordings') ?>" target="_blank" rel="noopener" class="toolbar-button"><i class="fas fa-circle-play"></i> Gravações no PostHog</a>
            <?php if ($podeGerenciar): ?><a href="administradores.php?editar=<?= (int) $adminSelecionado ?>" class="toolbar-button toolbar-button-primary"><i class="fas fa-pen"></i> Editar usuário</a><?php endif; ?>
        </div><?php endif; ?>
    </section>

    <?php if (!$tabelasProntas): ?>
        <div class="atv-aviso"><strong>Falta rodar a migration</strong> <code>database/2026-09-25_atividade_admin.sql</code> neste banco. Até lá, só a métrica "Ações no sistema" (que vem do histórico) aparece.</div>
    <?php endif; ?>

    <?php if ($adminSelecionado): $r = $resumo[$adminSelecionado] ?? []; $u = $usuariosPorId[$adminSelecionado]; ?>
        <section class="atv-panel atv-info">
            <div><small>E-mail</small><?= htmlspecialchars($u['email']) ?></div>
            <div><small>Cargo</small><?= htmlspecialchars($nomesNivel[$u['nivel']] ?? $u['nivel']) ?></div>
            <div><small>Equipe</small><?= htmlspecialchars($nomesSetor[$u['setor'] ?? ''] ?? '—') ?></div>
            <div><small>Situação</small><?= $u['ativo'] ? 'Ativo' : 'Inativo' ?><?= !empty($u['primeiro_acesso']) ? ' · ainda não trocou a senha inicial' : '' ?></div>
            <div><small>Último login</small><?= !empty($u['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) : 'Nunca entrou' ?></div>
            <div><small>Cadastrado em</small><?= !empty($u['data_cadastro']) ? date('d/m/Y', strtotime($u['data_cadastro'])) : '—' ?></div>
        </section>
        <section class="atv-cards">
            <div class="atv-card"><small>Ativo hoje</small><strong><?= atividadeFormatarDuracao((int) ($r['hoje'] ?? 0)) ?></strong></div>
            <div class="atv-card"><small>Últimos 7 dias</small><strong><?= atividadeFormatarDuracao((int) ($r['semana'] ?? 0)) ?></strong></div>
            <div class="atv-card"><small>Últimos 30 dias</small><strong><?= atividadeFormatarDuracao((int) ($r['mes'] ?? 0)) ?></strong></div>
            <div class="atv-card"><small>Dias ativos (30 dias)</small><strong><?= (int) ($r['dias_ativos_mes'] ?? 0) ?></strong></div>
            <div class="atv-card"><small>Ações (30 dias)</small><strong><?= (int) ($r['acoes_mes'] ?? 0) ?></strong></div>
            <div class="atv-card"><small>Erros (30 dias)</small><strong><?= (int) ($r['erros_mes'] ?? 0) ?></strong></div>
        </section>
    <?php endif; ?>

    <section class="atv-panel">
        <h2><?= $adminSelecionado ? 'Atividade no último ano' : 'Atividade da equipe no último ano' ?></h2>
        <p class="atv-panel-sub"><?= htmlspecialchars(formatarValorMetrica($metrica, $totalPeriodo)) ?> em <?= $diasComAtividade ?> dia(s)<?= $metrica === 'acoes' ? ' · conta andamentos, mudanças de status, envios e demais ações que o sistema registra no histórico' : ($metrica === 'tempo' ? ' · só conta com a aba visível e mouse/teclado no último minuto' : '') ?><?= $adminSelecionado ? ' · clique num dia para ver o passo a passo' : '' ?></p>
        <nav class="atv-metricas">
            <?php foreach ($metricas as $chave => [$rotulo, $icone]): ?>
                <a href="<?= htmlspecialchars(urlAtividade(['metrica' => $chave])) ?>" class="<?= $metrica === $chave ? 'active' : '' ?>"><i class="fas <?= $icone ?>"></i> <?= $rotulo ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="atv-heat-wrap">
            <div class="atv-heat" role="img" aria-label="Gráfico de atividade por dia">
                <?php
                $mesAnterior = null;
                foreach ($grade as $s => $semana):
                    $mes = (int) substr($semana[0]['data'], 5, 2);
                    if ($mes !== $mesAnterior && (int) substr($semana[0]['data'], 8, 2) <= 7): ?>
                        <span class="atv-mes" style="grid-column:<?= $s + 2 ?>"><?= $mesesCurtos[$mes] ?></span>
                    <?php $mesAnterior = $mes; endif;
                endforeach; ?>
                <?php foreach ([1 => 'seg', 3 => 'qua', 5 => 'sex'] as $linha => $rotulo): ?>
                    <span class="atv-dsem" style="grid-row:<?= $linha + 2 ?>"><?= $rotulo ?></span>
                <?php endforeach; ?>
                <?php foreach ($grade as $s => $semana): foreach ($semana as $d => $cel):
                    $titulo = date('d/m/Y', strtotime($cel['data'])) . ' · ' . formatarValorMetrica($metrica, $cel['valor']);
                    $estilo = 'grid-column:' . ($s + 2) . ';grid-row:' . ($d + 2);
                    $classes = 'atv-cel' . ($cel['futuro'] ? ' futuro' : '') . ($cel['data'] === $diaTimeline ? ' sel' : '');
                    if ($adminSelecionado && !$cel['futuro']): ?>
                        <a class="<?= $classes ?>" data-n="<?= $cel['nivel'] ?>" style="<?= $estilo ?>" title="<?= $titulo ?>" href="<?= htmlspecialchars(urlAtividade(['dia' => $cel['data']])) ?>#linha-do-tempo"></a>
                    <?php else: ?>
                        <span class="<?= $classes ?>" data-n="<?= $cel['nivel'] ?>" style="<?= $estilo ?>" title="<?= $titulo ?>"></span>
                    <?php endif;
                endforeach; endforeach; ?>
            </div>
        </div>
        <div class="atv-legenda">Menos <?php for ($n = 0; $n <= 4; $n++): ?><span class="atv-cel" data-n="<?= $n ?>"></span><?php endfor; ?> Mais</div>
    </section>

    <?php if (!$adminSelecionado): ?>
        <section class="atv-panel">
            <h2>Pessoas</h2>
            <p class="atv-panel-sub">Clique em alguém para ver o gráfico individual e o passo a passo de cada dia.</p>
            <table class="atv-tabela">
                <thead><tr><th>Pessoa</th><th>Hoje</th><th>7 dias</th><th class="opcional">30 dias</th><th class="opcional">Ações (30d)</th><th class="opcional">Erros (30d)</th><th>Última atividade</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): $r = $resumo[(int) $u['id']] ?? []; ?>
                    <tr class="<?= $u['ativo'] ? '' : 'inativo' ?>">
                        <td><a href="<?= htmlspecialchars(urlAtividade(['admin' => (int) $u['id']])) ?>"><?= htmlspecialchars($u['nome']) ?></a><br><small><?= htmlspecialchars(($nomesNivel[$u['nivel']] ?? $u['nivel']) . ' · ' . ($nomesSetor[$u['setor'] ?? ''] ?? '')) ?><?= $u['ativo'] ? '' : ' · inativo' ?></small></td>
                        <td><?= atividadeFormatarDuracao((int) ($r['hoje'] ?? 0)) ?></td>
                        <td><?= atividadeFormatarDuracao((int) ($r['semana'] ?? 0)) ?></td>
                        <td class="opcional"><?= atividadeFormatarDuracao((int) ($r['mes'] ?? 0)) ?></td>
                        <td class="opcional"><?= (int) ($r['acoes_mes'] ?? 0) ?></td>
                        <td class="opcional"><?= (int) ($r['erros_mes'] ?? 0) ?></td>
                        <td><?= !empty($r['ultima']) ? date('d/m/Y H:i', strtotime($r['ultima'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($tabelasProntas): ?>
        <section class="atv-panel" id="linha-do-tempo">
            <h2>Passo a passo de <?= date('d/m/Y', strtotime($diaTimeline)) ?></h2>
            <p class="atv-panel-sub">Mais recente primeiro. "Ver gravação" abre no PostHog a gravação da sessão em que o passo aconteceu.</p>
            <?php if (!$eventos): ?>
                <p class="atv-panel-sub">Nenhum registro neste dia.</p>
            <?php else: ?>
            <table class="atv-tabela">
                <thead><tr><th>Hora</th><th>Tipo</th><th>Onde</th><th>Ação</th><th class="opcional">Registro</th><th class="opcional">Tempo</th><th class="opcional">Gravação</th></tr></thead>
                <tbody>
                <?php foreach ($eventos as $e): $link = linkEntidade($e['entidade'], $e['entidade_id'] !== null ? (int) $e['entidade_id'] : null); ?>
                    <tr>
                        <td><?= date('H:i:s', strtotime($e['ocorrido_em'])) ?></td>
                        <td><span class="atv-pill <?= htmlspecialchars($e['tipo']) ?>"><?= ['pagina' => 'Página', 'acao' => 'Ação', 'erro' => 'Erro'][$e['tipo']] ?? $e['tipo'] ?></span><?= $e['simulando'] ? ' <small>(simulando ' . htmlspecialchars($e['nivel']) . ')</small>' : '' ?></td>
                        <td><?= htmlspecialchars(rotuloPaginaAdmin($e['pagina'])) ?><br><small class="atv-sid"><?= htmlspecialchars($e['pagina']) ?></small></td>
                        <td><?= htmlspecialchars((string) $e['acao']) ?><?php if ($e['erro']): ?><br><small style="color:#913f39"><?= htmlspecialchars($e['erro']) ?></small><?php endif; ?><?php if ((int) $e['status_http'] >= 400): ?> <small>HTTP <?= (int) $e['status_http'] ?></small><?php endif; ?></td>
                        <td class="opcional"><?php if ($e['entidade_id']): ?><?= $link ? '<a href="' . htmlspecialchars($link) . '">' : '' ?><?= htmlspecialchars($e['entidade'] . ' #' . $e['entidade_id']) ?><?= $link ? '</a>' : '' ?><?php else: ?>—<?php endif; ?></td>
                        <td class="opcional"><?= $e['duracao_ms'] !== null ? number_format((int) $e['duracao_ms'] / 1000, 2, ',', '') . ' s' : '' ?></td>
                        <td class="opcional"><?php if ($e['posthog_session_id']): ?><a class="atv-gravacao" target="_blank" rel="noopener" title="Sessão <?= htmlspecialchars($e['posthog_session_id']) ?>" href="<?= htmlspecialchars($posthogApp . '/replay/' . rawurlencode($e['posthog_session_id'])) ?>"><i class="fas fa-circle-play"></i> Ver gravação</a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<script>
// Em tela estreita o gráfico rola: mantém o mês atual à vista até a pessoa rolar sozinha.
document.querySelectorAll('.atv-heat-wrap').forEach(function (el) {
    var rolouManual = false;
    el.addEventListener('pointerdown', function () { rolouManual = true; });
    el.addEventListener('wheel', function () { rolouManual = true; }, { passive: true });
    new ResizeObserver(function () {
        if (!rolouManual) el.scrollLeft = el.scrollWidth;
    }).observe(el);
});
</script>
<?php include 'footer.php'; ?>
