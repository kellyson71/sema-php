<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();

$requerimento_id = filter_input(INPUT_GET, 'requerimento_id', FILTER_VALIDATE_INT);
if (!$requerimento_id) die("Acesso Negado: ID do requerimento não fornecido.");

$stmt = $pdo->prepare("SELECT protocolo, status FROM requerimentos WHERE id = ?");
$stmt->execute([$requerimento_id]);
$req = $stmt->fetch();
if (!$req) die("Erro: Requerimento não encontrado.");

$titulo_pagina = 'Selecionar Template';
include '../header.php';
?>
    <!-- Assets Extras Específicos do Gerador -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════
           VARIÁVEIS E BASE
        ═══════════════════════════════════════════════ */
        :root {
            --sema-green:    #1c4b36;
            --sema-green-lt: #2a6b50;
            --sema-teal:     #0d7f5f;
            --card-radius:   14px;
        }

        /* ═══════════════════════════════════════════════
           SKELETON LOADING
        ═══════════════════════════════════════════════ */
        @keyframes shimmer {
            0%   { background-position: -800px 0; }
            100% { background-position: 800px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f2f5 25%, #e2e5ea 50%, #f0f2f5 75%);
            background-size: 800px 100%;
            animation: shimmer 1.5s infinite linear;
            border-radius: 8px;
        }
        .skeleton-card {
            height: 220px;
            border-radius: var(--card-radius);
        }

        /* ═══════════════════════════════════════════════
           ANIMAÇÃO DE ENTRADA DOS CARDS
        ═══════════════════════════════════════════════ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .template-card-wrapper {
            animation: fadeInUp 0.4s ease both;
        }

        /* ═══════════════════════════════════════════════
           CARD DE TEMPLATE
        ═══════════════════════════════════════════════ */
        .template-card {
            transition: transform 0.28s cubic-bezier(0.25, 0.8, 0.25, 1),
                        box-shadow 0.28s ease,
                        border-color 0.28s ease;
            cursor: pointer;
            border: 1.5px solid #e5e9f2;
            border-bottom: 3px solid transparent;
            background: #fff;
            border-radius: var(--card-radius);
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .template-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(28,75,54,0.04) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.28s;
        }
        .template-card:hover {
            transform: translateY(-5px);
            border-bottom-color: var(--sema-green);
            box-shadow: 0 12px 30px rgba(28, 75, 54, 0.13);
        }
        .template-card:hover::before { opacity: 1; }

        .template-card .icon-wrap {
            width: 58px; height: 58px;
            border-radius: 14px;
            background: #f0fdf4;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            transition: background 0.25s, transform 0.25s;
        }
        .template-card:hover .icon-wrap {
            background: #d1fae5;
            transform: scale(1.08) rotate(-3deg);
        }
        .template-card .icon-wrap i { font-size: 1.55rem; }

        /* Badge de categoria */
        .tpl-badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .tpl-badge.ambiental  { background: #d1fae5; color: #065f46; }
        .tpl-badge.construcao { background: #fef3c7; color: #92400e; }
        .tpl-badge.habite     { background: #dbeafe; color: #1e40af; }
        .tpl-badge.licenca    { background: #ede9fe; color: #5b21b6; }
        .tpl-badge.economico  { background: #fef9c3; color: #854d0e; }
        .tpl-badge.livre      { background: #f1f5f9; color: #475569; }
        .tpl-badge.parecer    { background: #f1f5f9; color: #475569; }
        .tpl-badge.desmembramento { background: #cffafe; color: #155e75; }

        /* Destaque para templates de fiscalização de obras */
        .template-card.border-warning {
            border-color: #f59e0b !important;
            border-bottom-color: #f59e0b !important;
        }
        .template-card.border-warning:hover {
            border-bottom-color: #d97706 !important;
            box-shadow: 0 12px 30px rgba(245, 158, 11, 0.18);
        }

        /* Preview de texto do template */
        .preview-miniature {
            font-size: 0.72rem;
            color: #64748b;
            text-align: left;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 8px;
            height: 68px;
            overflow: hidden;
            margin-top: 12px;
            border: 1px dashed #cbd5e1;
            position: relative;
            line-height: 1.5;
        }
        .preview-miniature::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 28px;
            background: linear-gradient(transparent, #f8fafc);
        }

        /* ═══════════════════════════════════════════════
           CARD DE HISTÓRICO
        ═══════════════════════════════════════════════ */
        .hist-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            border: 1.5px solid #e5e9f2;
            border-left: 4px solid var(--sema-green) !important;
            border-radius: var(--card-radius);
            background: #fff;
            cursor: pointer;
        }
        .hist-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(28, 75, 54, 0.1);
        }
        .hist-icon-wrap {
            width: 42px; height: 42px;
            border-radius: 10px;
            background: #f0fdf4;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        /* ═══════════════════════════════════════════════
           ABAS DE TEMPLATES
        ═══════════════════════════════════════════════ */
        #tabsTemplates {
            border-bottom: 2px solid #e2e8f0;
        }
        #tabsTemplates .nav-link {
            color: #64748b;
            border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            padding: 10px 18px;
            border-radius: 0;
            transition: color 0.2s, border-color 0.2s;
        }
        #tabsTemplates .nav-link:hover {
            color: var(--sema-green);
            background: transparent;
        }
        #tabsTemplates .nav-link.active {
            color: var(--sema-green);
            border-bottom-color: var(--sema-green);
            background: transparent;
            font-weight: 700;
        }
    </style>

    <!-- Navegação de Topo -->
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="../visualizar_requerimento.php?id=<?= $requerimento_id ?>" class="btn btn-sm btn-light border fw-medium px-3 text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-file-signature me-2" style="color: var(--sema-green)"></i> Selecionar Template
                </h5>
                <small class="text-muted">Escolha um modelo para gerar o documento oficial do processo</small>
            </div>
        </div>
        <span class="badge px-3 py-2 rounded-pill fw-semibold" style="background: #f0fdf4; color: var(--sema-green); border: 1px solid #bbf7d0; font-size: 0.85rem;">
            <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($req['protocolo']) ?>
        </span>
    </div>

    <!-- Abas de navegação -->
    <ul class="nav nav-tabs mb-4" id="tabsTemplates" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-meus" data-bs-toggle="tab"
                    data-bs-target="#pane-meus" type="button" role="tab">
                <i class="fas fa-bookmark me-2 text-success"></i> Meus Modelos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold active" id="tab-todos" data-bs-toggle="tab"
                    data-bs-target="#pane-todos" type="button" role="tab">
                <i class="fas fa-layer-group me-2 text-success"></i> Todos os Modelos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-historico" data-bs-toggle="tab"
                    data-bs-target="#pane-historico" type="button" role="tab">
                <i class="fas fa-history me-2 text-warning"></i> Histórico
            </button>
        </li>
    </ul>

    <div class="tab-content" id="tabsTemplatesContent">

        <!-- Aba: Meus Modelos -->
        <div class="tab-pane fade" id="pane-meus" role="tabpanel">
            <div class="row g-4 mb-4" id="lista-meus-templates">
                <div class="col-12">
                    <div class="text-center text-muted py-3">
                        <div class="spinner-border spinner-border-sm me-2 text-secondary" role="status"></div>
                        <small>Carregando seus modelos...</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba: Todos os Modelos -->
        <div class="tab-pane fade show active" id="pane-todos" role="tabpanel">
            <div class="row g-4 mb-4" id="lista-templates">
                <?php for($i=0;$i<6;$i++): ?>
                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="skeleton skeleton-card"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Aba: Histórico -->
        <div class="tab-pane fade" id="pane-historico" role="tabpanel">
            <div class="row g-3 mb-4" id="lista-historico">
                <div class="col-12">
                    <div class="text-center text-muted py-3">
                        <div class="spinner-border spinner-border-sm me-2 text-secondary" role="status"></div>
                        <small>Verificando histórico...</small>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    const reqId = <?= $requerimento_id ?>;
    const adminNivel = <?= json_encode($_SESSION['admin_nivel'] ?? '') ?>;
    let favoritosSet = new Set(); // nomes dos templates favoritados

    /* ─── Helpers de badge ─────────────────────────────────── */
    function badgeClass(badge) {
        const map = {
            'Ambiental':      'ambiental',
            'Construção':     'construcao',
            'Habite-se':      'habite',
            'Licença':        'licenca',
            'Econômico':      'economico',
            'Livre':          'livre',
            'Desmembramento': 'desmembramento',
        };
        return map[badge] || 'parecer';
    }

    function tipoFromBadge(badge) {
        const map = {
            'Ambiental':      'ambiental',
            'Construção':     'construcao',
            'Habite-se':      'habite_se',
            'Licença':        'licenca',
            'Econômico':      'economico',
            'Livre':          'livre',
            'Desmembramento': 'desmembramento',
        };
        return map[badge] || '';
    }

    function escaparAttr(str) {
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ─── Montar HTML de um card de template ───────────────── */
    function buildCardTemplate(t, idx) {
        const nome    = t.nome  || t;
        const label   = t.label_amigavel || nome.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const desc    = t.descricao  || 'Modelo padrão oficial da secretaria.';
        const icone   = t.icone      || 'fa-file-signature';
        const cor     = t.icone_cor  || 'text-secondary';
        const badge   = t.badge      || 'Parecer';
        const preview = t.preview    || desc;
        const delay   = (idx * 0.06).toFixed(2);
        const isFav   = favoritosSet.has(nome);
        const favIcon = isFav ? 'fas fa-star text-warning' : 'far fa-star text-muted';
        const favTitle= isFav ? 'Remover dos Meus Modelos' : 'Adicionar aos Meus Modelos';

        return `
        <div class="col-xl-3 col-md-4 col-sm-6 template-card-wrapper" id="tpl-card-${escapeHtml(nome)}" style="animation-delay:${delay}s">
            <div class="card template-card border-0 shadow-sm h-100 position-relative">
                <button class="btn btn-sm position-absolute top-0 end-0 m-2 p-1 border-0 bg-transparent"
                        style="z-index:2;line-height:1" title="${favTitle}"
                        onclick="toggleFavorito('${escaparAttr(nome)}', this)">
                    <i class="${favIcon}" style="font-size:1rem"></i>
                </button>
                <a href="editor.php?requerimento_id=${reqId}&template=${encodeURIComponent(nome)}"
                   class="card-body text-center p-4 text-decoration-none d-block"
                   title="${escaparAttr(desc)}">
                    <div class="icon-wrap mb-1">
                        <i class="fas ${icone} ${cor} fs-2"></i>
                    </div>
                    <span class="tpl-badge ${badgeClass(badge)} mb-2 d-inline-block">${badge}</span>
                    <h6 class="fw-bold text-dark lh-sm mb-1" style="font-size:.85rem">${label}</h6>
                    <div class="preview-miniature">${escapeHtml(preview)}</div>
                </a>
            </div>
        </div>`;
    }

    /* ─── Toggle favorito ──────────────────────────────────── */
    function toggleFavorito(nome, btn) {
        btn.disabled = true;
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'favoritar_template', template_nome: nome })
        })
        .then(r => r.json())
        .then(ret => {
            if (!ret.success) { btn.disabled = false; return; }
            const icon = btn.querySelector('i');
            if (ret.favoritado) {
                favoritosSet.add(nome);
                icon.className = 'fas fa-star text-warning';
                btn.title = 'Remover dos Meus Modelos';
            } else {
                favoritosSet.delete(nome);
                icon.className = 'far fa-star text-muted';
                btn.title = 'Adicionar aos Meus Modelos';
            }
            btn.disabled = false;
            // Recarregar aba "Meus Modelos" se estiver visível
            if (document.getElementById('tab-meus').classList.contains('active')) {
                carregarTemplates();
            }
        })
        .catch(() => { btn.disabled = false; });
    }

    /* ─── Montar HTML de um card de histórico ───────────────── */
    function buildHistCard(h, idx) {
        const nome      = h.label || h.nome || 'Documento';
        const isDb      = h.origem === 'db';
        const icone     = isDb ? 'fa-pen-to-square' : 'fa-file-pdf';
        const cor       = isDb ? 'text-primary'  : 'text-danger';
        const tipoBadge = isDb
            ? '<span class="badge bg-primary-subtle text-primary" style="font-size:.65rem">Rascunho</span>'
            : '<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem">Assinado</span>';
        const delay     = (idx * 0.07).toFixed(2);
        const labelEnc  = encodeURIComponent(h.label || h.nome || 'Documento');

        return `
        <div class="col-xl-3 col-md-4 col-sm-6 template-card-wrapper" style="animation-delay:${delay}s">
            <a href="editor.php?requerimento_id=${reqId}&template=${encodeURIComponent(h.id)}&label=${labelEnc}"
               class="card hist-card shadow-sm border-0 text-decoration-none">
                <div class="card-body p-3 d-flex align-items-start gap-3">
                    <div class="hist-icon-wrap">
                        <i class="fas ${icone} ${cor}"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            ${tipoBadge}
                        </div>
                        <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size:.82rem" title="${escaparAttr(nome)}">${escapeHtml(nome)}</h6>
                        <small class="text-muted d-block">${h.data}</small>
                        <small class="text-success fw-medium" style="font-size:.7rem">
                            <i class="fas fa-copy me-1"></i> Usar como base
                        </small>
                    </div>
                </div>
            </a>
        </div>`;
    }

    /* ─── Card de template do usuário (personalizado ou favorito) */
    function buildUserTemplateCard(t, idx) {
        const delay = (idx * 0.06).toFixed(2);
        const label = escapeHtml(t.nome);
        const desc  = escapeHtml(t.descricao || '');
        const isFav = t.tipo === 'favorito';

        if (isFav) {
            // Card de favorito — igual ao card padrão mas com estrela preenchida e sem botão excluir
            const icone = t.icone || 'fa-file-signature';
            const cor   = t.icone_cor || 'text-secondary';
            const badge = t.badge || 'Parecer';
            const preview = escapeHtml(t.preview || t.descricao || '');
            return `
            <div class="col-xl-3 col-md-4 col-sm-6 template-card-wrapper" style="animation-delay:${delay}s">
                <div class="card template-card border-0 shadow-sm h-100 position-relative" style="border-bottom:3px solid #f59e0b !important;">
                    <button class="btn btn-sm position-absolute top-0 end-0 m-2 p-1 border-0 bg-transparent"
                            style="z-index:2;line-height:1" title="Remover dos Meus Modelos"
                            onclick="toggleFavorito('${escaparAttr(t.nome)}', this)">
                        <i class="fas fa-star text-warning" style="font-size:1rem"></i>
                    </button>
                    <a href="editor.php?requerimento_id=${reqId}&template=${encodeURIComponent(t.nome)}"
                       class="card-body text-center p-4 text-decoration-none d-block">
                        <div class="icon-wrap mb-1" style="background:#fef3c7;">
                            <i class="fas ${icone} ${cor} fs-2"></i>
                        </div>
                        <span class="tpl-badge construcao mb-2 d-inline-block" style="background:#fef3c7;color:#92400e;">Favorito</span>
                        <h6 class="fw-bold text-dark lh-sm mb-1" style="font-size:.85rem">${label}</h6>
                        <div class="preview-miniature">${preview}</div>
                    </a>
                </div>
            </div>`;
        }

        // Card personalizado
        const icone    = t.icone || 'fa-bookmark';
        const tplId    = encodeURIComponent('user_tpl:' + t.id);
        const labelEnc = encodeURIComponent(t.nome);
        return `
        <div class="col-xl-3 col-md-4 col-sm-6 template-card-wrapper" id="user-tpl-${t.id}" style="animation-delay:${delay}s">
            <div class="card template-card border-0 shadow-sm h-100 position-relative" style="border-bottom:3px solid #1c4b36 !important;">
                <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle p-0 d-flex align-items-center justify-content-center"
                        style="width:22px;height:22px;font-size:.7rem;z-index:2"
                        title="Excluir modelo" onclick="excluirTemplateUsuario(${t.id}, event)">
                    <i class="fas fa-times"></i>
                </button>
                <a href="editor.php?requerimento_id=${reqId}&template=${tplId}&label=${labelEnc}"
                   class="card-body text-center p-4 text-decoration-none d-block">
                    <div class="icon-wrap mb-1" style="background:#d1fae5;">
                        <i class="fas ${icone} text-success fs-2"></i>
                    </div>
                    <span class="tpl-badge mb-2 d-inline-block" style="background:#d1fae5;color:#065f46;">Personalizado</span>
                    <h6 class="fw-bold text-dark lh-sm mb-1" style="font-size:.85rem">${label}</h6>
                    <div class="preview-miniature">${desc}</div>
                    <small class="text-muted d-block mt-1" style="font-size:.7rem">${t.data || ''}</small>
                </a>
            </div>
        </div>`;
    }

    /* ─── Excluir template do usuário ────────────────────────── */
    function excluirTemplateUsuario(id, evt) {
        evt.preventDefault(); evt.stopPropagation();
        if (!confirm('Excluir este template? Esta ação não pode ser desfeita.')) return;
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'excluir_template_usuario', id: id })
        })
        .then(r => r.json())
        .then(ret => {
            if (ret.success) {
                const card = document.getElementById('user-tpl-' + id);
                if (card) card.remove();
                // Se ficou vazio, exibir estado vazio
                const lista = document.getElementById('lista-meus-templates');
                if (lista && lista.children.length === 0) {
                    lista.innerHTML = emptyStateMeusTemplates();
                }
            }
        });
    }

    function emptyStateMeusTemplates() {
        return `<div class="col-12">
            <div class="text-center py-5" style="color:#94a3b8">
                <i class="fas fa-bookmark fa-3x mb-3 d-block" style="opacity:.3"></i>
                <p class="fw-semibold mb-1">Você ainda não tem templates personalizados.</p>
                <small>Abra um template, edite-o e clique em <strong>"Salvar Template"</strong> no editor para criá-los.</small>
            </div>
        </div>`;
    }

    /* ─── Carregar templates via AJAX ──────────────────────── */
    function carregarTemplates() {
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 'action': 'listar_templates', 'requerimento_id': reqId })
        })
        .then(res => res.json())
        .then(ret => {
            const listTpl  = document.getElementById('lista-templates');
            const listHist = document.getElementById('lista-historico');
            const listMeus = document.getElementById('lista-meus-templates');

            // ── Carregar favoritos no Set ────────────────────
            if (ret.favoritos) favoritosSet = new Set(ret.favoritos);

            // ── Todos os Modelos ─────────────────────────────
            if (ret.success && ret.templates && ret.templates.length > 0) {
                listTpl.innerHTML = ret.templates.map((t, i) => buildCardTemplate(t, i)).join('');
            } else {
                listTpl.innerHTML = `<div class="col-12"><div class="alert alert-danger d-flex align-items-center gap-3 rounded-3">
                    <i class="fas fa-triangle-exclamation fs-4"></i>
                    <div><strong>Falha ao carregar os modelos.</strong><br>
                    <small class="text-muted">${ret.error || 'Nenhum template encontrado.'}</small></div>
                </div></div>`;
            }

            // ── Meus Modelos (favoritos + personalizados) ───
            if (ret.user_templates && ret.user_templates.length > 0) {
                listMeus.innerHTML = ret.user_templates.map((t, i) => buildUserTemplateCard(t, i)).join('');
                // Ativar a aba "Meus Modelos" como padrão se houver algo
                document.getElementById('tab-meus').click();
            } else {
                listMeus.innerHTML = emptyStateMeusTemplates();
            }

            // ── Histórico ────────────────────────────────────
            if (ret.historico_recente && ret.historico_recente.length > 0) {
                listHist.innerHTML = ret.historico_recente.map((h, i) => buildHistCard(h, i)).join('');
            } else {
                listHist.innerHTML = `<div class="col-12"><div class="text-muted py-2 d-flex align-items-center gap-2">
                    <i class="fas fa-inbox text-secondary"></i>
                    <small>Nenhum documento anterior encontrado neste processo.</small>
                </div></div>`;
            }
        })
        .catch(err => {
            document.getElementById('lista-templates').innerHTML = `
            <div class="col-12"><div class="alert alert-danger rounded-3">
                <i class="fas fa-wifi-slash me-2"></i>
                <strong>Falha na conexão.</strong>
                <br><small class="text-muted">${err.message || ''}</small>
            </div></div>`;
        });
    }

    document.addEventListener('DOMContentLoaded', carregarTemplates);
    </script>
<?php include '../footer.php'; ?>
