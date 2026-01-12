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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                Informações do Perfil
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

<?php include 'footer.php'; ?>