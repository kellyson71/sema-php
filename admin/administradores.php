<?php
require_once 'conexao.php';
verificaLogin();

// Verificar se o usuário é admin
if ($_SESSION['admin_nivel'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$mensagem = '';
$mensagemTipo = '';

// Processar exclusão de administrador
if (isset($_GET['excluir'])) {
    $idExcluir = (int)$_GET['excluir'];

    // Não permitir auto-exclusão
    if ($idExcluir === (int)$_SESSION['admin_id']) {
        $mensagem = "Você não pode excluir seu próprio usuário.";
        $mensagemTipo = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
            $stmt->execute([$idExcluir]);
            $adminExcluir = $stmt->fetch();

            if ($adminExcluir) {
                $stmt = $pdo->prepare("DELETE FROM administradores WHERE id = ?");
                $stmt->execute([$idExcluir]);

                // Apagar foto de perfil se existir
                if (!empty($adminExcluir['foto_perfil'])) {
                    $caminhoFoto = '../uploads/perfil/' . $adminExcluir['foto_perfil'];
                    if (file_exists($caminhoFoto)) {
                        unlink($caminhoFoto);
                    }
                }

                $mensagem = "Administrador excluído com sucesso.";
                $mensagemTipo = "success";
            } else {
                $mensagem = "Administrador não encontrado.";
                $mensagemTipo = "danger";
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao excluir administrador: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar mudança de status (ativar/desativar)
if (isset($_GET['alterarStatus'])) {
    $idAlterar = (int)$_GET['alterarStatus'];

    // Não permitir alteração do próprio status
    if ($idAlterar === (int)$_SESSION['admin_id']) {
        $mensagem = "Você não pode alterar o status do seu próprio usuário.";
        $mensagemTipo = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT ativo FROM administradores WHERE id = ?");
            $stmt->execute([$idAlterar]);
            $admin = $stmt->fetch();

            if ($admin) {
                $novoStatus = $admin['ativo'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE administradores SET ativo = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $idAlterar]);

                $mensagem = "Status do administrador alterado com sucesso.";
                $mensagemTipo = "success";
            } else {
                $mensagem = "Administrador não encontrado.";
                $mensagemTipo = "danger";
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao alterar status: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar formulário de adicionar/editar administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nivel = $_POST['nivel'] ?? 'operador';
    $ativo = isset($_POST['ativo']) && $_POST['ativo'] == 1 ? 1 : 0;
    $idEditar = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Validar dados
    if (empty($nome) || empty($email)) {
        $mensagem = "Nome e e-mail são campos obrigatórios.";
        $mensagemTipo = "danger";
    } else {
        try {
            // Verificar se o e-mail já está em uso
            $stmt = $pdo->prepare("SELECT id FROM administradores WHERE email = ? AND id != ?");
            $stmt->execute([$email, $idEditar]);

            if ($stmt->rowCount() > 0) {
                $mensagem = "Este e-mail já está sendo utilizado.";
                $mensagemTipo = "danger";
            } else {
                // Inserir novo admin ou atualizar existente
                if ($idEditar > 0) {
                    // Atualizar existente
                    if (!empty($senha)) {
                        // Se forneceu senha, atualiza senha também
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE administradores SET nome = ?, email = ?, senha = ?, nivel = ?, ativo = ? WHERE id = ?");
                        $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo, $idEditar]);
                    } else {
                        // Sem senha, não atualiza este campo
                        $stmt = $pdo->prepare("UPDATE administradores SET nome = ?, email = ?, nivel = ?, ativo = ? WHERE id = ?");
                        $stmt->execute([$nome, $email, $nivel, $ativo, $idEditar]);
                    }

                    $mensagem = "Administrador atualizado com sucesso.";
                } else {
                    // Inserir novo - senha é obrigatória
                    if (empty($senha)) {
                        $mensagem = "A senha é obrigatória para novos administradores.";
                        $mensagemTipo = "danger";
                    } else {
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO administradores (nome, email, senha, nivel, ativo) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo]);

                        $mensagem = "Administrador adicionado com sucesso.";
                    }
                }

                if ($mensagemTipo !== "danger") {
                    $mensagemTipo = "success";
                }
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao salvar administrador: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Obter lista de administradores
$stmt = $pdo->query("SELECT * FROM administradores ORDER BY nome");
$administradores = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="section-title mb-0">Gerenciar Administradores</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdmin">
        <i class="fas fa-plus"></i> Novo Administrador
    </button>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-<?php echo $mensagemTipo; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensagem; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-users-cog me-1"></i>
        Lista de Administradores
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Último Acesso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($administradores as $admin): ?>
                        <tr>
                            <td>
                                <?php if (!empty($admin['foto_perfil']) && file_exists('../uploads/perfil/' . $admin['foto_perfil'])): ?>
                                    <img src="<?php echo '../uploads/perfil/' . $admin['foto_perfil']; ?>" alt="Foto" class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle me-2 text-secondary" style="font-size: 24px;"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($admin['nome']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $admin['nivel'] === 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                    <?php echo $admin['nivel'] === 'admin' ? 'Administrador' : 'Operador'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $admin['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $admin['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td><?php echo $admin['ultimo_acesso'] ? formataData($admin['ultimo_acesso']) : 'Nunca'; ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalAdmin"
                                        data-id="<?php echo $admin['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($admin['nome']); ?>"
                                        data-email="<?php echo htmlspecialchars($admin['email']); ?>"
                                        data-nivel="<?php echo $admin['nivel']; ?>"
                                        data-ativo="<?php echo $admin['ativo']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                        <a href="?alterarStatus=<?php echo $admin['id']; ?>" class="btn btn-sm btn-outline-<?php echo $admin['ativo'] ? 'warning' : 'success'; ?>" onclick="return confirm('Tem certeza que deseja <?php echo $admin['ativo'] ? 'desativar' : 'ativar'; ?> este administrador?')">
                                            <i class="fas <?php echo $admin['ativo'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </a>

                                        <a href="?excluir=<?php echo $admin['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este administrador? Esta ação não pode ser desfeita.')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($administradores) == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center">Nenhum administrador encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Administrador -->
<div class="modal fade" id="modalAdmin" tabindex="-1" aria-labelledby="modalAdminLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAdminLabel">Novo Administrador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="id" id="admin_id">

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="admin_nome" name="nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="admin_email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="admin_senha" name="senha">
                        <small class="form-text text-muted" id="senha_help">Deixe em branco para manter a senha atual (ao editar).</small>
                    </div>

                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível</label>
                        <select class="form-select" id="admin_nivel" name="nivel">
                            <option value="operador">Operador</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="admin_ativo" name="ativo" value="1" checked>
                        <label class="form-check-label" for="ativo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar modal de edição
        const modalAdmin = document.getElementById('modalAdmin');
        if (modalAdmin) {
            modalAdmin.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const modalTitle = modalAdmin.querySelector('.modal-title');
                const adminIdInput = document.getElementById('admin_id');
                const adminNomeInput = document.getElementById('admin_nome');
                const adminEmailInput = document.getElementById('admin_email');
                const adminSenhaInput = document.getElementById('admin_senha');
                const adminNivelSelect = document.getElementById('admin_nivel');
                const adminAtivoCheck = document.getElementById('admin_ativo');
                const senhaHelp = document.getElementById('senha_help');

                if (id) {
                    // Edição
                    modalTitle.textContent = 'Editar Administrador';
                    adminIdInput.value = id;
                    adminNomeInput.value = button.getAttribute('data-nome');
                    adminEmailInput.value = button.getAttribute('data-email');
                    adminSenhaInput.value = '';
                    adminNivelSelect.value = button.getAttribute('data-nivel');
                    adminAtivoCheck.checked = button.getAttribute('data-ativo') === '1';
                    senhaHelp.style.display = 'block';
                } else {
                    // Adição
                    modalTitle.textContent = 'Novo Administrador';
                    adminIdInput.value = '';
                    adminNomeInput.value = '';
                    adminEmailInput.value = '';
                    adminSenhaInput.value = '';
                    adminNivelSelect.value = 'operador';
                    adminAtivoCheck.checked = true;
                    senhaHelp.style.display = 'none';
                }
            });
        }
    });
</script>

<?php include 'footer.php'; ?>