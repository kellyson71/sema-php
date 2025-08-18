<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$arquivo = '../temp/preferencias_ui.json';
$preferencias = [];
$estatisticas = [];

if (file_exists($arquivo)) {
    $conteudo = file_get_contents($arquivo);
    if ($conteudo !== false) {
        $preferencias = json_decode($conteudo, true) ?: [];
    }
}

if (!empty($preferencias)) {
    $likes = 0;
    $dislikes = 0;
    
    foreach ($preferencias as $pref) {
        if ($pref['preferencia'] === 'like') {
            $likes++;
        } else {
            $dislikes++;
        }
    }
    
    $total = $likes + $dislikes;
    $aprovacao = $total > 0 ? round(($likes / $total) * 100, 1) : 0;
    
    $estatisticas = [
        'likes' => $likes,
        'dislikes' => $dislikes,
        'total' => $total,
        'aprovacao' => $aprovacao
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferências UI - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        .btn-voltar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-voltar:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'header.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-bar me-2"></i>Preferências da Interface</h2>
            <a href="index.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </a>
        </div>

        <?php if (empty($preferencias)): ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5>Nenhuma preferência registrada ainda</h5>
                <p class="text-muted">As preferências dos usuários aparecerão aqui quando eles avaliarem a interface.</p>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <div class="stats-number"><?php echo $estatisticas['total']; ?></div>
                        <div class="stats-label">Total de Respostas</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <div class="stats-number"><?php echo $estatisticas['likes']; ?></div>
                        <div class="stats-label"><i class="fas fa-thumbs-up me-1"></i>Gostaram</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                        <div class="stats-number"><?php echo $estatisticas['dislikes']; ?></div>
                        <div class="stats-label"><i class="fas fa-thumbs-down me-1"></i>Prefere Antigo</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                        <div class="stats-number"><?php echo $estatisticas['aprovacao']; ?>%</div>
                        <div class="stats-label">Taxa de Aprovação</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detalhes das Respostas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Preferência</th>
                                    <th>Página</th>
                                    <th>IP</th>
                                    <th>Data/Hora</th>
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($preferencias) as $pref): ?>
                                    <tr>
                                        <td>
                                            <?php if ($pref['preferencia'] === 'like'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-thumbs-up me-1"></i>Gostou
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-thumbs-down me-1"></i>Prefere Antigo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($pref['pagina']); ?></td>
                                        <td><code><?php echo htmlspecialchars($pref['ip_usuario'] ?? 'N/A'); ?></code></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($pref['data_resposta'])); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(substr($pref['user_agent'] ?? 'N/A', 0, 50)); ?>
                                                <?php if (strlen($pref['user_agent'] ?? '') > 50): ?>...<?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
