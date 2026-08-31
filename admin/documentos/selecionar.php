<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();

$requerimento_id = filter_input(INPUT_GET, 'requerimento_id', FILTER_VALIDATE_INT);
if (!$requerimento_id) die("Acesso Negado: ID do requerimento não fornecido.");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// O nome do requerente vive em `requerentes`, não em `requerimentos` — mesmo
// JOIN que admin/requerimentos.php faz. LEFT JOIN porque o cabeçalho é só
// informativo: se o vínculo faltar, a tela abre sem o nome em vez de morrer.
$stmt = $pdo->prepare("
    SELECT r.protocolo, r.status, r.setor_atual, r.tipo_alvara,
           req.nome AS requerente_nome
    FROM requerimentos r
    LEFT JOIN requerentes req ON r.requerente_id = req.id
    WHERE r.id = ?
");
$stmt->execute([$requerimento_id]);
$req = $stmt->fetch();
if (!$req) die("Erro: Requerimento não encontrado.");
$setorReq = $req['setor_atual'] ?? 'setor1';

require_once __DIR__ . '/../../tipos_alvara.php';

// Subtítulo do cabeçalho: "Alvará de Construção · Maria Nogueira", como no
// redesenho. tipos_alvara.php dá o nome bonito; sem ele, cai no slug tratado.
$tipoAlvaraNome = $tipos_alvara[$req['tipo_alvara']]['nome']
    ?? ucwords(str_replace('_', ' ', (string) $req['tipo_alvara']));

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

        /* ═══════════════════════════════════════════════
           CARD COM MELHOR ENCAIXE / MODELO RECOMENDADO
        ═══════════════════════════════════════════════ */
        .template-card.melhor-match,
        .tpl-card.melhor {
            border: 2px solid #16a34a !important;
            border-bottom: 3.5px solid var(--sema-green, #1c4b36) !important;
            box-shadow: 0 6px 20px rgba(22, 163, 74, 0.12) !important;
        }
        .template-card.melhor-match:hover,
        .tpl-card.melhor:hover {
            border-color: var(--sema-green, #1c4b36) !important;
            box-shadow: 0 10px 26px rgba(22, 163, 74, 0.18) !important;
            transform: translateY(-4px);
        }
        .tpl-card.melhor .tpl-melhor {
            background: var(--sema-green, #1c4b36);
            color: #ffffff;
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        /* Preview renderizada via iframe — miniatura centralizada */

        /* ═══════════════════════════════════════════════
           MODAL — Preview como folha de papel
        ═══════════════════════════════════════════════ */
        .preview-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30,30,40,0.6);
            z-index: 9999;
            justify-content: center;
            align-items: flex-start;
            padding: 30px 20px;
            overflow-y: auto;
            backdrop-filter: blur(4px);
        }
        .preview-modal-overlay.active { display: flex; }

        .preview-modal-box {
            background: #e8e8e8;
            border-radius: 12px;
            width: 100%;
            max-width: 780px;
            display: flex;
            flex-direction: column;
            animation: fadeInUp 0.35s ease;
            overflow: hidden;
        }

        /* Barra de topo do modal */
        .preview-modal-header {
            background: #2d2d2d;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .preview-modal-header h6 {
            margin: 0;
            font-weight: 600;
            color: #fff;
            font-size: 0.85rem;
        }
        .preview-modal-close {
            width: 28px; height: 28px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.15);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 0.75rem;
        }
        .preview-modal-close:hover { background: rgba(255,255,255,0.3); }

        /* Área da "folha" de papel */
        .preview-modal-body {
            padding: 30px;
            display: flex;
            justify-content: center;
        }
        .preview-paper {
            background: #fff;
            width: 100%;
            max-width: 700px;
            min-height: 900px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
            border-radius: 2px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Timbre (header da folha) */
        .preview-paper-header {
            padding: 20px 30px 12px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 2px solid #2d8661;
        }
        .preview-paper-header img {
            height: 50px;
            width: auto;
            flex-shrink: 0;
        }
        .preview-paper-header .header-text {
            display: flex;
            flex-direction: column;
        }
        .preview-paper-header .header-text strong {
            font-size: 0.82rem;
            color: #1a1a1a;
            letter-spacing: 0.01em;
        }
        .preview-paper-header .header-text small {
            font-size: 0.7rem;
            color: #666;
            font-weight: 600;
        }

        /* Conteúdo da folha (iframe) */
        .preview-paper-content {
            flex: 1;
            padding: 0;
        }
        .preview-paper-content iframe {
            width: 100%;
            min-height: 800px;
            border: none;
            display: block;
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
    </style>

    <!-- ══════════════════════════════════════════════════
         Cabeçalho do redesenho: trilha + "Escolher modelo" + stepper.
         O stepper é estático nesta tela — aqui é sempre o passo 1;
         editor.php acende o 2 e o modal de assinatura, o 3.
    ══════════════════════════════════════════════════ -->
    <div class="proc-crumb">
        <a href="../visualizar_requerimento.php?id=<?= $requerimento_id ?>">
            <i class="fas fa-arrow-left" style="font-size:.72rem"></i> Voltar ao processo
        </a>
        <span class="proc-crumb-sep">/</span>
        <span class="proc-crumb-proto">#<?= htmlspecialchars($req['protocolo']) ?></span>
        <span class="proc-crumb-sep">/</span>
        <span>Gerar documento</span>
    </div>

    <div class="doc-head">
        <div class="doc-head-main">
            <h2>Escolher modelo</h2>
            <p>
                <?= htmlspecialchars($tipoAlvaraNome) ?>
                <?php if (!empty($req['requerente_nome'])): ?>
                    · <?= htmlspecialchars($req['requerente_nome']) ?>
                <?php endif; ?>
            </p>
        </div>
        <ol class="doc-stepper" aria-label="Etapas para emitir o documento">
            <li class="doc-step ativo"><span class="doc-step-num">1</span>Modelo</li>
            <li class="doc-step-fio" aria-hidden="true"></li>
            <li class="doc-step"><span class="doc-step-num">2</span>Editar</li>
            <li class="doc-step-fio" aria-hidden="true"></li>
            <li class="doc-step"><span class="doc-step-num">3</span>Assinar</li>
        </ol>
    </div>

    <div class="doc-layout">
      <div class="doc-col-principal">

    <!-- Seção: Meus Modelos (topo, visível apenas se houver) -->
    <div id="secao-meus-modelos" style="display:none;margin-bottom:24px;">
        <p style="font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#888;margin-bottom:10px;">
            <i class="fas fa-bookmark me-1 text-warning"></i>Meus Modelos
        </p>
        <div class="row g-3 mb-2" id="lista-meus-templates"></div>
    </div>

    <!-- Busca + filtros por categoria (chips), como no redesenho -->
    <div class="doc-filtros">
        <div class="doc-busca">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="busca-modelo" placeholder="Buscar modelo por nome"
                   autocomplete="off" aria-label="Buscar modelo por nome">
        </div>
        <div class="doc-chips" id="doc-chips">
            <button type="button" class="doc-chip ativo" data-cat="todos">Todos</button>
            <button type="button" class="doc-chip" data-cat="final">Documento final</button>
            <button type="button" class="doc-chip" data-cat="parecer">Parecer técnico</button>
            <button type="button" class="doc-chip" data-cat="fiscal">Fiscalização</button>
        </div>
    </div>

    <div id="doc-sem-resultado" class="doc-vazio" style="display:none">
        <i class="fas fa-magnifying-glass"></i>
        Nenhum modelo corresponde à busca.
    </div>

    <div class="doc-secoes">
                <section class="doc-secao" id="secao-final" data-grupo="final">
                    <header class="doc-secao-head">
                        <h3>Documento final</h3>
                        <span class="doc-secao-contagem"></span>
                        <span class="doc-secao-hint">Vai assinado para o cidadão e encerra o processo</span>
                    </header>
                    <div class="doc-grid" id="lista-final">
                        <?php for ($i = 0; $i < 3; $i++): ?><div class="skeleton skeleton-card"></div><?php endfor; ?>
                    </div>
                </section>

                <section class="doc-secao" id="secao-parecer" data-grupo="parecer">
                    <header class="doc-secao-head">
                        <h3>Parecer técnico</h3>
                        <span class="doc-secao-contagem"></span>
                        <span class="doc-secao-hint">Análise interna que fundamenta a decisão — cada tipo tem a versão ambiental</span>
                    </header>
                    <div class="doc-grid" id="lista-parecer">
                        <?php for ($i = 0; $i < 3; $i++): ?><div class="skeleton skeleton-card"></div><?php endfor; ?>
                    </div>
                </section>

                <section class="doc-secao" id="secao-fiscal" data-grupo="fiscal">
                    <header class="doc-secao-head">
                        <h3>Fiscalização</h3>
                        <span class="doc-secao-contagem"></span>
                        <span class="doc-secao-hint">Usados a partir de vistoria ou denúncia</span>
                    </header>
                    <div class="doc-grid" id="lista-fiscal"></div>
                </section>

                <section class="doc-secao" id="secao-outros" data-grupo="outros" style="display:none">
                    <header class="doc-secao-head">
                        <h3>Outros</h3>
                        <span class="doc-secao-contagem"></span>
                        <span class="doc-secao-hint">Modelos sem categoria definida</span>
                    </header>
                    <div class="doc-grid" id="lista-outros"></div>
                </section>
            </div>

      </div><!-- /doc-col-principal -->

      <!-- ══════════════════════════════════════════════════
           Rail: documentos que já existem neste processo (era a aba
           "Histórico") e a saída para um documento em branco.
      ══════════════════════════════════════════════════ -->
      <aside class="doc-rail">
        <div class="doc-rail-card">
            <div class="doc-rail-head">Documentos deste processo</div>
            <div id="lista-historico">
                <div class="doc-rail-vazio">
                    <div class="spinner-border spinner-border-sm me-2 text-secondary" role="status"></div>
                    Verificando histórico...
                </div>
            </div>
        </div>

        <div class="doc-rail-branco">
            <div class="doc-rail-branco-tit">Nenhum modelo serve?</div>
            <p>Comece de uma página em branco com o cabeçalho oficial da secretaria já aplicado.</p>
            <a href="editor.php?requerimento_id=<?= $requerimento_id ?>&template=em_branco">
                <i class="fas fa-file" style="color:#66756d"></i>Documento em branco
            </a>
        </div>
      </aside>
    </div><!-- /doc-layout -->

    <!-- Modal de preview expandida — folha de papel -->
    <div class="preview-modal-overlay" id="previewModal" onclick="fecharPreviewModal(event)">
        <div class="preview-modal-box" onclick="event.stopPropagation()">
            <div class="preview-modal-header">
                <h6 id="previewModalTitle">
                    <i class="fas fa-file-alt me-2" style="opacity:.6"></i>Preview do Documento
                </h6>
                <button class="preview-modal-close" onclick="fecharPreviewModal()" title="Fechar (Esc)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="preview-modal-body">
                <div class="preview-paper">
                    <div class="preview-paper-header">
                        <img src="../../assets/SEMA/PNG/Azul/Logo Prefeitura_SEMA.png" alt="Logo SEMA" onerror="this.style.display='none'">
                        <div class="header-text">
                            <strong>PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</strong>
                            <small>SECRETARIA MUNICIPAL DE MEIO AMBIENTE — SEMA</small>
                        </div>
                    </div>
                    <div class="preview-paper-content">
                        <iframe id="previewModalIframe" src="about:blank"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    const reqId = <?= $requerimento_id ?>;
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    const adminNivel = <?= json_encode($_SESSION['admin_nivel'] ?? '') ?>;
    const setorReq = <?= json_encode($setorReq) ?>;
    let favoritosSet = new Set();

    // Mapear nível do usuário logado → setor
    const nivelParaSetor = {
        'analista': 'setor1',
        'fiscal':   'setor2',
        'admin':    '',
        'admin_geral': '',
        'secretario':  '',
        'operador':    '',
    };
    // Usa o setor do USUÁRIO logado (prioridade) ou o setor do requerimento como fallback
    const setorUsuario = nivelParaSetor[adminNivel] || setorReq;

    // Templates recomendados por setor (badges que têm prioridade)

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
    /* ─── Famílias visuais dos modelos ─────────────────────
       Cor e ícone por assunto, nos valores do redesenho. O verde é o
       padrão; só Auto de Infração foge para o vermelho, por ser o único
       documento punitivo do conjunto. */
    const FAMILIAS = {
        construcao:     { rotulo: 'Construção',      cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-helmet-safety' },
        ambiental:      { rotulo: 'Ambiental',       cor: '#0d5433', bg: '#e9f1ec', icone: 'fa-leaf' },
        desmembramento: { rotulo: 'Desmembramento',  cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-map-location-dot' },
        habitese:       { rotulo: 'Habite-se',       cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-house-circle-check' },
        licenca:        { rotulo: 'Licença',         cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-clipboard-check' },
        economico:      { rotulo: 'Econômico',       cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-store' },
        notificacao:    { rotulo: 'Notificação',     cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-triangle-exclamation' },
        auto:           { rotulo: 'Auto de infração',cor: '#b13232', bg: '#f7eaea', icone: 'fa-ban' },
        laudo:          { rotulo: 'Laudo',           cor: '#3d5c46', bg: '#eef2ef', icone: 'fa-microscope' },
        comunicado:     { rotulo: 'Comunicado',      cor: '#55635b', bg: '#eef2ef', icone: 'fa-bullhorn' },
        generico:       { rotulo: 'Modelo',          cor: '#55635b', bg: '#eef2ef', icone: 'fa-file-signature' },
    };

    /* Grupo = o que o documento FAZ no processo. Sai do slug do template,
       que é estável — o badge é texto de exibição e pode mudar. */
    function grupoDoTemplate(nome) {
        const n = String(nome || '').toLowerCase();
        if (n.indexOf('parecer_tecnico') === 0 || n.indexOf('parecer') === 0) return 'parecer';
        if (['notificacao_fiscal', 'auto_de_infracao', 'laudo_relatorio_tecnico',
             'comunicados_orientacoes'].indexOf(n) !== -1 || n.indexOf('denuncia_') === 0) return 'fiscal';
        if (['alvara_de_construcao', 'carta_habite_se', 'alvara_de_desmembramento',
             'licenca_previa_projeto', 'licenca_atividade_economica'].indexOf(n) !== -1) return 'final';
        return 'outros';
    }

    function familiaDoTemplate(nome, badge) {
        const n = String(nome || '').toLowerCase();
        if (n.indexOf('ambiental') !== -1) return FAMILIAS.ambiental;
        if (n.indexOf('construcao') !== -1) return FAMILIAS.construcao;
        if (n.indexOf('habite') !== -1) return FAMILIAS.habitese;
        if (n.indexOf('desmembramento') !== -1) return FAMILIAS.desmembramento;
        if (n.indexOf('licenca_previa') !== -1) return FAMILIAS.licenca;
        if (n.indexOf('economica') !== -1) return FAMILIAS.economico;
        if (n.indexOf('notificacao') !== -1) return FAMILIAS.notificacao;
        if (n.indexOf('infracao') !== -1) return FAMILIAS.auto;
        if (n.indexOf('laudo') !== -1 || n.indexOf('relatorio') !== -1) return FAMILIAS.laudo;
        if (n.indexOf('comunicado') !== -1) return FAMILIAS.comunicado;
        return FAMILIAS.generico;
    }

    function buildCardTemplate(t, idx) {
        const nome       = t.nome || t;
        const label      = t.label_amigavel || nome.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const desc       = t.descricao || 'Modelo padrão oficial da secretaria.';
        const familia    = familiaDoTemplate(nome, t.badge);
        const badge      = t.badge || familia.rotulo;
        const isFav      = favoritosSet.has(nome);
        const favIcon    = isFav ? 'fas fa-star' : 'far fa-star';
        const favCor     = isFav ? '#e0a91b' : '#c8d2cc';
        const favTitle   = isFav ? 'Remover dos Meus Modelos' : 'Adicionar aos Meus Modelos';
        const isMelhor   = t.melhor_match === true;
        const urlEditor  = `editor.php?requerimento_id=${reqId}&template=${encodeURIComponent(nome)}`;
        const fillTexto  = isMelhor
            ? `<span style="color:#15803d;font-weight:700;"><i class="fas fa-circle-check me-1"></i>100% preenchido</span>`
            : ((t.fill_score != null && t.fill_score > 0)
                ? `${t.fill_score}% preenchido`
                : 'Modelo em branco');

        return `
        <div class="tpl-card template-card-wrapper${isMelhor ? ' melhor' : ''}"
             id="tpl-card-${escapeHtml(nome)}" data-grupo="${grupoDoTemplate(nome)}">
            <div class="tpl-card-corpo" onclick="location.href='${urlEditor}'">
                <span class="tpl-icone" style="background:${familia.bg};color:${familia.cor}">
                    <i class="fas ${familia.icone}"></i>
                </span>
                <span class="tpl-txt">
                    <span class="tpl-badges">
                        <span class="tpl-badge">${escapeHtml(badge)}</span>
                        ${isMelhor ? '<span class="tpl-melhor"><i class="fas fa-star"></i>RECOMENDADO</span>' : ''}
                    </span>
                    <span class="tpl-nome">${escapeHtml(label)}</span>
                    <span class="tpl-desc">${escapeHtml(desc)}</span>
                </span>
                <span class="tpl-acoes">
                    <button type="button" class="tpl-fav" title="${favTitle}"
                            onclick="event.stopPropagation();toggleFavorito('${escaparAttr(nome)}', this)">
                        <i class="${favIcon}" style="color:${favCor}"></i>
                    </button>
                    <button type="button" class="tpl-olho" title="Ver prévia do modelo"
                            onclick="event.stopPropagation();expandirPreview('${escaparAttr(nome)}', '${escaparAttr(label)}', event)">
                        <i class="fas fa-eye"></i>
                    </button>
                </span>
            </div>
            <div class="tpl-rodape" onclick="location.href='${urlEditor}'">
                <span class="tpl-fill">${fillTexto}</span>
                <span class="tpl-usar">Usar <i class="fas fa-arrow-right"></i></span>
            </div>
        </div>`;
    }

    /* ─── Busca e chips de categoria ───────────────────────
       Filtram no cliente o que já está na tela. As seções continuam
       existindo (Recomendados / Ambiental / Obras / Outros); a busca
       esconde os cards que não casam e, junto, a seção que ficou vazia,
       pra não sobrar um título solto sobre o nada. */
    function aplicarFiltros() {
        const termo = (document.getElementById('busca-modelo')?.value || '')
            .trim().toLowerCase();
        const chip = document.querySelector('.doc-chip.ativo');
        const cat = chip ? chip.dataset.cat : 'todos';

        let visiveisTotal = 0;

        document.querySelectorAll('.doc-secao').forEach((secao) => {
            const grupo = secao.dataset.grupo;
            const cards = Array.from(secao.querySelectorAll('.tpl-card'));
            if (cards.length === 0) { secao.style.display = 'none'; return; }

            let visiveis = 0;
            cards.forEach((card) => {
                const casa = !termo || (card.textContent || '').toLowerCase().includes(termo);
                card.style.display = casa ? '' : 'none';
                if (casa) visiveis += 1;
            });

            // O chip filtra por grupo; a busca, por texto. Os dois se somam.
            const catOk = cat === 'todos' || cat === grupo;
            secao.style.display = (catOk && visiveis > 0) ? '' : 'none';
            if (catOk) visiveisTotal += visiveis;

            // A contagem do cabeçalho passa a refletir o que está filtrado,
            // senão diz "6 modelos" com dois na tela.
            const contagem = secao.querySelector('.doc-secao-contagem');
            if (contagem) {
                const n = termo ? visiveis : cards.length;
                contagem.textContent = n === 1 ? '1 modelo' : n + ' modelos';
            }
        });

        // "Meus Modelos" acompanha a busca, mas ignora o chip de finalidade:
        // é uma lista pessoal, não uma das categorias oficiais.
        const secMeus = document.getElementById('secao-meus-modelos');
        if (secMeus && secMeus.dataset.tinhaConteudo === '1') {
            let algum = false;
            secMeus.querySelectorAll('.template-card-wrapper').forEach((card) => {
                const casa = !termo || (card.textContent || '').toLowerCase().includes(termo);
                card.style.display = casa ? '' : 'none';
                if (casa) algum = true;
            });
            secMeus.style.display = (algum && cat === 'todos') ? 'block' : 'none';
            if (algum && cat === 'todos') visiveisTotal += 1;
        }

        const vazio = document.getElementById('doc-sem-resultado');
        if (vazio) vazio.style.display = visiveisTotal > 0 ? 'none' : 'block';
    }

    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'busca-modelo') aplicarFiltros();
    });

    document.addEventListener('click', function (e) {
        const chip = e.target.closest('.doc-chip');
        if (!chip) return;
        document.querySelectorAll('.doc-chip').forEach((c) => c.classList.remove('ativo'));
        chip.classList.add('ativo');
        aplicarFiltros();
    });

    /* ─── Toggle favorito ──────────────────────────────────── */
    function toggleFavorito(nome, btn) {
        btn.disabled = true;
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'favoritar_template', template_nome: nome, csrf_token: csrfToken })
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
            // Atualizar "Meus Modelos" somente quando a seção já estiver visível.
            // A tela não usa mais abas, então não há `tab-meus` para consultar.
            const secMeus = document.getElementById('secao-meus-modelos');
            if (secMeus && secMeus.dataset.tinhaConteudo === '1') {
                carregarTemplates();
            }
        })
        .catch(() => { btn.disabled = false; });
    }

    /* ─── Montar HTML de um card de histórico ───────────────── */
    function buildHistCard(h, idx) {
        // Formato de rail: linha compacta, não mais card em grade de 4 colunas.
        const nome      = h.label || h.nome || 'Documento';
        const isDb      = h.origem === 'db';
        const icone     = isDb ? 'fa-pen-to-square' : 'fa-file-pdf';
        const cor       = isDb ? '#3762d9' : '#b13232';
        const selo      = isDb ? 'Rascunho' : 'Assinado';
        const labelEnc  = encodeURIComponent(h.label || h.nome || 'Documento');

        return `
        <a href="editor.php?requerimento_id=${reqId}&template=${encodeURIComponent(h.id)}&label=${labelEnc}"
           class="doc-rail-item" title="${escaparAttr(nome)}">
            <i class="fas ${icone} doc-rail-ic" style="color:${cor}"></i>
            <span class="doc-rail-corpo">
                <span class="doc-rail-nome">${escapeHtml(nome)}</span>
                <span class="doc-rail-meta">${selo} · ${escapeHtml(h.data || '')}</span>
                <span class="doc-rail-usar">Usar como base</span>
            </span>
        </a>`;
    }

    /* ─── Card de template do usuário (personalizado ou favorito) */
    /* ─── Card de "Meus Modelos" (favorito ou personalizado) ──
       Mesmo formato dos demais, para a página não ter dois desenhos de card.
       O que muda é a cor do ícone e o badge. */
    function buildUserTemplateCard(t, idx) {
        const isFav = t.tipo === 'favorito';
        const label = t.nome;
        const desc  = t.descricao || (isFav ? 'Modelo marcado como favorito.' : 'Modelo salvo por você.');

        const familia = isFav
            ? familiaDoTemplate(t.nome, t.badge)
            : { bg: '#e9f1ec', cor: '#0d5433', icone: 'fa-bookmark' };

        const url = isFav
            ? `editor.php?requerimento_id=${reqId}&template=${encodeURIComponent(t.nome)}`
            : `editor.php?requerimento_id=${reqId}&template=${encodeURIComponent('user_tpl:' + t.id)}&label=${encodeURIComponent(t.nome)}`;

        const acaoCanto = isFav
            ? `<button type="button" class="tpl-fav" title="Remover dos Meus Modelos"
                       onclick="event.stopPropagation();toggleFavorito('${escaparAttr(t.nome)}', this)">
                   <i class="fas fa-star" style="color:#e0a91b"></i>
               </button>`
            : `<span class="tpl-acoes">
                   <button type="button" class="tpl-fav" title="Duplicar modelo"
                           onclick="event.stopPropagation();duplicarTemplateUsuario(${t.id}, event)">
                       <i class="fas fa-copy" style="color:#9aa9a0;font-size:.82rem"></i>
                   </button>
                   <button type="button" class="tpl-fav" title="Excluir modelo"
                           onclick="event.stopPropagation();excluirTemplateUsuario(${t.id}, event)">
                       <i class="fas fa-trash-can" style="color:#c8d2cc;font-size:.82rem"></i>
                   </button>
               </span>`;

        return `
        <div class="tpl-card template-card-wrapper" ${isFav ? '' : `id="user-tpl-${t.id}"`}>
            <div class="tpl-card-corpo" onclick="location.href='${url}'">
                <span class="tpl-icone" style="background:${familia.bg};color:${familia.cor}">
                    <i class="fas ${familia.icone}"></i>
                </span>
                <span class="tpl-txt">
                    <span class="tpl-badges">
                        <span class="tpl-badge">${isFav ? 'Favorito' : 'Personalizado'}</span>
                    </span>
                    <span class="tpl-nome">${escapeHtml(label)}</span>
                    <span class="tpl-desc">${escapeHtml(desc)}</span>
                </span>
                ${acaoCanto}
            </div>
            <div class="tpl-rodape" onclick="location.href='${url}'">
                <span class="tpl-fill">${t.data ? escapeHtml(t.data) : 'Modelo seu'}</span>
                <span class="tpl-usar">Usar <i class="fas fa-arrow-right"></i></span>
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
            body: new URLSearchParams({ action: 'excluir_template_usuario', id: id, csrf_token: csrfToken })
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

    function duplicarTemplateUsuario(id, evt) {
        evt.preventDefault(); evt.stopPropagation();
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'duplicar_template_usuario', id: id, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(ret => {
            if (!ret.success) throw new Error(ret.error || 'Não foi possível duplicar o modelo.');
            carregarTemplates();
        })
        .catch(err => Swal.fire('Erro', err.message, 'error'));
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
            const listHist  = document.getElementById('lista-historico');
            const listMeus  = document.getElementById('lista-meus-templates');
            const secMeus   = document.getElementById('secao-meus-modelos');
            const GRUPOS = ['final', 'parecer', 'fiscal', 'outros'];

            // ── Modelos, distribuídos por finalidade ─────────
            if (ret.success && ret.templates && ret.templates.length > 0) {
                const porGrupo = { final: [], parecer: [], fiscal: [], outros: [] };
                ret.templates.forEach((t) => {
                    porGrupo[grupoDoTemplate(t.nome)].push(t);
                });

                // Ordenar para colocar o modelo com melhor encaixe no início da lista
                porGrupo.final.sort((a, b) => {
                    if (a.melhor_match && !b.melhor_match) return -1;
                    if (!a.melhor_match && b.melhor_match) return 1;
                    return 0;
                });
                porGrupo.parecer.sort((a, b) => {
                    if (a.melhor_match && !b.melhor_match) return -1;
                    if (!a.melhor_match && b.melhor_match) return 1;
                    return 0;
                });

                GRUPOS.forEach((g) => {
                    const secao = document.getElementById('secao-' + g);
                    const lista = document.getElementById('lista-' + g);
                    if (!secao || !lista) return;

                    const itens = porGrupo[g];
                    lista.innerHTML = itens.map((t, i) => buildCardTemplate(t, i)).join('');

                    const contagem = secao.querySelector('.doc-secao-contagem');
                    if (contagem) {
                        contagem.textContent = itens.length === 1 ? '1 modelo' : itens.length + ' modelos';
                    }
                    // "Outros" é uma rede de segurança: só aparece se algum
                    // modelo novo não couber em nenhum dos três grupos.
                    secao.style.display = itens.length > 0 ? '' : 'none';
                });
            } else {
                const lista = document.getElementById('lista-final');
                if (lista) {
                    lista.innerHTML = `<div class="doc-vazio-erro">
                        <i class="fas fa-triangle-exclamation"></i>
                        <strong>Falha ao carregar os modelos.</strong>
                        <span>${ret.error || 'Nenhum template encontrado.'}</span>
                    </div>`;
                }
                ['parecer', 'fiscal', 'outros'].forEach((g) => {
                    const sec = document.getElementById('secao-' + g);
                    if (sec) sec.style.display = 'none';
                });
            }

            aplicarFiltros();

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
            // A seção "Documento final" é a primeira da página: é onde o aviso
            // de falha aparece sem a pessoa precisar rolar.
            const lista = document.getElementById('lista-final');
            if (lista) {
                lista.innerHTML = `<div class="doc-vazio-erro">
                    <i class="fas fa-wifi-slash"></i>
                    <strong>Falha na conexão com o servidor.</strong>
                    <span>${err.message || 'Verifique sua conexão e recarregue a página.'}</span>
                </div>`;
            }
            ['parecer', 'fiscal', 'outros'].forEach((g) => {
                const sec = document.getElementById('secao-' + g);
                if (sec) sec.style.display = 'none';
            });
        });
    }

    /* ─── Preview expandida (modal — folha de papel) ─────── */
    function expandirPreview(nome, label, evt) {
        evt.preventDefault();
        evt.stopPropagation();
        const modal  = document.getElementById('previewModal');
        const iframe = document.getElementById('previewModalIframe');
        const title  = document.getElementById('previewModalTitle');
        title.innerHTML = `<i class="fas fa-file-alt me-2" style="opacity:.6"></i>${escapeHtml(label)}`;
        iframe.src = `../templates/${encodeURIComponent(nome)}.html`;
        // Auto-ajustar altura do iframe ao conteúdo
        iframe.onload = function() {
            try {
                const h = iframe.contentDocument.documentElement.scrollHeight;
                iframe.style.minHeight = Math.max(h + 40, 600) + 'px';
            } catch(e) {}
        };
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function fecharPreviewModal(evt) {
        if (evt && evt.target !== evt.currentTarget) return;
        const modal  = document.getElementById('previewModal');
        const iframe = document.getElementById('previewModalIframe');
        modal.classList.remove('active');
        iframe.src = 'about:blank';
        iframe.style.minHeight = '800px';
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharPreviewModal(); });

    document.addEventListener('DOMContentLoaded', carregarTemplates);
    </script>
<?php include '../footer.php'; ?>
