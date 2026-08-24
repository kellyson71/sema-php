<?php
// Nas páginas públicas, a sessão pode ainda não estar reconstruída quando o
// acesso veio de um dispositivo confiável. Restaura somente pelo cookie seguro
// já emitido pelo login administrativo; sem esse cookie, não abre conexão nem
// tenta descobrir usuário algum.
if (empty($_SESSION['admin_id']) && !empty($_COOKIE['sema_device_token'])) {
    require_once __DIR__ . '/../admin/conexao.php';
    if (isset($pdo) && function_exists('verificarSessaoConfiada')) {
        try {
            verificarSessaoConfiada($pdo);
        } catch (Throwable $e) {
            // A página pública não pode quebrar por uma falha de restauração.
        }
    }
}

$adminLogado = !empty($_SESSION['admin_id']);
if (!$adminLogado) {
    return;
}

$adminNome = trim((string) ($_SESSION['admin_nome_completo'] ?? $_SESSION['admin_nome'] ?? ''));
$adminNomeExibicao = $adminNome !== '' ? $adminNome : 'Servidor SEMA';
$adminCargo = trim((string) ($_SESSION['admin_cargo'] ?? 'Área administrativa'));
$adminUrl = $adminUrl ?? 'admin/index.php';
?>

<section class="public-admin-access is-authenticated" aria-label="Sessão administrativa ativa">
    <div class="public-admin-access-icon" aria-hidden="true"><i class="fas fa-user"></i></div>
    <div class="public-admin-access-copy">
        <div class="public-admin-access-meta">
            <span class="public-admin-access-status">Sessão administrativa ativa</span>
            <span class="public-admin-access-role"><?= htmlspecialchars($adminCargo, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <strong class="public-admin-access-name"><?= htmlspecialchars($adminNomeExibicao, ENT_QUOTES, 'UTF-8') ?></strong>
        <p>Acesso reconhecido. Continue para o painel.</p>
    </div>
    <a class="public-admin-access-action" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>">
        <span>Abrir painel</span><i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
</section>
