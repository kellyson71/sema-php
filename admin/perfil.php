<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/assinatura_avancada_service.php';
verificaLogin();

$adminId = (int) $_SESSION['admin_id'];
$admin = getDadosAdmin($pdo, $adminId);

// Estado da chave de assinatura eletrônica (para a seção de PIN)
$temChaveAssinatura = (new AssinaturaAvancadaService($pdo))->temChave($adminId);

$uploadDir = '../uploads/perfil/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function getFotoPerfil($adminId, $uploadDir)
{
    $extensoes = ['jpg', 'jpeg', 'png', 'gif'];
    foreach ($extensoes as $ext) {
        $arquivo = $uploadDir . 'admin_' . $adminId . '.' . $ext;
        if (file_exists($arquivo)) {
            return 'admin_' . $adminId . '.' . $ext;
        }
    }
    return null;
}

function removerFotoAnterior($adminId, $uploadDir)
{
    $extensoes = ['jpg', 'jpeg', 'png', 'gif'];
    foreach ($extensoes as $ext) {
        $arquivo = $uploadDir . 'admin_' . $adminId . '.' . $ext;
        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
    }
}

function nomeNivelPerfil(string $nivel): string
{
    return match ($nivel) {
        'admin' => 'Administrador',
        'admin_geral' => 'Admin Geral',
        'secretario' => 'Secretário(a)',
        'analista' => 'Analista',
        'fiscal' => 'Fiscal',
        default => 'Operador',
    };
}

$mensagem = '';
$mensagemTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    require_once 'TwoFactorService.php';
    $twoFactorService = new \Admin\Services\TwoFactorService();

    if ($_POST['action'] === 'setup_totp') {
        $setup = $twoFactorService->generateSetup($admin['email']);
        $_SESSION['totp_setup_secret'] = $setup['secret'];
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode([
            'success' => true,
            'qrcode' => $twoFactorService->getQrCodeImage($setup['qrCodeUri']),
        ]);
        exit;
    }

    if ($_POST['action'] === 'verify_setup_totp') {
        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['totp_setup_secret'] ?? '';

        if (ob_get_length()) {
            ob_clean();
        }

        if ($twoFactorService->verify($secret, $code)) {
            $stmt = $pdo->prepare("UPDATE administradores SET totp_secret = ? WHERE id = ?");
            $stmt->execute([$secret, $adminId]);
            unset($_SESSION['totp_setup_secret']);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Código inválido ou expirado. Tente novamente.']);
        }
        exit;
    }

    if ($_POST['action'] === 'disable_totp') {
        $stmt = $pdo->prepare("UPDATE administradores SET totp_secret = NULL WHERE id = ?");
        $stmt->execute([$adminId]);
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senhaAtual = trim($_POST['senha_atual'] ?? '');
    $novaSenha = trim($_POST['nova_senha'] ?? '');
    $confirmarSenha = trim($_POST['confirmar_senha'] ?? '');

    if ($nome === '' || $email === '') {
        $mensagem = 'Nome e e-mail são campos obrigatórios.';
        $mensagemTipo = 'danger';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM administradores WHERE email = ? AND id != ?");
        $stmt->execute([$email, $adminId]);

        if ($stmt->rowCount() > 0) {
            $mensagem = 'Este e-mail já está sendo utilizado por outro administrador.';
            $mensagemTipo = 'danger';
        } else {
            try {
                $pdo->beginTransaction();

                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $fotoTmp = $_FILES['foto']['tmp_name'];
                    $fotoInfo = pathinfo($_FILES['foto']['name']);
                    $fotoExtensao = strtolower($fotoInfo['extension'] ?? '');
                    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($fotoExtensao, $extensoesPermitidas, true)) {
                        removerFotoAnterior($adminId, $uploadDir);

                        $fotoNome = 'admin_' . $adminId . '.' . $fotoExtensao;
                        $fotoDestino = $uploadDir . $fotoNome;

                        if (!move_uploaded_file($fotoTmp, $fotoDestino)) {
                            throw new Exception('Erro ao fazer upload da foto.');
                        }
                    } else {
                        throw new Exception('Formato de arquivo inválido. Apenas JPG, PNG e GIF são permitidos.');
                    }
                }

                $stmt = $pdo->prepare("UPDATE administradores SET nome = ?, email = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $adminId]);

                if ($senhaAtual !== '' && $novaSenha !== '') {
                    if ($novaSenha !== $confirmarSenha) {
                        throw new Exception('A nova senha e a confirmação não coincidem.');
                    }

                    if (!password_verify($senhaAtual, $admin['senha'])) {
                        throw new Exception('Senha atual incorreta.');
                    }

                    $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                    $stmt->execute([$novaSenhaHash, $adminId]);
                }

                $pdo->commit();

                $_SESSION['admin_nome'] = $nome;
                $_SESSION['admin_email'] = $email;
                $admin = getDadosAdmin($pdo, $adminId);

                $mensagem = 'Perfil atualizado com sucesso!';
                $mensagemTipo = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensagem = 'Erro ao atualizar perfil: ' . $e->getMessage();
                $mensagemTipo = 'danger';
            }
        }
    }
}

$fotoAtual = getFotoPerfil($adminId, $uploadDir);
$fotoSrc = ($fotoAtual && file_exists($uploadDir . $fotoAtual)) ? '../' . urlArquivo('perfil/' . $fotoAtual) : null;
$nivelLabel = nomeNivelPerfil((string) ($admin['nivel'] ?? 'operador'));
$ultimoAcesso = !empty($admin['ultimo_acesso']) ? formataData($admin['ultimo_acesso']) : 'Ainda sem registro';
$totpAtivo = !empty($admin['totp_secret']);
$iniciais = strtoupper(substr(trim((string) ($admin['nome'] ?? 'A')), 0, 2));

include 'header.php';
?>

<style>
    .profile-shell { display:flex; flex-direction:column; gap:18px; max-width:1240px; margin:0 auto; }
    .profile-hero { background:#fff; border:1px solid var(--line); border-radius:20px; padding:26px 28px; box-shadow:var(--card-shadow); display:flex; align-items:flex-start; justify-content:space-between; gap:18px; }
    .profile-hero-kicker { display:inline-flex; align-items:center; gap:8px; min-height:32px; padding:0 12px; border-radius:999px; background:var(--primary-soft); color:var(--primary); font-size:.78rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .profile-hero h1 { margin:14px 0 8px; font-size:2rem; font-weight:800; line-height:1.04; color:var(--ink); }
    .profile-hero p { margin:0; max-width:720px; color:var(--muted); line-height:1.6; }
    .profile-hero-note { min-width:220px; padding:16px 18px; border-radius:18px; border:1px solid var(--line); background:var(--surface-soft); }
    .profile-hero-note span { display:block; font-size:.76rem; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
    .profile-hero-note strong { display:block; font-size:1rem; color:var(--ink); }
    .profile-grid { display:grid; grid-template-columns:330px minmax(0, 1fr); gap:18px; align-items:start; }
    .profile-card,
    .profile-panel { background:#fff; border:1px solid var(--line); border-radius:20px; box-shadow:var(--card-shadow); }
    .profile-card { padding:22px; position:sticky; top:88px; }
    .profile-avatar-wrap { display:flex; align-items:center; justify-content:center; margin-bottom:18px; }
    .profile-avatar,
    .profile-avatar-fallback { width:132px; height:132px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .profile-avatar { object-fit:cover; border:4px solid #fff; box-shadow:0 18px 34px rgba(16, 33, 23, .12); }
    .profile-avatar-fallback { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-strong) 100%); color:#fff; font-size:2rem; font-weight:800; box-shadow:0 18px 34px rgba(16, 33, 23, .16); }
    .profile-name { margin:0 0 6px; font-size:1.34rem; font-weight:800; color:var(--ink); text-align:center; }
    .profile-role { display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:18px; color:var(--muted); font-size:.92rem; }
    .profile-role-badge { display:inline-flex; align-items:center; min-height:28px; padding:0 10px; border-radius:999px; background:var(--primary-soft); color:var(--primary); font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .profile-meta-list { display:flex; flex-direction:column; gap:12px; }
    .profile-meta-item { display:flex; align-items:flex-start; gap:12px; padding:14px 14px; border-radius:16px; background:var(--surface-soft); border:1px solid var(--line); }
    .profile-meta-icon { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#fff; border:1px solid var(--line); color:var(--primary); flex-shrink:0; }
    .profile-meta-item span { display:block; font-size:.75rem; text-transform:uppercase; letter-spacing:.08em; font-weight:800; color:var(--muted-2); margin-bottom:4px; }
    .profile-meta-item strong, .profile-meta-item a { color:var(--ink); font-size:.92rem; text-decoration:none; word-break:break-word; }
    .profile-meta-item a:hover { color:var(--primary); }
    .profile-panels { display:flex; flex-direction:column; gap:18px; }
    .profile-panel { padding:22px; }
    .panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .panel-head h2 { margin:0 0 6px; font-size:1.14rem; font-weight:800; color:var(--ink); }
    .panel-head p { margin:0; color:var(--muted); line-height:1.55; }
    .panel-kicker { display:inline-flex; align-items:center; min-height:28px; padding:0 10px; border-radius:999px; background:var(--surface-soft); color:var(--muted); font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .profile-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
    .field-span-2 { grid-column:1 / -1; }
    .profile-label { display:block; margin-bottom:8px; font-size:.8rem; font-weight:800; color:var(--ink); letter-spacing:.02em; }
    .profile-help { margin-top:8px; color:var(--muted); font-size:.8rem; line-height:1.5; }
    .profile-input,
    .profile-file { width:100%; min-height:48px; border-radius:14px; border:1px solid var(--line); background:#fff; color:var(--ink); padding:0 14px; transition:border-color .2s ease, box-shadow .2s ease; }
    .profile-file { padding:11px 14px; }
    .profile-input:focus,
    .profile-file:focus { outline:none; border-color:var(--primary-soft-2); box-shadow:0 0 0 4px rgba(20, 83, 45, .08); }
    .profile-divider { height:1px; margin:2px 0 0; background:var(--line); }
    .security-card { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:18px; border-radius:18px; border:1px solid var(--line); background:var(--surface-soft); }
    .security-card.is-active { background:linear-gradient(135deg, #eff9f2 0%, #f7fbf8 100%); border-color:#cfe5d5; }
    .security-card.is-inactive { background:linear-gradient(135deg, #f7f9f7 0%, #ffffff 100%); }
    .security-state { display:flex; align-items:flex-start; gap:14px; }
    .security-state-icon { width:52px; height:52px; border-radius:16px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .security-card.is-active .security-state-icon { background:var(--success); color:#fff; }
    .security-card.is-inactive .security-state-icon { background:#fff; border:1px solid var(--line); color:var(--primary); }
    .security-state h3 { margin:0 0 6px; font-size:1rem; font-weight:800; color:var(--ink); }
    .security-state p { margin:0; color:var(--muted); line-height:1.55; max-width:560px; }
    .security-actions { display:flex; align-items:center; gap:10px; }
    .profile-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 18px; border-radius:14px; border:1px solid transparent; font-weight:700; text-decoration:none; transition:transform .2s ease, background-color .2s ease, border-color .2s ease, color .2s ease; }
    .profile-btn:hover { transform:translateY(-1px); }
    .profile-btn-primary { background:var(--primary); border-color:var(--primary-soft-2); color:#fff; }
    .profile-btn-primary:hover { background:var(--primary-strong); color:#fff; }
    .profile-btn-secondary { background:#fff; border-color:var(--line); color:var(--ink); }
    .profile-btn-secondary:hover { border-color:var(--primary-soft-2); color:var(--primary); }
    .profile-btn-danger { background:#fff; border-color:#edc3c3; color:var(--danger); }
    .profile-btn-danger:hover { background:#fff6f6; color:#9a2323; }
    .profile-actions { display:flex; align-items:center; justify-content:flex-end; gap:10px; margin-top:20px; }
    .profile-alert { border:none; border-radius:16px; box-shadow:var(--card-shadow); }
    /* Card de status do PIN de assinatura */
    .pin-status-card { display:flex; align-items:center; gap:16px; padding:18px 20px; border-radius:16px; border:1px solid var(--line); background:#fff; margin-top:14px; }
    .pin-status-card.ativo   { border-color:#bbf0d4; background:linear-gradient(180deg,#f3faf6,#fff); }
    .pin-status-card.inativo { border-color:#fde6c7; background:linear-gradient(180deg,#fffaf2,#fff); }
    .pin-status-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
    .pin-status-card.ativo   .pin-status-icon { background:#1c4b36; color:#fff; }
    .pin-status-card.inativo .pin-status-icon { background:#f59e0b; color:#fff; }
    .pin-status-title { font-size:.98rem; font-weight:700; margin:0 0 2px; color:var(--ink); }
    .pin-status-desc  { font-size:.82rem; color:var(--muted, #64748b); margin:0; line-height:1.4; }
    @media (max-width:640px){ .pin-status-card { flex-direction:column; align-items:flex-start; } }
    /* Avatar com botão de editar foto */
    .profile-avatar-wrap { position:relative; display:inline-block; }
    .avatar-edit { position:absolute; right:4px; bottom:4px; width:34px; height:34px; border-radius:50%; background:var(--primary); color:#fff; border:2.5px solid #fff; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,.22); transition:transform .12s, background .12s; }
    .avatar-edit:hover { transform:scale(1.08); background:var(--primary-strong); }
    /* Itens de segurança compactos */
    .sec-item { display:flex; align-items:center; gap:14px; padding:14px 16px; border:1px solid var(--line); border-radius:14px; margin-bottom:10px; background:#fff; }
    .sec-item .sec-ic { width:42px; height:42px; border-radius:12px; background:var(--primary-soft); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
    .sec-item.ok .sec-ic { background:#dcfce7; color:#15803d; }
    .sec-item .sec-tit { font-weight:700; font-size:.92rem; color:var(--ink); }
    .sec-item .sec-sub { font-size:.78rem; color:var(--muted); margin-top:1px; }
    .sec-item .sec-act { margin-left:auto; flex-shrink:0; }
    @media (max-width:640px){ .sec-item { flex-wrap:wrap; } .sec-item .sec-act { margin-left:0; width:100%; } .sec-item .sec-act .profile-btn { width:100%; } }
    .totp-modal .modal-content { border-radius:24px; border:1px solid var(--line); overflow:hidden; box-shadow:0 28px 52px rgba(16, 33, 23, .18); }
    .totp-modal-header { background:linear-gradient(135deg, var(--primary) 0%, #0f4425 100%); color:#fff; border-bottom:none; padding:22px 24px 18px; }
    .totp-modal-title { margin:0 0 6px; font-size:1.18rem; font-weight:800; color:#fff; }
    .totp-modal-copy { margin:0; color:rgba(255,255,255,.72); line-height:1.55; }
    .totp-modal-header .btn-close { filter:invert(1) brightness(2); opacity:.8; }
    .totp-modal-body { padding:22px 24px 24px; background:#fff; }
    .totp-qr-shell { margin:18px 0 20px; padding:18px; border-radius:18px; border:1px solid var(--line); background:var(--surface-soft); display:flex; justify-content:center; }
    .totp-qr-shell img { max-width:220px; border-radius:16px; border:1px solid var(--line); background:#fff; padding:10px; }
    .totp-input-wrap { max-width:360px; margin:0 auto; }
    .totp-code-input { min-height:56px; border-radius:16px; border:1px solid var(--line); background:var(--surface-soft); text-align:center; letter-spacing:.45em; font-size:1.4rem; font-weight:800; color:var(--primary); }
    .totp-code-input:focus { border-color:var(--primary-soft-2); box-shadow:0 0 0 4px rgba(20, 83, 45, .08); background:#fff; }
    .totp-modal-footer { border-top:none; padding:0 24px 24px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
    @media (max-width: 1099px) {
        .profile-grid { grid-template-columns:1fr; }
        .profile-card { position:static; }
    }
    @media (max-width: 767px) {
        .profile-hero { padding:20px; flex-direction:column; }
        .profile-hero h1 { font-size:1.6rem; }
        .profile-hero-note { width:100%; min-width:0; }
        .profile-form-grid { grid-template-columns:1fr; }
        .security-card,
        .panel-head,
        .profile-actions,
        .totp-modal-footer { flex-direction:column; align-items:stretch; }
        .security-actions,
        .profile-btn { width:100%; }
        .profile-panel,
        .profile-card { padding:18px; }
        .totp-modal-body,
        .totp-modal-header,
        .totp-modal-footer { padding-left:18px; padding-right:18px; }
    }
</style>

<div class="profile-shell">
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= htmlspecialchars($mensagemTipo) ?> alert-dismissible fade show profile-alert" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="profile-grid">
        <aside class="profile-card">
            <div class="profile-avatar-wrap">
                <?php if ($fotoSrc): ?>
                    <img src="<?= htmlspecialchars($fotoSrc) ?>" alt="Foto de perfil" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-fallback"><?= htmlspecialchars($iniciais) ?></div>
                <?php endif; ?>
                <button type="button" class="avatar-edit" title="Trocar foto" onclick="document.getElementById('foto').click()">
                    <i class="fas fa-pen"></i>
                </button>
            </div>

            <h2 class="profile-name"><?= htmlspecialchars($admin['nome']) ?></h2>
            <div class="profile-role">
                <span class="profile-role-badge"><?= htmlspecialchars($nivelLabel) ?></span>
                <?php if (!empty($admin['cargo'])): ?>
                    <span><?= htmlspecialchars($admin['cargo']) ?></span>
                <?php endif; ?>
            </div>

            <div class="profile-meta-list">
                <div class="profile-meta-item">
                    <div class="profile-meta-icon"><i class="fas fa-envelope"></i></div>
                    <div>
                        <span>E-mail</span>
                        <a href="mailto:<?= htmlspecialchars($admin['email']) ?>"><?= htmlspecialchars($admin['email']) ?></a>
                    </div>
                </div>
                <div class="profile-meta-item">
                    <div class="profile-meta-icon"><i class="fas fa-clock-rotate-left"></i></div>
                    <div>
                        <span>Último acesso</span>
                        <strong><?= htmlspecialchars($ultimoAcesso) ?></strong>
                    </div>
                </div>
            </div>
        </aside>

        <div class="profile-panels">
            <!-- Dados da conta -->
            <form method="post" action="" enctype="multipart/form-data" class="profile-panel" id="formDados">
                <div class="panel-head">
                    <div>
                        <span class="panel-kicker">Conta</span>
                        <h2>Dados da conta</h2>
                    </div>
                </div>
                <div class="profile-form-grid">
                    <div>
                        <label for="nome" class="profile-label">Nome</label>
                        <input type="text" class="profile-input" id="nome" name="nome" value="<?= htmlspecialchars($admin['nome']) ?>" required>
                    </div>
                    <div>
                        <label for="email" class="profile-label">E-mail</label>
                        <input type="email" class="profile-input" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                </div>
                <!-- input de foto acionado pelo lápis no avatar (auto-submete) -->
                <input type="file" id="foto" name="foto" accept="image/*" class="d-none" onchange="document.getElementById('formDados').submit()">
                <div class="profile-actions">
                    <button type="submit" class="profile-btn profile-btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                </div>
            </form>

            <!-- Segurança -->
            <div class="profile-panel">
                <div class="panel-head">
                    <div>
                        <span class="panel-kicker">Segurança</span>
                        <h2>Acesso e assinatura</h2>
                    </div>
                </div>

                <div class="sec-item">
                    <div class="sec-ic"><i class="fas fa-lock"></i></div>
                    <div>
                        <div class="sec-tit">Senha de acesso</div>
                        <div class="sec-sub">Usada para entrar no painel</div>
                    </div>
                    <div class="sec-act">
                        <button type="button" class="profile-btn profile-btn-secondary" onclick="abrirModalSenha()">
                            <i class="fas fa-key"></i> Alterar
                        </button>
                    </div>
                </div>

                <div class="sec-item <?= $totpAtivo ? 'ok' : '' ?>">
                    <div class="sec-ic"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="sec-tit">Verificação em duas etapas</div>
                        <div class="sec-sub"><?= $totpAtivo ? 'Ativada — app autenticador' : 'Desativada' ?></div>
                    </div>
                    <div class="sec-act">
                        <?php if ($totpAtivo): ?>
                            <button type="button" class="profile-btn profile-btn-danger" onclick="desativarTotp()">
                                <i class="fas fa-power-off"></i> Desativar
                            </button>
                        <?php else: ?>
                            <button type="button" class="profile-btn profile-btn-primary" onclick="iniciarSetupTotp()">
                                <i class="fas fa-qrcode"></i> Ativar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sec-item <?= $temChaveAssinatura ? 'ok' : '' ?>" style="margin-bottom:0;">
                    <div class="sec-ic"><i class="fas fa-file-signature"></i></div>
                    <div>
                        <div class="sec-tit">PIN de assinatura</div>
                        <div class="sec-sub"><?= $temChaveAssinatura ? 'Configurado — chave RSA ativa' : 'Não configurado' ?></div>
                    </div>
                    <div class="sec-act">
                        <button type="button" class="profile-btn <?= $temChaveAssinatura ? 'profile-btn-secondary' : 'profile-btn-primary' ?>" onclick="abrirModalPin()">
                            <i class="fas <?= $temChaveAssinatura ? 'fa-rotate' : 'fa-key' ?>"></i>
                            <?= $temChaveAssinatura ? 'Redefinir' : 'Configurar' ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Alterar senha -->
<div class="modal fade" id="modalSenha" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header px-4 py-3" style="background:linear-gradient(135deg,#1c4b36,#0d7f5f);">
        <h5 class="modal-title fw-bold text-white"><i class="fas fa-lock me-2"></i>Alterar senha</h5>
        <button type="button" class="btn-close" style="filter:brightness(0) invert(1);" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="">
        <div class="modal-body p-4">
          <input type="hidden" name="nome" value="<?= htmlspecialchars($admin['nome']) ?>">
          <input type="hidden" name="email" value="<?= htmlspecialchars($admin['email']) ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold small">Senha atual</label>
            <input type="password" class="form-control" name="senha_atual" required autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Nova senha</label>
            <input type="password" class="form-control" name="nova_senha" required autocomplete="new-password">
          </div>
          <div class="mb-1">
            <label class="form-label fw-semibold small">Confirmar nova senha</label>
            <input type="password" class="form-control" name="confirmar_senha" required autocomplete="new-password">
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn text-white fw-semibold" style="background:#1c4b36;">
            <i class="fas fa-check me-1"></i> Alterar senha
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal PIN de assinatura -->
<div class="modal fade" id="modalPin" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header px-4 py-3" style="background:linear-gradient(135deg,#1c4b36,#0d7f5f);">
        <h5 class="modal-title fw-bold text-white">
          <i class="fas fa-key me-2"></i><?= $temChaveAssinatura ? 'Redefinir PIN de assinatura' : 'Configurar PIN de assinatura' ?>
        </h5>
        <button type="button" class="btn-close" style="filter:brightness(0) invert(1);" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <?php if ($temChaveAssinatura): ?>
          <div class="alert alert-warning py-2" style="font-size:.82rem;">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Redefinir gera uma <strong>nova chave</strong>. O PIN antigo deixa de funcionar. Documentos já assinados permanecem válidos.
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Senha de login (confirmação)</label>
            <input type="password" id="pinSenhaLogin" class="form-control" autocomplete="current-password" placeholder="Sua senha de acesso ao painel">
          </div>
        <?php endif; ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Novo PIN <span class="text-muted">(mínimo 6 caracteres)</span></label>
          <input type="password" id="pinNovoPerfil" class="form-control" maxlength="64" autocomplete="new-password" placeholder="Crie seu PIN">
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold small">Confirmar PIN</label>
          <input type="password" id="pinConfirmaPerfil" class="form-control" maxlength="64" autocomplete="new-password" placeholder="Repita o PIN">
        </div>
        <div id="pinErroPerfil" class="text-danger small mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4">
        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn text-white fw-semibold" style="background:#1c4b36;" id="btnSalvarPin" onclick="salvarPin()">
          <i class="fas fa-check me-1"></i> <?= $temChaveAssinatura ? 'Redefinir' : 'Configurar' ?>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSetupTotp" tabindex="-1" aria-labelledby="modalSetupTotpLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered totp-modal">
        <div class="modal-content">
            <div class="modal-header totp-modal-header">
                <div>
                    <h5 class="totp-modal-title" id="modalSetupTotpLabel"><i class="fas fa-mobile-screen-button me-2"></i>Configurar app autenticador</h5>
                    <p class="totp-modal-copy">Escaneie o QR Code com Google Authenticator, Authy ou Microsoft Authenticator e valide o primeiro código gerado.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="totp-modal-body">
                <div id="qr-code-container" class="totp-qr-shell">
                    <div class="spinner-border text-success my-4" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>

                <div class="totp-input-wrap">
                    <label for="totp-code-input" class="profile-label text-center d-block">Código de verificação (6 dígitos)</label>
                    <input type="text" class="form-control totp-code-input" id="totp-code-input" placeholder="000000" maxlength="6" inputmode="numeric">
                    <div class="invalid-feedback text-center mt-2 fw-medium" id="totp-error-msg">Código inválido.</div>
                </div>
            </div>
            <div class="totp-modal-footer">
                <button type="button" class="profile-btn profile-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="profile-btn profile-btn-primary" onclick="verificarSetupTotp()" id="btn-verify-totp">
                    <i class="fas fa-check"></i> Ativar proteção
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    let setupModal;
    let pinModal;
    let senhaModal;

    document.addEventListener('DOMContentLoaded', function() {
        setupModal = new bootstrap.Modal(document.getElementById('modalSetupTotp'));
        pinModal = new bootstrap.Modal(document.getElementById('modalPin'));
        senhaModal = new bootstrap.Modal(document.getElementById('modalSenha'));
    });

    function abrirModalSenha() { senhaModal.show(); }

    const temChaveAssinatura = <?= $temChaveAssinatura ? 'true' : 'false' ?>;

    function abrirModalPin() {
        document.getElementById('pinNovoPerfil').value = '';
        document.getElementById('pinConfirmaPerfil').value = '';
        const sl = document.getElementById('pinSenhaLogin');
        if (sl) sl.value = '';
        document.getElementById('pinErroPerfil').style.display = 'none';
        pinModal.show();
    }

    function salvarPin() {
        const erro = document.getElementById('pinErroPerfil');
        const pin = document.getElementById('pinNovoPerfil').value;
        const pin2 = document.getElementById('pinConfirmaPerfil').value;
        const senhaEl = document.getElementById('pinSenhaLogin');
        erro.style.display = 'none';

        if (temChaveAssinatura && senhaEl && !senhaEl.value) {
            erro.textContent = 'Informe sua senha de login para confirmar.';
            erro.style.display = 'block'; return;
        }
        if (pin.length < 6) {
            erro.textContent = 'O PIN deve ter no mínimo 6 caracteres.';
            erro.style.display = 'block'; return;
        }
        if (pin !== pin2) {
            erro.textContent = 'Os PINs não coincidem.';
            erro.style.display = 'block'; return;
        }

        const btn = document.getElementById('btnSalvarPin');
        btn.disabled = true;
        const txtOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Salvando...';

        const body = new URLSearchParams({
            acao: 'criar',
            pin: pin,
            pin_confirmacao: pin2,
            confirmar_recriacao: temChaveAssinatura ? '1' : '0',
        });
        if (temChaveAssinatura && senhaEl) body.append('senha_login', senhaEl.value);

        fetch('assinatura/chave_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(r => r.json())
        .then(ret => {
            btn.disabled = false;
            btn.innerHTML = txtOriginal;
            if (ret.success) {
                pinModal.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ title: 'PIN configurado', text: 'Sua chave de assinatura está pronta.', icon: 'success', timer: 2200, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    alert('PIN configurado com sucesso.');
                    location.reload();
                }
            } else {
                erro.textContent = ret.error || 'Não foi possível salvar o PIN.';
                erro.style.display = 'block';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = txtOriginal;
            erro.textContent = 'Falha de conexão. Tente novamente.';
            erro.style.display = 'block';
        });
    }

    function iniciarSetupTotp() {
        setupModal.show();
        document.getElementById('qr-code-container').innerHTML = '<div class="spinner-border text-success my-4" role="status"></div>';
        document.getElementById('totp-code-input').value = '';
        document.getElementById('totp-code-input').classList.remove('is-invalid');

        fetch('perfil.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=setup_totp'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('qr-code-container').innerHTML = `<img src="${data.qrcode}" alt="QR Code" class="img-fluid">`;
            } else {
                document.getElementById('qr-code-container').innerHTML = '<p class="text-danger mb-0">Erro ao gerar QR Code.</p>';
            }
        });
    }

    function verificarSetupTotp() {
        const codeInput = document.getElementById('totp-code-input');
        const code = codeInput.value.replace(/\D/g, '');
        const btn = document.getElementById('btn-verify-totp');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validando...';
        codeInput.classList.remove('is-invalid');

        fetch('perfil.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=verify_setup_totp&code=${encodeURIComponent(code)}`
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;

            if (data.success) {
                setupModal.hide();
                location.reload();
            } else {
                codeInput.classList.add('is-invalid');
                document.getElementById('totp-error-msg').innerText = data.error;
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            codeInput.classList.add('is-invalid');
            document.getElementById('totp-error-msg').innerText = 'Não foi possível validar agora. Tente novamente.';
        });
    }

    function desativarTotp() {
        if (confirm('Tem certeza que deseja desativar o App Autenticador?')) {
            fetch('perfil.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=disable_totp'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao desativar Autenticador.');
                }
            });
        }
    }
</script>
