<?php
require_once '../conexao.php';
verificaLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID não fornecido.</div>';
    exit;
}

$id = (int)$_GET['id'];

// Buscar dados do processo arquivado
$stmt = $pdo->prepare("SELECT * FROM requerimentos_arquivados WHERE id = ?");
$stmt->execute([$id]);
$processo = $stmt->fetch();

if (!$processo) {
    echo '<div class="alert alert-danger">Processo não encontrado.</div>';
    exit;
}

// Buscar dados do administrador que arquivou
$admin = null;
if ($processo['admin_arquivamento']) {
    $stmt = $pdo->prepare("SELECT nome FROM administradores WHERE id = ?");
    $stmt->execute([$processo['admin_arquivamento']]);
    $admin = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-md-6">
        <h6><i class="fas fa-file-alt me-2"></i>Informações do Processo</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Protocolo:</strong></td>
                <td>#<?php echo htmlspecialchars($processo['protocolo']); ?></td>
            </tr>
            <tr>
                <td><strong>Tipo de Alvará:</strong></td>
                <td><?php echo htmlspecialchars($processo['tipo_alvara']); ?></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><span class="badge bg-secondary"><?php echo $processo['status']; ?></span></td>
            </tr>
            <tr>
                <td><strong>Data de Envio:</strong></td>
                <td><?php echo formataData($processo['data_envio']); ?></td>
            </tr>
            <tr>
                <td><strong>Última Atualização:</strong></td>
                <td><?php echo formataData($processo['data_atualizacao']); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6><i class="fas fa-user me-2"></i>Dados do Requerente</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Nome:</strong></td>
                <td><?php echo htmlspecialchars($processo['requerente_nome']); ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo htmlspecialchars($processo['requerente_email']); ?></td>
            </tr>
            <tr>
                <td><strong>CPF/CNPJ:</strong></td>
                <td><?php echo htmlspecialchars($processo['requerente_cpf_cnpj']); ?></td>
            </tr>
            <tr>
                <td><strong>Telefone:</strong></td>
                <td><?php echo htmlspecialchars($processo['requerente_telefone']); ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if (!empty($processo['proprietario_nome'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6><i class="fas fa-home me-2"></i>Dados do Proprietário</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Nome:</strong></td>
                <td><?php echo htmlspecialchars($processo['proprietario_nome']); ?></td>
            </tr>
            <tr>
                <td><strong>CPF/CNPJ:</strong></td>
                <td><?php echo htmlspecialchars($processo['proprietario_cpf_cnpj']); ?></td>
            </tr>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="row mt-3">
    <div class="col-12">
        <h6><i class="fas fa-map-marker-alt me-2"></i>Endereço do Objetivo</h6>
        <p class="text-muted"><?php echo nl2br(htmlspecialchars($processo['endereco_objetivo'])); ?></p>
    </div>
</div>

<?php if (!empty($processo['observacoes'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6><i class="fas fa-sticky-note me-2"></i>Observações</h6>
        <p class="text-muted"><?php echo nl2br(htmlspecialchars($processo['observacoes'])); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="row mt-3">
    <div class="col-12">
        <h6><i class="fas fa-archive me-2"></i>Informações do Arquivamento</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Data do Arquivamento:</strong></td>
                <td><?php echo formataData($processo['data_arquivamento']); ?></td>
            </tr>
            <tr>
                <td><strong>Arquivado por:</strong></td>
                <td><?php echo $admin ? htmlspecialchars($admin['nome']) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td><strong>Motivo:</strong></td>
                <td><?php echo nl2br(htmlspecialchars($processo['motivo_arquivamento'])); ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="alert alert-info mt-3">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Processo Arquivado:</strong> Este requerimento foi removido da lista principal e não aparece mais nas consultas normais.
</div> 