<?php
require_once '../includes/config.php';
require_once '../includes/assinatura_digital_service.php';
require_once '../admin/conexao.php';

$documentoId = $_GET['id'] ?? '';
$resultado = null;

if (!empty($documentoId)) {
    $assinaturaService = new AssinaturaDigitalService($pdo);
    $resultado = $assinaturaService->verificarDocumento($documentoId);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Documento - SEMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .verificacao-container { max-width: 800px; margin: 50px auto; }
        .card { border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .badge-valido { background: #10b981; }
        .badge-invalido { background: #ef4444; }
        .hash-display { font-family: monospace; font-size: 0.8rem; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container verificacao-container">
        <div class="card">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <img src="../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png" alt="SEMA" style="max-width: 250px;">
                    <h2 class="mt-3">Verificação de Autenticidade</h2>
                    <p class="text-muted">Sistema de Assinatura Digital - Lei 14.063/2020</p>
                </div>

                <?php if ($resultado === null): ?>
                    <form method="GET" class="mb-4">
                        <div class="input-group input-group-lg">
                            <input type="text" name="id" class="form-control"
                                   placeholder="Digite o ID do documento" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Verificar
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php if ($resultado['valido']): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h4>✅ Documento Autêntico e Válido</h4>
                            <p class="mb-0">Este documento foi assinado digitalmente e sua integridade foi verificada com sucesso.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user me-2"></i>Assinante</h6>
                                <p><strong><?php echo htmlspecialchars($resultado['dados']['assinante_nome']); ?></strong></p>
                                <?php if (!empty($resultado['dados']['assinante_cpf'])): ?>
                                    <p>CPF: <?php echo htmlspecialchars($resultado['dados']['assinante_cpf']); ?></p>
                                <?php endif; ?>
                                <p>Cargo: <?php echo htmlspecialchars($resultado['dados']['assinante_cargo']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-calendar me-2"></i>Data da Assinatura</h6>
                                <p><?php echo date('d/m/Y H:i:s', strtotime($resultado['dados']['timestamp_assinatura'])); ?></p>

                                <h6><i class="fas fa-file-alt me-2"></i>Tipo de Documento</h6>
                                <p><?php echo ucfirst($resultado['dados']['tipo_documento']); ?></p>
                            </div>
                        </div>

                        <hr>

                        <h6><i class="fas fa-fingerprint me-2"></i>Hash SHA-256 do Documento</h6>
                        <p class="hash-display bg-light p-2 rounded"><?php echo $resultado['dados']['hash_documento']; ?></p>

                        <h6><i class="fas fa-key me-2"></i>ID do Documento</h6>
                        <p class="hash-display bg-light p-2 rounded"><?php echo $resultado['dados']['documento_id']; ?></p>

                        <hr>

                        <div class="text-center">
                            <a href="<?php echo htmlspecialchars('../admin/parecer_viewer.php?id=' . $resultado['dados']['documento_id']); ?>"
                               target="_blank"
                               class="btn btn-primary btn-lg">
                                <i class="fas fa-file-pdf me-2"></i>Ver Documento Original
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-times-circle fa-3x mb-3"></i>
                            <h4>❌ Documento Inválido ou Adulterado</h4>
                            <p class="mb-0"><?php echo htmlspecialchars($resultado['erro']); ?></p>
                        </div>

                        <?php if (isset($resultado['dados'])): ?>
                            <div class="alert alert-warning">
                                <strong>Informações do Registro Original:</strong><br>
                                Assinado por: <?php echo htmlspecialchars($resultado['dados']['assinante_nome']); ?><br>
                                Data: <?php echo date('d/m/Y H:i', strtotime($resultado['dados']['timestamp_assinatura'])); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="verificar.php" class="btn btn-outline-primary">
                            <i class="fas fa-redo me-2"></i>Verificar Outro Documento
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-white mt-4">
            <small>
                <i class="fas fa-shield-alt me-2"></i>
                Sistema protegido por criptografia RSA-2048 e hash SHA-256<br>
                Conforme Lei 14.063/2020 - Assinatura Eletrônica Avançada
            </small>
        </div>
    </div>
</body>
</html>

