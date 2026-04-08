<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();

$denuncia_id = filter_input(INPUT_GET, 'denuncia_id', FILTER_VALIDATE_INT);
if (!$denuncia_id) die("Acesso negado: ID da denúncia não fornecido.");

$stmt = $pdo->prepare("SELECT id, infrator_nome, status FROM denuncias WHERE id = ?");
$stmt->execute([$denuncia_id]);
$denuncia = $stmt->fetch();
if (!$denuncia) die("Denúncia não encontrada.");

$titulo_pagina = 'Gerar Documento – Denúncia';
include '../header.php';
?>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --sema-green:    #1c4b36;
            --sema-green-lt: #2a6b50;
            --card-radius:   14px;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .template-card-wrapper { animation: fadeInUp 0.4s ease both; }
        .template-card {
            transition: transform 0.28s cubic-bezier(0.25,0.8,0.25,1),
                        box-shadow 0.28s ease, border-color 0.28s ease;
            cursor: pointer;
            border: 1.5px solid #e5e9f2;
            border-bottom: 3px solid transparent;
            background: #fff;
            border-radius: var(--card-radius);
            height: 100%;
        }
        .template-card:hover {
            transform: translateY(-5px);
            border-bottom-color: var(--sema-green);
            box-shadow: 0 12px 30px rgba(28,75,54,0.13);
        }
        .template-card .icon-wrap {
            width: 58px; height: 58px;
            border-radius: 14px;
            background: #f0fdf4;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            transition: background 0.25s, transform 0.25s;
        }
        .template-card:hover .icon-wrap { background: #d1fae5; transform: scale(1.08) rotate(-3deg); }
        .tpl-badge {
            font-size: 0.68rem; font-weight: 600; padding: 3px 8px;
            border-radius: 20px; letter-spacing: 0.03em; text-transform: uppercase;
        }
        .tpl-badge.notificacao   { background: #fef3c7; color: #92400e; }
        .tpl-badge.tac           { background: #dbeafe; color: #1e40af; }
        .tpl-badge.compromisso   { background: #d1fae5; color: #065f46; }
        .preview-miniature {
            font-size: 0.72rem; color: #64748b; text-align: left;
            background: #f8fafc; padding: 10px 12px; border-radius: 8px;
            height: 68px; overflow: hidden; margin-top: 12px;
            border: 1px dashed #cbd5e1; position: relative; line-height: 1.5;
        }
        .preview-miniature::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 28px; background: linear-gradient(transparent, #f8fafc);
        }
        @keyframes shimmer {
            0%   { background-position: -800px 0; }
            100% { background-position: 800px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f2f5 25%, #e2e5ea 50%, #f0f2f5 75%);
            background-size: 800px 100%; animation: shimmer 1.5s infinite linear;
            border-radius: 8px;
        }
        .skeleton-card { height: 220px; border-radius: var(--card-radius); }
    </style>

    <!-- Navegação de Topo -->
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="../visualizar_denuncia.php?id=<?= $denuncia_id ?>" class="btn btn-sm btn-light border fw-medium px-3 text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-file-signature me-2" style="color:var(--sema-green)"></i>
                    Gerar Documento – Denúncia
                </h5>
                <small class="text-muted">Escolha o modelo de documento a ser gerado para esta denúncia</small>
            </div>
        </div>
        <span class="badge px-3 py-2 rounded-pill fw-semibold"
              style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:.85rem;">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Denúncia #<?= str_pad($denuncia['id'], 6, '0', STR_PAD_LEFT) ?>
        </span>
    </div>

    <!-- Info do infrator -->
    <div class="alert alert-light border d-flex align-items-center gap-3 mb-4 rounded-3">
        <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
             style="width:42px;height:42px;background:#f1f5f9">
            <i class="fas fa-user text-secondary"></i>
        </div>
        <div>
            <div class="fw-bold text-dark"><?= htmlspecialchars($denuncia['infrator_nome']) ?></div>
            <small class="text-muted">Infrator identificado nesta denúncia</small>
        </div>
    </div>

    <!-- Grid de templates -->
    <h6 class="fw-bold text-muted mb-3 text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
        <i class="fas fa-layer-group me-2"></i> Modelos Disponíveis
    </h6>
    <div class="row g-4 mb-4" id="lista-templates">
        <?php for($i=0;$i<3;$i++): ?>
        <div class="col-xl-4 col-md-6">
            <div class="skeleton skeleton-card"></div>
        </div>
        <?php endfor; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    const denunciaId = <?= $denuncia_id ?>;

    const badgeClass = {
        'Notificação': 'notificacao',
        'TAC':         'tac',
        'Compromisso': 'compromisso',
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function buildCard(t, idx) {
        const delay = (idx * 0.1).toFixed(2);
        const bc    = badgeClass[t.badge] || 'tac';
        return `
        <div class="col-xl-4 col-md-6 template-card-wrapper" style="animation-delay:${delay}s">
            <a href="editor_denuncia.php?denuncia_id=${denunciaId}&template=${encodeURIComponent(t.nome)}"
               class="card template-card border-0 shadow-sm h-100 text-decoration-none d-block">
                <div class="card-body text-center p-4">
                    <div class="icon-wrap mb-1">
                        <i class="fas ${t.icon} ${t.cor} fs-2"></i>
                    </div>
                    <span class="tpl-badge ${bc} mb-2 d-inline-block">${escapeHtml(t.badge)}</span>
                    <h6 class="fw-bold text-dark lh-sm mb-1" style="font-size:.9rem">${escapeHtml(t.label)}</h6>
                    <div class="preview-miniature">${escapeHtml(t.preview)}</div>
                </div>
            </a>
        </div>`;
    }

    fetch('../denuncia_doc_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'listar_templates_denuncia' })
    })
    .then(r => r.json())
    .then(ret => {
        const lista = document.getElementById('lista-templates');
        if (ret.success && ret.templates && ret.templates.length > 0) {
            lista.innerHTML = ret.templates.map((t, i) => buildCard(t, i)).join('');
        } else {
            lista.innerHTML = `<div class="col-12"><div class="alert alert-danger rounded-3">
                <i class="fas fa-triangle-exclamation me-2"></i>
                <strong>Nenhum template encontrado.</strong>
            </div></div>`;
        }
    })
    .catch(err => {
        document.getElementById('lista-templates').innerHTML = `
        <div class="col-12"><div class="alert alert-danger rounded-3">
            <i class="fas fa-wifi-slash me-2"></i>
            <strong>Falha na conexão.</strong> ${err.message || ''}
        </div></div>`;
    });
    </script>
<?php include '../footer.php'; ?>
