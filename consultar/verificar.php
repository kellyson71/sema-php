<?php
require_once '../includes/config.php';
require_once '../includes/assinatura_digital_service.php';
require_once '../admin/conexao.php';

$documentoId = trim($_GET['id'] ?? '');
$resultado = null;

if ($documentoId !== '') {
    $assinaturaService = new AssinaturaDigitalService($pdo);
    $resultado = $assinaturaService->verificarDocumento($documentoId);
}

function mascararCpfPublico(?string $cpf): string
{
    $dig = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($dig) !== 11) return '';
    return '***.' . substr($dig, 3, 3) . '.' . substr($dig, 6, 3) . '-**';
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
        body { background: linear-gradient(160deg, #11271c 0%, #1c4b36 60%, #2a6b50 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .verificacao-container { max-width: 760px; margin: 40px auto; padding: 0 16px; }
        .card { border: none; border-radius: 18px; box-shadow: 0 16px 50px rgba(0,0,0,0.3); overflow: hidden; }
        .status-banner { padding: 28px 24px; text-align: center; color: #fff; }
        .status-banner.ok  { background: linear-gradient(135deg, #0d7f5f, #10b981); }
        .status-banner.err { background: linear-gradient(135deg, #b91c1c, #ef4444); }
        .status-banner .status-icon { font-size: 2.6rem; margin-bottom: 10px; }
        .status-banner h4 { font-weight: 700; margin: 0; }
        .status-banner p  { margin: 6px 0 0; opacity: .9; font-size: .9rem; }
        .assinante-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid #e5e9f0; border-radius: 12px; margin-bottom: 10px; background: #fafcfb; }
        .assinante-item .av { width: 42px; height: 42px; border-radius: 50%; background: #1c4b36; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
        .assinante-item .badge-nivel { font-size: .68rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
        .badge-avancada { background: #d1fae5; color: #065f46; }
        .badge-simples  { background: #fef3c7; color: #92400e; }
        .hash-display { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.72rem; word-break: break-all; background: #f1f5f9; border-radius: 8px; padding: 10px 12px; color: #475569; }
        .info-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 3px; }
        .btn-sema { background: #1c4b36; border-color: #1c4b36; color: #fff; }
        .btn-sema:hover { background: #2a6b50; border-color: #2a6b50; color: #fff; }
    </style>
</head>
<body>
    <div class="verificacao-container">
        <div class="card">
            <div class="card-body p-0">
                <div class="text-center pt-4 pb-3 px-4">
                    <img src="../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png" alt="SEMA" style="max-width: 220px;">
                    <h5 class="mt-3 mb-1 fw-bold">Verificação de Autenticidade</h5>
                    <p class="text-muted small mb-0">Assinatura Eletrônica — Lei nº 14.063/2020</p>
                </div>

                <?php if ($resultado === null): ?>
                    <div class="px-4 pb-4">
                        <form method="GET" class="mb-2">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white"><i class="fas fa-qrcode text-secondary"></i></span>
                                <input type="text" name="id" class="form-control"
                                       placeholder="Código do documento" required>
                                <button type="submit" class="btn btn-sema px-4">
                                    <i class="fas fa-search me-2"></i>Verificar
                                </button>
                            </div>
                        </form>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-circle-info me-1"></i>
                            O código está impresso no rodapé do documento, junto ao QR code.
                        </p>
                    </div>
                <?php else: ?>
                    <?php if ($resultado['valido']): ?>
                        <div class="status-banner ok">
                            <div class="status-icon"><i class="fas fa-circle-check"></i></div>
                            <h4>Documento Autêntico</h4>
                            <p>As assinaturas eletrônicas e a integridade do arquivo foram verificadas com sucesso.</p>
                        </div>

                        <div class="p-4">
                            <div class="info-label"><i class="fas fa-users me-1"></i> Assinado eletronicamente por</div>
                            <div class="mt-2 mb-4">
                                <?php foreach ($resultado['assinantes'] as $a):
                                    $iniciais = strtoupper(mb_substr(trim($a['nome']), 0, 1));
                                    $cpfM = mascararCpfPublico($a['cpf']);
                                ?>
                                <div class="assinante-item">
                                    <div class="av"><?php echo htmlspecialchars($iniciais); ?></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold" style="font-size:.92rem;"><?php echo htmlspecialchars($a['nome']); ?></div>
                                        <div class="text-muted" style="font-size:.78rem;">
                                            <?php echo htmlspecialchars($a['cargo'] ?? ''); ?>
                                            <?php if ($cpfM): ?> &middot; CPF <?php echo $cpfM; ?><?php endif; ?>
                                            &middot; <?php echo date('d/m/Y H:i', strtotime($a['data'])); ?>
                                        </div>
                                    </div>
                                    <?php if ($a['nivel'] === 'avancada'): ?>
                                        <span class="badge-nivel badge-avancada"><i class="fas fa-shield-halved me-1"></i>Avançada</span>
                                    <?php else: ?>
                                        <span class="badge-nivel badge-simples"><i class="fas fa-file-circle-check me-1"></i>Registro eletrônico</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="info-label"><i class="fas fa-file-lines me-1"></i> Tipo de documento</div>
                                    <div style="font-size:.9rem;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $resultado['dados']['tipo_documento']))); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label"><i class="fas fa-hashtag me-1"></i> Processo</div>
                                    <div style="font-size:.9rem;">#<?php echo (int) $resultado['dados']['requerimento_id']; ?></div>
                                </div>
                            </div>

                            <div class="info-label"><i class="fas fa-fingerprint me-1"></i> Impressão digital do arquivo (SHA-256)</div>
                            <div class="hash-display mb-3"><?php echo htmlspecialchars($resultado['dados']['hash_documento']); ?></div>

                            <div class="info-label"><i class="fas fa-key me-1"></i> Código do documento</div>
                            <div class="hash-display mb-4"><?php echo htmlspecialchars($resultado['dados']['documento_id']); ?></div>

                            <div class="d-grid">
                                <a href="baixar.php?id=<?php echo urlencode($resultado['dados']['documento_id']); ?>"
                                   target="_blank" class="btn btn-sema btn-lg">
                                    <i class="fas fa-file-pdf me-2"></i>Visualizar Documento Original
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="status-banner err">
                            <div class="status-icon"><i class="fas fa-circle-xmark"></i></div>
                            <h4>Documento Inválido ou Adulterado</h4>
                            <p><?php echo htmlspecialchars($resultado['erro']); ?></p>
                        </div>

                        <div class="p-4">
                            <?php if (isset($resultado['dados'])): ?>
                                <div class="alert alert-warning mb-3" style="font-size:.85rem;">
                                    <div class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i> Informações do registro original</div>
                                    Assinado por: <?php echo htmlspecialchars($resultado['dados']['assinante_nome']); ?><br>
                                    Data: <?php echo date('d/m/Y H:i', strtotime($resultado['dados']['timestamp_assinatura'])); ?>
                                </div>
                            <?php endif; ?>
                            <p class="text-muted small mb-0">
                                Se você recebeu este documento de terceiros, ele pode ter sido alterado.
                                Em caso de dúvida, entre em contato com a Secretaria Municipal de Meio Ambiente.
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="text-center pb-4">
                        <a href="verificar.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-rotate-left me-2"></i>Verificar outro documento
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-white mt-4 opacity-75">
            <small>
                <i class="fas fa-shield-halved me-2"></i>
                Assinatura individual RSA-2048 por servidor &middot; integridade SHA-256<br>
                Prefeitura Municipal de Pau dos Ferros/RN — SEMA
            </small>
        </div>
    </div>
</body>
</html>
