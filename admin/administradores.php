<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

if ($_SESSION['admin_nivel'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$cargos = [
    'analista'    => ['Analista — Triagem', 'Recebe e analisa os protocolos'],
    'fiscal'      => ['Fiscal de Obras', 'Vistorias e notificações de obras'],
    'secretario'  => ['Secretário(a)', 'Revisa e aprova alvarás'],
    'operador'    => ['Operador', 'Acesso geral, sem administração'],
    'admin_geral' => ['Admin geral', 'Vê tudo, sem gerenciar usuários'],
    'admin'       => ['Administrador', 'Acesso total, inclusive usuários'],
];
$equipes = [
    'meio_ambiente'   => ['Meio Ambiente', 'fa-leaf', 'ma'],
    'obras_urbanismo' => ['Obras e Urbanismo', 'fa-hard-hat', 'obras'],
    'ambos'           => ['Todas as equipes', 'fa-layer-group', 'todas'],
];

function voltarAdministradores(string $tipo, string $codigo): void
{
    header('Location: administradores.php?' . http_build_query([$tipo => $codigo]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        voltarAdministradores('erro', 'csrf');
    }

    $acao = $_POST['acao'] ?? 'salvar';
    $id = (int) ($_POST['id'] ?? 0);
    $meuId = (int) $_SESSION['admin_id'];

    try {
        if ($acao === 'alternar_status' || $acao === 'excluir') {
            if ($id === $meuId) {
                voltarAdministradores('erro', 'proprio');
            }
            $stmt = $pdo->prepare('SELECT * FROM administradores WHERE id = ?');
            $stmt->execute([$id]);
            $alvo = $stmt->fetch();
            if (!$alvo) {
                voltarAdministradores('erro', 'nao_encontrado');
            }

            if ($acao === 'alternar_status') {
                $pdo->prepare('UPDATE administradores SET ativo = ? WHERE id = ?')->execute([$alvo['ativo'] ? 0 : 1, $id]);
                voltarAdministradores('ok', $alvo['ativo'] ? 'desativado' : 'ativado');
            }

            $pdo->prepare('DELETE FROM administradores WHERE id = ?')->execute([$id]);
            if (!empty($alvo['foto_perfil'])) {
                $caminhoFoto = '../uploads/perfil/' . basename($alvo['foto_perfil']);
                if (is_file($caminhoFoto)) {
                    unlink($caminhoFoto);
                }
            }
            voltarAdministradores('ok', 'excluido');
        }

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $nivel = array_key_exists($_POST['nivel'] ?? '', $cargos) ? $_POST['nivel'] : 'operador';
        $setor = array_key_exists($_POST['setor'] ?? '', $equipes) ? $_POST['setor'] : 'meio_ambiente';
        $ativo = ($_POST['ativo'] ?? '') === '1' ? 1 : 0;

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            voltarAdministradores('erro', 'campos');
        }
        if ($id === $meuId && ($nivel !== 'admin' || !$ativo)) {
            voltarAdministradores('erro', 'rebaixar_proprio');
        }

        $stmt = $pdo->prepare('SELECT id FROM administradores WHERE email = ? AND id != ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            voltarAdministradores('erro', 'email_em_uso');
        }

        if ($id > 0) {
            if ($senha !== '') {
                $pdo->prepare('UPDATE administradores SET nome = ?, email = ?, senha = ?, nivel = ?, setor = ?, ativo = ? WHERE id = ?')
                    ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nivel, $setor, $ativo, $id]);
            } else {
                $pdo->prepare('UPDATE administradores SET nome = ?, email = ?, nivel = ?, setor = ?, ativo = ? WHERE id = ?')
                    ->execute([$nome, $email, $nivel, $setor, $ativo, $id]);
            }
            voltarAdministradores('ok', 'atualizado');
        }

        if ($senha === '') {
            voltarAdministradores('erro', 'senha');
        }
        $pdo->prepare('INSERT INTO administradores (nome, email, senha, nivel, setor, ativo, primeiro_acesso) VALUES (?, ?, ?, ?, ?, ?, 1)')
            ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nivel, $setor, $ativo]);
        voltarAdministradores('ok', 'criado');
    } catch (PDOException $e) {
        error_log('[administradores] ' . $e->getMessage());
        voltarAdministradores('erro', 'banco');
    }
}

$mensagensOk = [
    'criado' => 'Usuário criado. No primeiro acesso ele vai definir uma nova senha.',
    'atualizado' => 'Alterações salvas.',
    'ativado' => 'Usuário reativado.',
    'desativado' => 'Usuário desativado. Ele não consegue mais entrar até ser reativado.',
    'excluido' => 'Usuário excluído.',
];
$mensagensErro = [
    'csrf' => 'A sessão expirou. Atualize a página e tente de novo.',
    'proprio' => 'Você não pode desativar nem excluir o seu próprio usuário.',
    'rebaixar_proprio' => 'Você não pode tirar o seu próprio acesso de administrador nem se desativar.',
    'nao_encontrado' => 'Usuário não encontrado.',
    'campos' => 'Preencha o nome e um e-mail válido.',
    'email_em_uso' => 'Já existe um usuário com esse e-mail.',
    'senha' => 'Defina uma senha inicial para o novo usuário.',
    'banco' => 'Não foi possível salvar agora. Tente de novo em instantes.',
];
$mensagemOk = $mensagensOk[$_GET['ok'] ?? ''] ?? '';
$mensagemErro = $mensagensErro[$_GET['erro'] ?? ''] ?? '';

$usuarios = $pdo->query('SELECT * FROM administradores ORDER BY ativo DESC, nome')->fetchAll();
$contagem = ['todos' => 0, 'meio_ambiente' => 0, 'obras_urbanismo' => 0, 'ambos' => 0, 'inativos' => 0];
foreach ($usuarios as $u) {
    if (!$u['ativo']) {
        $contagem['inativos']++;
        continue;
    }
    $contagem['todos']++;
    $contagem[$u['setor'] ?? 'meio_ambiente'] = ($contagem[$u['setor'] ?? 'meio_ambiente'] ?? 0) + 1;
}

function iniciaisUsuario(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $iniciais = mb_substr($partes[0] ?? '?', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');
    return mb_strtoupper($iniciais);
}

function acessoRelativo(?string $data): string
{
    if (!$data) {
        return 'Nunca entrou';
    }
    $dias = (int) floor((strtotime('today') - strtotime(date('Y-m-d', strtotime($data)))) / 86400);
    return match (true) {
        $dias <= 0 => 'Hoje, ' . date('H:i', strtotime($data)),
        $dias === 1 => 'Ontem, ' . date('H:i', strtotime($data)),
        $dias < 30 => 'Há ' . $dias . ' dias',
        default => date('d/m/Y', strtotime($data)),
    };
}

include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.usr-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:4px 0 16px;border-bottom:1px solid var(--line)}
.usr-tab{display:inline-flex;align-items:center;gap:8px;margin-bottom:-1px;padding:10px 14px;border:0;border-bottom:3px solid transparent;background:none;color:var(--muted);font-weight:750}
.usr-tab:hover{color:var(--ink)}.usr-tab.active{color:var(--ink);border-bottom-color:var(--primary)}
.usr-count{display:inline-block;min-width:22px;padding:1px 7px;border-radius:999px;background:#eef3ef;color:#3d5446;font-size:.72rem;font-weight:800;text-align:center}
.usr-busca{position:relative;margin-bottom:14px}.usr-busca i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#8fa399;font-size:.85rem}
.usr-busca input{width:100%;min-height:44px;padding:8px 12px 8px 36px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink)}
.usr-lista{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
.usr-linha{display:grid;grid-template-columns:minmax(220px,2.2fr) minmax(140px,1.2fr) minmax(140px,1.1fr) minmax(120px,1fr) auto;gap:16px;align-items:center;padding:14px 18px;border-top:1px solid #eef2ef}
.usr-linha:first-child{border-top:0}.usr-cab{padding:10px 18px;background:#f7f9f8;color:var(--muted);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.usr-linha.inativo{background:#fafafa}.usr-linha.inativo .usr-pessoa,.usr-linha.inativo .usr-col{opacity:.55}
.usr-pessoa{display:flex;align-items:center;gap:12px;min-width:0}
.usr-avatar{flex:0 0 38px;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e3ece6;color:#2e5a3f;font-size:.8rem;font-weight:800;overflow:hidden}
.usr-avatar img{width:100%;height:100%;object-fit:cover}
.usr-nome{color:var(--ink);font-weight:750;line-height:1.25}.usr-email{color:var(--muted);font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.usr-voce{margin-left:6px;padding:1px 7px;border-radius:999px;background:#eef3ef;color:#3d5446;font-size:.66rem;font-weight:800;text-transform:uppercase}
.usr-col{color:var(--ink);font-size:.86rem}.usr-col small{display:block;color:var(--muted);font-size:.74rem}
.usr-equipe{display:inline-flex;align-items:center;gap:7px}.usr-equipe i{font-size:.8rem}
.usr-equipe.ma i{color:#3f8a55}.usr-equipe.obras i{color:#c98b2e}.usr-equipe.todas i{color:#6b7c73}
.usr-inativo-tag{display:inline-block;margin-top:3px;padding:1px 7px;border-radius:999px;background:#f1f1f1;color:#6b6b6b;font-size:.68rem;font-weight:800}
.usr-acoes{display:flex;align-items:center;gap:6px;justify-content:flex-end}
.usr-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);font-size:.82rem;font-weight:700;text-decoration:none}
.usr-btn:hover{border-color:#b9cbc0;color:var(--ink)}.usr-btn.icone{padding:7px 10px}
.usr-menu .dropdown-item{font-size:.86rem;display:flex;align-items:center;gap:9px}.usr-menu .dropdown-item.perigo{color:#a33b34}
.usr-vazio{padding:40px 20px;text-align:center;color:var(--muted)}
.usr-form .form-label{font-size:.8rem;font-weight:750;color:var(--ink)}
.usr-opcoes{display:grid;gap:8px}.usr-opcao{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid var(--line);border-radius:10px;cursor:pointer}
.usr-opcao:has(input:checked){border-color:var(--primary);background:#f2f8f4}.usr-opcao input{margin-top:3px}
.usr-opcao strong{display:block;font-size:.86rem}.usr-opcao small{color:var(--muted);font-size:.76rem}
.usr-opcoes.equipes{grid-template-columns:repeat(3,1fr)}
@media(max-width:900px){.usr-cab{display:none}.usr-linha{grid-template-columns:1fr auto;gap:8px 12px}.usr-linha .usr-col{grid-column:1}.usr-acoes{grid-row:1;grid-column:2}.usr-opcoes.equipes{grid-template-columns:1fr}}
</style>

<div class="admin-page-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <h1 class="page-title">Usuários</h1>
            <p class="page-subtitle">Quem acessa o painel, com qual cargo e em qual equipe.</p>
        </div>
        <div class="page-toolbar"><button type="button" class="toolbar-button toolbar-button-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario"><i class="fas fa-plus"></i> Novo usuário</button></div>
    </section>

    <?php if ($mensagemOk): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensagemOk) ?></div><?php endif; ?>
    <?php if ($mensagemErro): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($mensagemErro) ?></div><?php endif; ?>

    <nav class="usr-tabs" aria-label="Filtrar por equipe">
        <button type="button" class="usr-tab active" data-filtro="todos">Ativos <span class="usr-count"><?= $contagem['todos'] ?></span></button>
        <button type="button" class="usr-tab" data-filtro="meio_ambiente"><i class="fas fa-leaf"></i> Meio Ambiente <span class="usr-count"><?= $contagem['meio_ambiente'] ?></span></button>
        <button type="button" class="usr-tab" data-filtro="obras_urbanismo"><i class="fas fa-hard-hat"></i> Obras e Urbanismo <span class="usr-count"><?= $contagem['obras_urbanismo'] ?></span></button>
        <button type="button" class="usr-tab" data-filtro="ambos"><i class="fas fa-layer-group"></i> Todas as equipes <span class="usr-count"><?= $contagem['ambos'] ?></span></button>
        <button type="button" class="usr-tab" data-filtro="inativos">Inativos <span class="usr-count"><?= $contagem['inativos'] ?></span></button>
    </nav>

    <div class="usr-busca"><i class="fas fa-magnifying-glass"></i><input type="search" id="usrBusca" placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuário"></div>

    <section class="usr-lista" aria-label="Usuários">
        <div class="usr-linha usr-cab"><span>Pessoa</span><span>Cargo</span><span>Equipe</span><span>Último acesso</span><span></span></div>
        <?php foreach ($usuarios as $u):
            $cargo = $cargos[$u['nivel']] ?? [ucfirst($u['nivel']), ''];
            $equipe = $equipes[$u['setor'] ?? 'meio_ambiente'] ?? $equipes['meio_ambiente'];
            $souEu = (int) $u['id'] === (int) $_SESSION['admin_id'];
            $temFoto = !empty($u['foto_perfil']) && is_file('../uploads/perfil/' . basename($u['foto_perfil']));
        ?>
            <div class="usr-linha <?= $u['ativo'] ? '' : 'inativo' ?>" data-setor="<?= htmlspecialchars($u['setor'] ?? 'meio_ambiente') ?>" data-ativo="<?= $u['ativo'] ? '1' : '0' ?>" data-busca="<?= htmlspecialchars(mb_strtolower($u['nome'] . ' ' . $u['email'])) ?>">
                <div class="usr-pessoa">
                    <span class="usr-avatar"><?php if ($temFoto): ?><img src="<?= htmlspecialchars('../' . urlArquivo('perfil/' . $u['foto_perfil'])) ?>" alt=""><?php else: ?><?= htmlspecialchars(iniciaisUsuario($u['nome'])) ?><?php endif; ?></span>
                    <div style="min-width:0">
                        <div class="usr-nome"><?= htmlspecialchars($u['nome']) ?><?php if ($souEu): ?><span class="usr-voce">você</span><?php endif; ?></div>
                        <div class="usr-email"><?= htmlspecialchars($u['email']) ?></div>
                        <?php if (!$u['ativo']): ?><span class="usr-inativo-tag">Inativo</span><?php endif; ?>
                    </div>
                </div>
                <div class="usr-col"><?= htmlspecialchars($cargo[0]) ?></div>
                <div class="usr-col"><span class="usr-equipe <?= $equipe[2] ?>"><i class="fas <?= $equipe[1] ?>"></i><?= htmlspecialchars($equipe[0]) ?></span></div>
                <div class="usr-col" title="<?= $u['ultimo_acesso'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['ultimo_acesso']))) : '' ?>"><?= htmlspecialchars(acessoRelativo($u['ultimo_acesso'])) ?></div>
                <div class="usr-acoes">
                    <button type="button" class="usr-btn" data-bs-toggle="modal" data-bs-target="#modalUsuario"
                        data-id="<?= (int) $u['id'] ?>" data-nome="<?= htmlspecialchars($u['nome']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>"
                        data-nivel="<?= htmlspecialchars($u['nivel']) ?>" data-setor="<?= htmlspecialchars($u['setor'] ?? 'meio_ambiente') ?>"
                        data-ativo="<?= $u['ativo'] ? '1' : '0' ?>" data-eu="<?= $souEu ? '1' : '0' ?>">Editar</button>
                    <div class="dropdown">
                        <button type="button" class="usr-btn icone" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mais ações para <?= htmlspecialchars($u['nome']) ?>"><i class="fas fa-ellipsis-vertical"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end usr-menu">
                            <li><a class="dropdown-item" href="atividade_usuarios.php?admin=<?= (int) $u['id'] ?>"><i class="fas fa-chart-simple"></i> Ver atividade</a></li>
                            <?php if (!$souEu): ?>
                                <li><button type="button" class="dropdown-item" data-acao="alternar_status" data-id="<?= (int) $u['id'] ?>" data-confirmar="<?= htmlspecialchars(($u['ativo'] ? 'Desativar ' : 'Reativar ') . $u['nome'] . '?' . ($u['ativo'] ? ' Ele não vai conseguir entrar até ser reativado.' : '')) ?>"><i class="fas <?= $u['ativo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i> <?= $u['ativo'] ? 'Desativar' : 'Reativar' ?></button></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button type="button" class="dropdown-item perigo" data-acao="excluir" data-id="<?= (int) $u['id'] ?>" data-confirmar="<?= htmlspecialchars('Excluir ' . $u['nome'] . ' definitivamente? Prefira desativar: excluir não pode ser desfeito.') ?>"><i class="fas fa-trash-can"></i> Excluir</button></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="usr-vazio" id="usrVazio" hidden>Ninguém encontrado com esse filtro.</div>
    </section>
</div>

<form method="post" id="formAcaoUsuario" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="acao" id="acaoUsuario">
    <input type="hidden" name="id" id="acaoUsuarioId">
</form>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" class="usr-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsuarioTitulo">Novo usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" id="usr_id">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="usr_nome">Nome</label><input type="text" class="form-control" id="usr_nome" name="nome" required></div>
                        <div class="col-md-6"><label class="form-label" for="usr_email">E-mail</label><input type="email" class="form-control" id="usr_email" name="email" required></div>
                        <div class="col-12">
                            <label class="form-label" for="usr_senha" id="usr_senha_rotulo">Senha inicial</label>
                            <input type="password" class="form-control" id="usr_senha" name="senha" autocomplete="new-password">
                            <small class="text-muted" id="usr_senha_ajuda">No primeiro acesso a pessoa troca essa senha.</small>
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block">Cargo</span>
                            <div class="usr-opcoes" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
                                <?php foreach ($cargos as $valor => [$rotulo, $descricao]): ?>
                                    <label class="usr-opcao"><input type="radio" name="nivel" value="<?= $valor ?>"><span><strong><?= htmlspecialchars($rotulo) ?></strong><small><?= htmlspecialchars($descricao) ?></small></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block">Equipe</span>
                            <div class="usr-opcoes equipes">
                                <?php foreach ($equipes as $valor => [$rotulo, $icone]): ?>
                                    <label class="usr-opcao"><input type="radio" name="setor" value="<?= $valor ?>"><span><strong><i class="fas <?= $icone ?>"></i> <?= htmlspecialchars($rotulo) ?></strong></span></label>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Define a aba em que as denúncias abrem e a equipe das denúncias que a pessoa registra.</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="usr_ativo" name="ativo" value="1"><label class="form-check-label" for="usr_ativo">Pode entrar no sistema</label></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="usr-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="toolbar-button toolbar-button-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var linhas = Array.prototype.slice.call(document.querySelectorAll('.usr-linha[data-setor]'));
    var busca = document.getElementById('usrBusca');
    var vazio = document.getElementById('usrVazio');
    var filtro = 'todos';

    function aplicar() {
        var termo = busca.value.trim().toLowerCase();
        var visiveis = 0;
        linhas.forEach(function (linha) {
            var ativo = linha.dataset.ativo === '1';
            var passaFiltro = filtro === 'inativos' ? !ativo : (ativo && (filtro === 'todos' || linha.dataset.setor === filtro));
            var mostra = passaFiltro && (termo === '' || linha.dataset.busca.indexOf(termo) !== -1);
            linha.hidden = !mostra;
            if (mostra) visiveis++;
        });
        vazio.hidden = visiveis > 0;
    }
    document.querySelectorAll('.usr-tab').forEach(function (aba) {
        aba.addEventListener('click', function () {
            document.querySelectorAll('.usr-tab').forEach(function (a) { a.classList.remove('active'); });
            aba.classList.add('active');
            filtro = aba.dataset.filtro;
            aplicar();
        });
    });
    busca.addEventListener('input', aplicar);
    aplicar();

    var formAcao = document.getElementById('formAcaoUsuario');
    document.querySelectorAll('[data-acao]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (!window.confirm(botao.dataset.confirmar)) return;
            document.getElementById('acaoUsuario').value = botao.dataset.acao;
            document.getElementById('acaoUsuarioId').value = botao.dataset.id;
            formAcao.submit();
        });
    });

    var modal = document.getElementById('modalUsuario');
    modal.addEventListener('show.bs.modal', function (event) {
        var b = event.relatedTarget || {};
        var editando = b.dataset && b.dataset.id;
        var d = editando ? b.dataset : { id: '', nome: '', email: '', nivel: 'analista', setor: 'meio_ambiente', ativo: '1', eu: '0' };
        modal.querySelector('.modal-title').textContent = editando ? 'Editar ' + d.nome : 'Novo usuário';
        document.getElementById('usr_id').value = d.id;
        document.getElementById('usr_nome').value = d.nome;
        document.getElementById('usr_email').value = d.email;
        document.getElementById('usr_senha').value = '';
        document.getElementById('usr_senha').required = !editando;
        document.getElementById('usr_senha_rotulo').textContent = editando ? 'Nova senha (opcional)' : 'Senha inicial';
        document.getElementById('usr_senha_ajuda').textContent = editando ? 'Deixe em branco para manter a senha atual.' : 'No primeiro acesso a pessoa troca essa senha.';
        modal.querySelectorAll('input[name="nivel"]').forEach(function (r) { r.checked = r.value === d.nivel; r.disabled = d.eu === '1' && r.value !== 'admin'; });
        modal.querySelectorAll('input[name="setor"]').forEach(function (r) { r.checked = r.value === d.setor; });
        var ativo = document.getElementById('usr_ativo');
        ativo.checked = d.ativo === '1';
        ativo.disabled = d.eu === '1';
    });
});
</script>

<?php include 'footer.php'; ?>
