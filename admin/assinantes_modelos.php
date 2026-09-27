<?php
/**
 * Quem assina os modelos ambientais (LAU e Parecer Técnico de Pendências).
 * Os documentos já saem com o nome, cargo e matrícula daqui, e ao assinar o
 * sistema pede a coassinatura dos demais. Ver includes/assinantes_modelos.php.
 */
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/assinantes_modelos.php';
verificaLogin();

if (!in_array($_SESSION['admin_nivel'] ?? '', ['admin', 'admin_geral'], true)) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$papeis = papeisAssinantesModelo();
$mensagem = '';
$erro = '';

function salvarConfiguracaoModelo(PDO $pdo, string $chave, string $nome, string $valor): void
{
    $pdo->prepare("INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao)
        VALUES (?, ?, ?, 'texto', 'Assinantes dos modelos', 'Definido em Assinantes dos modelos')
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
        ->execute([$chave, $nome, $valor]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            $pdo->beginTransaction();
            foreach ($papeis as $papel => [$titulo]) {
                $id = (int) ($_POST[$papel . '_id'] ?? 0);
                salvarConfiguracaoModelo($pdo, "modelo_assinante_{$papel}_id", "{$titulo} (assinante dos modelos)", $id > 0 ? (string) $id : '');
                salvarConfiguracaoModelo($pdo, "modelo_assinante_{$papel}_rotulo", "{$titulo} — cargo no documento", mb_substr(trim((string) ($_POST[$papel . '_rotulo'] ?? '')), 0, 120));
                salvarConfiguracaoModelo($pdo, "modelo_assinante_{$papel}_matricula", "{$titulo} — matrícula/portaria", mb_substr(trim((string) ($_POST[$papel . '_matricula'] ?? '')), 0, 120));
            }
            $pdo->prepare('INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, NULL, ?)')
                ->execute([$_SESSION['admin_id'], 'Atualizou os assinantes dos modelos ambientais (LAU e parecer de pendências)']);
            $pdo->commit();
            $mensagem = 'Assinantes salvos. Os próximos documentos já saem com essas pessoas.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[assinantes_modelos] ' . $e->getMessage());
            $erro = 'Não foi possível salvar. Tente novamente.';
        }
    }
}

$config = [];
foreach ($papeis as $papel => [, $rotuloPadrao]) {
    $config[$papel] = [
        'id' => (int) DocumentoRegras::configuracao($pdo, "modelo_assinante_{$papel}_id", '0'),
        'rotulo' => DocumentoRegras::configuracao($pdo, "modelo_assinante_{$papel}_rotulo", $rotuloPadrao),
        'matricula' => DocumentoRegras::configuracao($pdo, "modelo_assinante_{$papel}_matricula", ''),
    ];
}
$efetivos = assinantesModelo($pdo);
$admins = $pdo->query("SELECT id, nome, nome_completo, cargo, matricula_portaria FROM administradores WHERE ativo = 1 ORDER BY COALESCE(NULLIF(nome_completo, ''), nome)")
    ->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Assinantes dos modelos';
include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.am-hero { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.am-hero-icon { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#1c4b36,#0d7f5f);
    color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.am-hero h1 { margin:0; font-size:1rem; font-weight:700; color:var(--req-ink); }
.am-sub { font-size:.85rem; color:var(--req-muted); margin:0 0 16px; max-width:760px; }
.am-card { background:#fff; border:1px solid var(--req-line); border-radius:14px; padding:16px 18px; margin-bottom:14px; }
.am-card h2 { font-size:.92rem; font-weight:800; margin:0 0 12px; color:var(--req-ink); }
.am-grid { display:grid; grid-template-columns:2fr 1.4fr 1fr; gap:12px; }
.am-grid label { font-size:.75rem; font-weight:700; color:var(--req-muted); margin-bottom:4px; display:block; }
.am-previa { font-size:.8rem; color:var(--req-muted); margin-top:10px; }
@media (max-width: 760px) { .am-grid { grid-template-columns:1fr; } }
</style>

<div class="am-hero">
    <div class="am-hero-icon"><i class="fas fa-signature"></i></div>
    <h1>Assinantes dos modelos ambientais</h1>
</div>
<p class="am-sub">
    A Licença Ambiental Única (LAU) e o Parecer Técnico de Pendências saem com o nome, o cargo e a matrícula
    destas pessoas. Quando alguém assina um desses documentos, o sistema já pede a coassinatura das outras
    (o parecer de pendências leva só o Eng. Ambiental e o Fiscal).
</p>

<?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($papeis as $papel => [$titulo, $rotuloPadrao]): $c = $config[$papel]; $ef = $efetivos[$papel]; ?>
    <div class="am-card">
        <h2><?= htmlspecialchars($titulo) ?></h2>
        <div class="am-grid">
            <div>
                <label for="<?= $papel ?>_id">Pessoa</label>
                <select class="form-select" id="<?= $papel ?>_id" name="<?= $papel ?>_id">
                    <option value="0"><?= $papel === 'secretario' ? 'Secretário ativo (automático)' : 'Ninguém definido' ?></option>
                    <?php foreach ($admins as $a): $nomeA = $a['nome_completo'] ?: $a['nome']; ?>
                    <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nomeA . ($a['cargo'] ? ' — ' . $a['cargo'] : '')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="<?= $papel ?>_rotulo">Cargo no documento</label>
                <input class="form-control" id="<?= $papel ?>_rotulo" name="<?= $papel ?>_rotulo" value="<?= htmlspecialchars($c['rotulo']) ?>" placeholder="<?= htmlspecialchars($rotuloPadrao) ?>">
            </div>
            <div>
                <label for="<?= $papel ?>_matricula">Matrícula/portaria</label>
                <input class="form-control" id="<?= $papel ?>_matricula" name="<?= $papel ?>_matricula" value="<?= htmlspecialchars($c['matricula']) ?>" placeholder="Em branco: usa a do cadastro">
            </div>
        </div>
        <div class="am-previa">
            No documento: <strong><?= htmlspecialchars($ef['nome'] ?: 'ninguém definido') ?></strong>
            — <?= htmlspecialchars($ef['rotulo']) ?><?= $ef['matricula'] !== '' ? ' — ' . htmlspecialchars($ef['matricula']) : '' ?>
        </div>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar</button>
</form>
<?php include 'footer.php'; ?>
