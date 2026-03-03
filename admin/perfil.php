<?php
require_once 'conexao.php';
verificaLogin();

$adminId = $_SESSION['admin_id'];
$admin = getDadosAdmin($pdo, $adminId);

// Verificar se o diretório de uploads existe
$uploadDir = '../uploads/perfil/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Função para gerenciar foto de perfil via arquivo (sem banco de dados)
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

$mensagem = '';
$mensagemTipo = '';

// Processar chamadas AJAX do TOTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    require_once 'TwoFactorService.php';
    $twoFactorService = new \Admin\Services\TwoFactorService();
    
    if ($_POST['action'] === 'setup_totp') {
        $setup = $twoFactorService->generateSetup($admin['email']);
        $_SESSION['totp_setup_secret'] = $setup['secret'];
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'qrcode' => $twoFactorService->getQrCodeImage($setup['qrCodeUri'])]);
        exit;
    }
    
    if ($_POST['action'] === 'verify_setup_totp') {
        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['totp_setup_secret'] ?? '';
        
        if (ob_get_length()) ob_clean();
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
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }
}

// Processar formulário normal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senhaAtual = trim($_POST['senha_atual'] ?? '');
    $novaSenha = trim($_POST['nova_senha'] ?? '');
    $confirmarSenha = trim($_POST['confirmar_senha'] ?? '');

    // Validar dados
    if (empty($nome) || empty($email)) {
        $mensagem = "Nome e e-mail são campos obrigatórios.";
        $mensagemTipo = "danger";
    } else {
        // Verificar se o e-mail já está em uso (por outro administrador)
        $stmt = $pdo->prepare("SELECT id FROM administradores WHERE email = ? AND id != ?");
        $stmt->execute([$email, $adminId]);
        if ($stmt->rowCount() > 0) {
            $mensagem = "Este e-mail já está sendo utilizado por outro administrador.";
            $mensagemTipo = "danger";
        } else {
            try {
                $pdo->beginTransaction(); // Verificar se há upload de foto
                $fotoAtual = getFotoPerfil($adminId, $uploadDir); // Buscar foto atual no sistema de arquivos

                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $fotoTmp = $_FILES['foto']['tmp_name'];
                    $fotoInfo = pathinfo($_FILES['foto']['name']);
                    $fotoExtensao = strtolower($fotoInfo['extension']);

                    // Validar extensão
                    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($fotoExtensao, $extensoesPermitidas)) {
                        // Remover foto anterior se existir
                        removerFotoAnterior($adminId, $uploadDir);

                        // Gerar nome para a nova foto
                        $fotoNome = 'admin_' . $adminId . '.' . $fotoExtensao;
                        $fotoDestino = $uploadDir . $fotoNome;

                        // Mover arquivo
                        if (!move_uploaded_file($fotoTmp, $fotoDestino)) {
                            throw new Exception("Erro ao fazer upload da foto.");
                        }
                    } else {
                        throw new Exception("Formato de arquivo inválido. Apenas JPG, PNG e GIF são permitidos.");
                    }
                }

                // Atualizar apenas dados básicos (sem foto_perfil)
                $stmt = $pdo->prepare("UPDATE administradores SET nome = ?, email = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $adminId]);

                // Atualizar senha se fornecida
                if (!empty($senhaAtual) && !empty($novaSenha)) {
                    if ($novaSenha !== $confirmarSenha) {
                        throw new Exception("A nova senha e a confirmação não coincidem.");
                    }

                    // Verificar senha atual
                    if (!password_verify($senhaAtual, $admin['senha'])) {
                        throw new Exception("Senha atual incorreta.");
                    }

                    // Atualizar senha
                    $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                    $stmt->execute([$novaSenhaHash, $adminId]);
                }

                $pdo->commit();

                // Atualizar dados da sessão
                $_SESSION['admin_nome'] = $nome;
                $_SESSION['admin_email'] = $email;

                // Atualizar dados do admin para exibição na página
                $admin = getDadosAdmin($pdo, $adminId);

                $mensagem = "Perfil atualizado com sucesso!";
                $mensagemTipo = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensagem = "Erro ao atualizar perfil: " . $e->getMessage();
                $mensagemTipo = "danger";
            }
        }
    }
}

include 'header.php';
?>

<h2 class="section-title">Meu Perfil</h2>

<?php if ($mensagem): ?>
    <div class="alert alert-<?php echo $mensagemTipo; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensagem; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-circle me-1"></i>
                Informações do Perfil teste
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <?php
                    $fotoAtual = getFotoPerfil($adminId, $uploadDir);
                    if ($fotoAtual && file_exists($uploadDir . $fotoAtual)): ?>
                        <img src="<?php echo '../uploads/perfil/' . $fotoAtual; ?>" alt="Foto de Perfil" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                            <i class="fas fa-user fa-5x text-secondary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="card-title"><?php echo htmlspecialchars($admin['nome']); ?></h5>
                <p class="card-text text-muted">
                    <?php echo $admin['nivel'] === 'admin' ? 'Administrador' : 'Operador'; ?>
                </p>
                <p class="card-text">
                    <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($admin['email']); ?>
                </p>
                <p class="card-text">
                    <i class="fas fa-clock me-2"></i> Último acesso:
                    <?php echo $admin['ultimo_acesso'] ? formataData($admin['ultimo_acesso']) : 'Nunca'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-edit me-1"></i>
                Editar Perfil
            </div>
            <div class="card-body">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($admin['nome']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="foto" class="form-label">Foto de Perfil</label>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        <small class="form-text text-muted">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB.</small>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-shield-alt text-primary fs-4 me-2"></i>
                        <h5 class="mb-0">Autenticação em Duas Etapas (App)</h5>
                    </div>
                    <p class="text-muted small mb-4">Aumente a segurança da sua conta usando um aplicativo como Google Authenticator ou Authy.</p>
                    
                    <div id="totp-status-container" class="bg-light p-4 rounded-4 border shadow-sm">
                        <?php if (!empty($admin['totp_secret'])): ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center text-success">
                                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                        <i class="fas fa-check fs-4"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block fs-5">Autenticador Ativado</strong>
                                        <small class="text-muted">Sua conta está protegida com segurança extra.</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-danger rounded-pill px-4" onclick="desativarTotp()">
                                    <i class="fas fa-power-off me-1"></i> Desativar
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center text-secondary">
                                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                        <i class="fas fa-shield-alt fs-4"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block fs-5 text-dark">Autenticador Desativado</strong>
                                        <small class="text-muted">Recomendamos ativar para evitar invasões.</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="iniciarSetupTotp()">
                                    <i class="fas fa-qrcode me-1"></i> Configurar Agora
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr class="my-4">

                    <h5>Alterar Senha</h5>
                    <p class="text-muted small mb-3">Deixe em branco se não deseja alterar sua senha</p>

                    <div class="mb-3">
                        <label for="senha_atual" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="senha_atual" name="senha_atual">
                    </div>

                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                    </div>

                    <div class="mb-3">
                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Configurar TOTP -->
<div class="modal fade" id="modalSetupTotp" tabindex="-1" aria-labelledby="modalSetupTotpLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-top border-primary border-4 rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalSetupTotpLabel"><i class="fas fa-mobile-alt text-primary me-2"></i> App Autenticador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-center pt-2">
                <p class="text-muted mb-4">Escaneie o QR Code abaixo usando um aplicativo como <strong>Google Authenticator</strong>, <strong>Authy</strong> ou <strong>Microsoft Authenticator</strong>.</p>
                
                <div id="qr-code-container" class="my-4 d-flex justify-content-center">
                    <div class="p-3 bg-white border rounded-4 shadow-sm" style="display:inline-block">
                        <div class="spinner-border text-primary my-4" role="status"><span class="visually-hidden">Carregando...</span></div>
                    </div>
                </div>
                
                <div class="form-group mt-4 text-start px-md-4">
                    <label for="totp-code-input" class="form-label fw-medium text-secondary">Código de verificação (6 dígitos):</label>
                    <input type="text" class="form-control form-control-lg text-center fw-bold text-primary" id="totp-code-input" placeholder="000 000" maxlength="6" style="letter-spacing: 0.5em; font-size: 1.5rem; border-radius: 12px; background-color: #f8f9fa;">
                    <div class="invalid-feedback text-center mt-2 fw-medium" id="totp-error-msg">Código inválido.</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-md-4 d-flex justify-content-between">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-5 shadow-sm" onclick="verificarSetupTotp()" id="btn-verify-totp">
                    <i class="fas fa-check me-1"></i> Ativar Proteção
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    let setupModal;

    document.addEventListener("DOMContentLoaded", function() {
        setupModal = new bootstrap.Modal(document.getElementById('modalSetupTotp'));
    });

    function iniciarSetupTotp() {
        setupModal.show();
        document.getElementById('qr-code-container').innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
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
                document.getElementById('qr-code-container').innerHTML = `<img src="${data.qrcode}" alt="QR Code" class="img-fluid border rounded" style="max-width:200px;">`;
            } else {
                document.getElementById('qr-code-container').innerHTML = '<p class="text-danger">Erro ao gerar QR Code.</p>';
            }
        });
    }

    function verificarSetupTotp() {
        const codeInput = document.getElementById('totp-code-input');
        const code = codeInput.value;
        const btn = document.getElementById('btn-verify-totp');
        
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
            btn.innerHTML = 'Confirmar';
            if (data.success) {
                setupModal.hide();
                location.reload(); // Recarrega para mostrar a UI atualizada
            } else {
                codeInput.classList.add('is-invalid');
                document.getElementById('totp-error-msg').innerText = data.error;
            }
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