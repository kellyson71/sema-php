<?php
require_once 'conexao.php';
verificaLogin();

$adminBase = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) !== 'admin') ? '../' : '';
$adminData = getDadosAdmin($pdo, $_SESSION['admin_id']);
$currentPage = basename($_SERVER['PHP_SELF']);

$totalNotificacoes = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$totalNaoVisualizados = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn();
$totalAguardandoFiscal = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aguardando Fiscalização'")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, req.nome AS requerente
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    ORDER BY r.data_envio DESC
    LIMIT 20
");
$stmt->execute();
$todasNotificacoes = $stmt->fetchAll();

$pageTitles = [
    'index.php' => 'Dashboard',
    'requerimentos.php' => 'Requerimentos',
    'documentos_assinados.php' => 'Documentos Assinados',
    'estatisticas.php' => 'Estatísticas',
    'visualizar_requerimento.php' => 'Detalhes do Requerimento',
    'perfil.php' => 'Meu Perfil',
    'administradores.php' => 'Gerenciar Usuários',
    'secretario_dashboard.php' => 'Aprovação de Alvarás',
    'revisao_secretario.php' => 'Revisão e Assinatura',
    'fiscal_dashboard.php' => 'Fiscalização de Obras',
    'denuncias.php' => 'Denúncias',
    'nova_denuncia.php' => 'Nova Denúncia',
    'visualizar_denuncia.php' => 'Detalhes da Denúncia',
    'requerimentos_arquivados.php' => 'Requerimentos Arquivados',
    'logs_email.php' => 'Histórico de Envios',
    'testes.php' => 'Painel de Testes',
];
$pageTitle = $pageTitles[$currentPage] ?? 'Painel Administrativo';

$labelSetor = [
    'admin' => 'Administrador',
    'admin_geral' => 'Admin Geral',
    'secretario' => 'Secretário(a)',
    'analista' => 'Analista — Triagem',
    'fiscal' => 'Fiscal de Obras',
    'operador' => 'Operador',
];

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$roleLabel = $labelSetor[$nivelAtual] ?? ucfirst($nivelAtual);
$isAdmin = in_array($nivelAtual, ['admin', 'admin_geral'], true);
$isSecretario = ($nivelAtual === 'secretario' || $isAdmin);
$isAnalista = ($nivelAtual === 'analista' || $isAdmin);
$isFiscal = ($nivelAtual === 'fiscal' || $isAdmin);
$isHomologHost = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'sematst') !== false;
$avatarPath = !empty($adminData['foto_perfil']) ? $adminBase . '../uploads/perfil/' . $adminData['foto_perfil'] : null;
$isDataSectionOpen = in_array($currentPage, ['requerimentos_arquivados.php', 'documentos_assinados.php', 'estatisticas.php', 'logs_email.php'], true);
$notificationTotal = $totalNaoVisualizados > 0 ? $totalNaoVisualizados : $totalNotificacoes;

$searchItems = [
    ['label' => 'Dashboard', 'caption' => 'Visão geral do painel', 'url' => $adminBase . 'index.php', 'icon' => 'fa-gauge-high'],
    ['label' => 'Requerimentos', 'caption' => 'Lista principal de protocolos', 'url' => $adminBase . 'requerimentos.php', 'icon' => 'fa-clipboard-list'],
    ['label' => 'Denúncias', 'caption' => 'Acompanhar denúncias ambientais', 'url' => $adminBase . 'denuncias.php', 'icon' => 'fa-bullhorn'],
    ['label' => 'Estatísticas', 'caption' => 'Indicadores e relatórios', 'url' => $adminBase . 'estatisticas.php', 'icon' => 'fa-chart-column'],
    ['label' => 'Arquivados', 'caption' => 'Consultar requerimentos arquivados', 'url' => $adminBase . 'requerimentos_arquivados.php', 'icon' => 'fa-box-archive'],
    ['label' => 'Documentos Assinados', 'caption' => 'Acervo de documentos assinados', 'url' => $adminBase . 'documentos_assinados.php', 'icon' => 'fa-file-signature'],
    ['label' => 'Histórico de Envios', 'caption' => 'Logs de emails enviados', 'url' => $adminBase . 'logs_email.php', 'icon' => 'fa-envelope-open-text'],
    ['label' => 'Meu Perfil', 'caption' => 'Dados do usuário logado', 'url' => $adminBase . 'perfil.php', 'icon' => 'fa-user-gear'],
];

if ($isAdmin) {
    $searchItems[] = ['label' => 'Gerenciar Usuários', 'caption' => 'Administradores e acessos', 'url' => $adminBase . 'administradores.php', 'icon' => 'fa-users-gear'];
}
if ($isSecretario) {
    $searchItems[] = [
        'label' => 'Aprovação de Alvarás',
        'caption' => 'Fluxo do secretário',
        'url' => $adminBase . ($isAdmin ? 'simular_perfil.php?role=secretario' : 'secretario_dashboard.php'),
        'icon' => 'fa-signature',
    ];
}
if ($isAnalista) {
    $searchItems[] = [
        'label' => 'Triagem de Protocolos',
        'caption' => 'Fila de análise inicial',
        'url' => $adminBase . ($isAdmin ? 'simular_perfil.php?role=analista' : 'requerimentos.php?status=Pendente'),
        'icon' => 'fa-magnifying-glass',
    ];
}
if ($isFiscal) {
    $searchItems[] = [
        'label' => 'Fiscalização de Obras',
        'caption' => 'Acompanhar vistorias e pendências',
        'url' => $adminBase . ($isAdmin ? 'simular_perfil.php?role=fiscal' : 'fiscal_dashboard.php'),
        'icon' => 'fa-hard-hat',
    ];
}
if ($isHomologHost || (defined('MODO_HOMOLOG') && MODO_HOMOLOG)) {
    $searchItems[] = ['label' => 'Painel de Testes', 'caption' => 'Ferramentas de homologação', 'url' => $adminBase . 'testes.php', 'icon' => 'fa-vial'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - SEMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tiny.cloud/1/djvd4vhwlkk5pio6pmjhmqd0a0j0iwziovpy9rz7k4jvzboi/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Dancing+Script:wght@400;700&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        :root {
            --shell-bg: #f3f7fb;
            --surface: #ffffff;
            --surface-soft: #f8fbff;
            --surface-strong: #0f172a;
            --line: #dbe7f3;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #0284c7;
            --primary-strong: #0369a1;
            --primary-soft: #e0f2fe;
            --warning-soft: #fff7ed;
            --warning: #f97316;
            --success-soft: #ecfdf5;
            --success: #10b981;
            --danger-soft: #fef2f2;
            --danger: #ef4444;
            --sidebar-width: 284px;
            --sidebar-collapsed-width: 92px;
            --topbar-height: 82px;
            --shell-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            --card-shadow: 0 12px 34px rgba(15, 23, 42, 0.06);
            --radius-lg: 24px;
            --radius-md: 18px;
            --radius-sm: 14px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            min-height: 100%;
        }

        body {
            margin: 0;
            font-family: 'Poppins', system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(2, 132, 199, 0.08), transparent 20%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.08), transparent 18%),
                var(--shell-bg);
            overflow-x: hidden;
        }

        body.sidebar-collapsed {
            --sidebar-width: var(--sidebar-collapsed-width);
        }

        a {
            text-decoration: none;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f172a 0%, #10253c 30%, #11324d 100%);
            color: #fff;
            z-index: 1040;
            display: flex;
            flex-direction: column;
            padding: 20px 16px 18px;
            transition: width .28s ease, transform .28s ease;
            box-shadow: 10px 0 40px rgba(15, 23, 42, 0.18);
        }

        .sidebar::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(56, 189, 248, 0.22), transparent 24%),
                radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.16), transparent 18%);
            pointer-events: none;
        }

        .sidebar > * {
            position: relative;
            z-index: 1;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 10px 18px;
            margin-bottom: 10px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            color: #fff;
        }

        .sidebar-logo-wrap {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            flex-shrink: 0;
        }

        .sidebar-logo {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .sidebar-brand-copy {
            min-width: 0;
        }

        .sidebar-brand-title {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .sidebar-brand-subtitle {
            font-size: .76rem;
            color: rgba(255,255,255,.68);
            line-height: 1.4;
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 6px;
            margin-right: -6px;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,.18);
            border-radius: 999px;
        }

        .sidebar-section {
            margin-bottom: 18px;
        }

        .menu-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 10px;
            margin-bottom: 10px;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255,255,255,.52);
            font-weight: 600;
        }

        .sidebar-menu ul,
        .sidebar-submenu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li + li {
            margin-top: 6px;
        }

        .sidebar-link,
        .sidebar-menu .nav-link {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            border-radius: 16px;
            color: rgba(255,255,255,.76) !important;
            padding: 11px 12px;
            transition: background-color .2s ease, color .2s ease, transform .2s ease, border-color .2s ease;
            border: 1px solid transparent;
            background: transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active,
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link:not(.collapsed) {
            color: #fff !important;
            background: rgba(255,255,255,.08);
            border-color: rgba(255,255,255,.08);
        }

        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(2,132,199,.24), rgba(14,165,233,.18));
            box-shadow: inset 0 0 0 1px rgba(125,211,252,.18);
        }

        .sidebar-link-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.06);
            color: inherit;
            flex-shrink: 0;
            font-size: .95rem;
        }

        .sidebar-link-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-width: 0;
            flex: 1;
        }

        .sidebar-link-text {
            min-width: 0;
        }

        .sidebar-link-title {
            display: block;
            font-size: .9rem;
            font-weight: 500;
            line-height: 1.2;
        }

        .sidebar-link-caption {
            display: block;
            font-size: .72rem;
            color: rgba(255,255,255,.5);
            margin-top: 2px;
            line-height: 1.3;
        }

        .sidebar-link-badge {
            border-radius: 999px;
            padding: 4px 8px;
            font-size: .72rem;
            font-weight: 600;
        }

        .sidebar-link-chevron {
            font-size: .72rem;
            opacity: .72;
            transition: transform .2s ease;
        }

        .sidebar-menu .nav-link:not(.collapsed) .sidebar-link-chevron {
            transform: rotate(180deg);
        }

        .sidebar-submenu {
            margin: 8px 0 4px 16px;
            padding-left: 16px;
            border-left: 1px solid rgba(255,255,255,.12);
        }

        .sidebar-submenu .sidebar-link {
            min-height: 40px;
            padding: 8px 10px;
            border-radius: 14px;
        }

        .sidebar-submenu .sidebar-link-icon {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            font-size: .78rem;
        }

        .sidebar-utility {
            margin-top: 18px;
            padding: 14px;
            border-radius: 20px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-utility-label {
            font-size: .72rem;
            color: rgba(255,255,255,.55);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 8px;
        }

        .sidebar-utility-link,
        .sidebar-utility-action {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            color: rgba(255,255,255,.8);
            padding: 10px 12px;
            border-radius: 14px;
            font-size: .84rem;
            transition: background-color .2s ease, color .2s ease;
        }

        .sidebar-utility-link:hover,
        .sidebar-utility-action:hover {
            color: #fff;
            background: rgba(255,255,255,.08);
        }

        .sidebar-footer {
            padding: 14px 10px 4px;
        }

        .sidebar-version {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: .72rem;
            color: rgba(255,255,255,.48);
        }

        .content-wrapper {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            transition: margin-left .28s ease;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 520px) auto;
            align-items: center;
            gap: 18px;
            min-height: var(--topbar-height);
            margin: 18px 18px 0;
            padding: 14px 20px;
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(219, 231, 243, .9);
            border-radius: 24px;
            box-shadow: var(--shell-shadow);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .icon-button {
            width: 44px;
            height: 44px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            color: var(--text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: border-color .2s ease, background-color .2s ease, color .2s ease, transform .2s ease;
        }

        .icon-button:hover {
            color: var(--primary);
            border-color: #b7d8ea;
            background: var(--surface-soft);
            transform: translateY(-1px);
        }

        .topbar-heading {
            min-width: 0;
        }

        .topbar-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
            font-size: .74rem;
            color: var(--muted);
        }

        .topbar-eyebrow .pill {
            padding: 4px 8px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-strong);
            font-weight: 600;
        }

        .topbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--text);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-search {
            position: relative;
        }

        .topbar-search-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 14px;
            height: 48px;
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 16px;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        .topbar-search-box:focus-within {
            border-color: #8ed1f3;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, .08);
            background: #fff;
        }

        .topbar-search-box i {
            color: var(--muted);
        }

        .topbar-search-input {
            flex: 1;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text);
            font-size: .92rem;
        }

        .topbar-search-hint {
            padding: 5px 8px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid var(--line);
            color: var(--muted);
            font-size: .72rem;
            white-space: nowrap;
        }

        .search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: rgba(255,255,255,.98);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: var(--shell-shadow);
            padding: 8px;
            display: none;
            max-height: 380px;
            overflow-y: auto;
        }

        .search-results.active {
            display: block;
        }

        .search-empty {
            padding: 14px;
            color: var(--muted);
            font-size: .85rem;
            text-align: center;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 14px;
            border-radius: 16px;
            color: var(--text);
            transition: background-color .2s ease, transform .2s ease;
        }

        .search-result-item:hover,
        .search-result-item.is-highlighted {
            background: var(--surface-soft);
            transform: translateY(-1px);
        }

        .search-result-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary-strong);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .search-result-copy {
            min-width: 0;
        }

        .search-result-title {
            display: block;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text);
        }

        .search-result-caption {
            display: block;
            font-size: .76rem;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-toggle {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            background: var(--danger);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .topbar-profile {
            min-width: 0;
        }

        .topbar-profile-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 8px 6px 6px;
            border: 1px solid var(--line);
            border-radius: 18px;
            color: var(--text);
            background: #fff;
        }

        .topbar-profile-toggle:hover {
            background: var(--surface-soft);
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary-strong);
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .user-name {
            font-size: .88rem;
            font-weight: 600;
            color: var(--text);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: .74rem;
            color: var(--muted);
            line-height: 1.2;
        }

        .dropdown-menu {
            border: 1px solid var(--line);
            box-shadow: var(--card-shadow);
            border-radius: 18px;
            min-width: 260px;
            padding: 8px;
        }

        .dropdown-header {
            padding: 12px 14px;
            color: var(--muted);
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .dropdown-item {
            border-radius: 12px;
            padding: 11px 14px;
            font-size: .88rem;
        }

        .dropdown-item:hover {
            background: var(--surface-soft);
        }

        .content-wrapper-inner {
            padding: 20px 18px 24px;
        }

        .main-content {
            padding: 0;
        }

        .card {
            border: 1px solid rgba(219, 231, 243, .96);
            border-radius: var(--radius-md);
            box-shadow: var(--card-shadow);
            background: rgba(255,255,255,.94);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(219, 231, 243, .85);
            padding: 18px 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
        }

        .table tbody tr.clickable-row {
            cursor: pointer;
            transition: background-color .2s ease;
        }

        .table tbody tr.clickable-row:hover {
            background: rgba(2,132,199,.06);
        }

        .badge-status {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 600;
            line-height: 1;
        }

        .status-em-analise { background-color: #fef3c7; color: #92400e; }
        .status-aprovado { background-color: #dcfce7; color: #166534; }
        .status-reprovado { background-color: #fee2e2; color: #991b1b; }
        .status-pendente { background-color: #dbeafe; color: #1d4ed8; }
        .status-cancelado { background-color: #e5e7eb; color: #374151; }
        .status-aguardando-fiscalização,
        .status-aguardando-fiscalizacao { background-color: #e0f2fe; color: #075985; }
        .status-apto-a-gerar-alvará,
        .status-apto-a-gerar-alvara { background-color: #ede9fe; color: #6d28d9; }
        .status-alvará-emitido,
        .status-alvara-emitido { background-color: #d1fae5; color: #047857; }
        .status-finalizado { background-color: #dbeafe; color: #1d4ed8; }
        .status-indeferido { background-color: #e2e8f0; color: #0f172a; }

        .notification-sidebar {
            position: fixed;
            top: 20px;
            right: 20px;
            bottom: 20px;
            width: min(380px, calc(100vw - 40px));
            background: rgba(255,255,255,.98);
            border: 1px solid var(--line);
            box-shadow: var(--shell-shadow);
            border-radius: 26px;
            z-index: 1060;
            transform: translateX(calc(100% + 20px));
            transition: transform .28s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .notification-sidebar.active {
            transform: translateX(0);
        }

        .notification-sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 18px 14px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(180deg, #f8fbff 0%, #fff 100%);
        }

        .notification-sidebar-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .notification-sidebar-close {
            width: 38px;
            height: 38px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--muted);
        }

        .notification-sidebar-body {
            padding: 18px;
            overflow-y: auto;
        }

        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .notification-item-sidebar {
            border: 1px solid rgba(219, 231, 243, .9);
            border-radius: 16px;
            margin-bottom: 10px;
            transition: border-color .2s ease, background-color .2s ease, transform .2s ease;
            background: #fff;
        }

        .notification-item-sidebar:hover {
            border-color: #b7d8ea;
            background: var(--surface-soft);
            transform: translateY(-1px);
        }

        .notification-item-sidebar a {
            display: block;
            padding: 14px;
            color: inherit;
        }

        .notification-title {
            font-size: .9rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .notification-content {
            font-size: .8rem;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .notification-time {
            font-size: .74rem;
            color: var(--muted);
        }

        .notification-unread {
            background: #f8fbff;
            border-color: #b7d8ea;
        }

        .content-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .44);
            z-index: 1050;
            display: none;
        }

        .content-overlay.active {
            display: block;
        }

        .simulation-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 18px 18px 0;
            padding: 14px 18px;
            border-radius: 18px;
            background: linear-gradient(135deg, #fffbeb, #fff7ed);
            border: 1px solid #fed7aa;
            color: #9a3412;
            box-shadow: var(--card-shadow);
        }

        .simulation-banner-copy {
            font-size: .88rem;
            line-height: 1.5;
        }

        .simulation-banner-copy strong {
            color: #7c2d12;
        }

        .simulation-banner .btn {
            white-space: nowrap;
        }

        body.sidebar-collapsed .sidebar-brand-copy,
        body.sidebar-collapsed .menu-header span,
        body.sidebar-collapsed .sidebar-link-text,
        body.sidebar-collapsed .sidebar-link-badge,
        body.sidebar-collapsed .sidebar-link-chevron,
        body.sidebar-collapsed .sidebar-utility,
        body.sidebar-collapsed .sidebar-footer {
            display: none;
        }

        body.sidebar-collapsed .sidebar-header {
            justify-content: center;
            padding-inline: 0;
        }

        body.sidebar-collapsed .sidebar-brand {
            justify-content: center;
        }

        body.sidebar-collapsed .sidebar-link,
        body.sidebar-collapsed .sidebar-menu .nav-link {
            justify-content: center;
            padding-inline: 0;
        }

        body.sidebar-collapsed .sidebar-submenu {
            display: none !important;
        }

        body.sidebar-collapsed .sidebar-link-content {
            display: none;
        }

        body.sidebar-collapsed .sidebar-menu .nav-link > div {
            justify-content: center !important;
        }

        @media (max-width: 1199px) {
            .topbar {
                grid-template-columns: minmax(0, 1fr) auto;
            }

            .topbar-search {
                grid-column: 1 / -1;
                order: 3;
            }
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(calc(-100% - 16px));
                width: min(284px, calc(100vw - 32px));
                left: 16px;
                top: 16px;
                bottom: 16px;
                border-radius: 28px;
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .content-wrapper {
                margin-left: 0;
            }

            .topbar {
                margin: 16px 16px 0;
                grid-template-columns: minmax(0, 1fr) auto;
            }

            .content-wrapper-inner,
            .simulation-banner {
                margin-inline: 0;
            }
        }

        @media (max-width: 767px) {
            .topbar {
                padding: 14px;
                gap: 12px;
            }

            .topbar-title {
                font-size: 1.05rem;
            }

            .topbar-profile-toggle {
                padding-right: 6px;
            }

            .topbar-profile-toggle .user-info,
            .topbar-search-hint {
                display: none !important;
            }

            .notification-sidebar {
                top: 12px;
                right: 12px;
                bottom: 12px;
                width: calc(100vw - 24px);
            }

            .simulation-banner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="<?= (defined('MODO_HOMOLOG') && MODO_HOMOLOG) ? 'env-homolog' : '' ?>">
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="<?= $adminBase ?>index.php" class="sidebar-brand" title="SEMA">
                <span class="sidebar-logo-wrap">
                    <img src="<?= $adminBase ?>../assets/img/Logo_sema.png" alt="SEMA" class="sidebar-logo">
                </span>
                <span class="sidebar-brand-copy">
                    <span class="sidebar-brand-title">SEMA Admin</span>
                    <span class="sidebar-brand-subtitle">Secretaria Municipal de Meio Ambiente</span>
                </span>
            </a>
        </div>

        <div class="sidebar-scroll sidebar-menu">
            <div class="sidebar-section">
                <div class="menu-header"><span>Principal</span></div>
                <ul>
                    <li>
                        <a href="<?= $adminBase ?>index.php" class="sidebar-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" title="Dashboard">
                            <span class="sidebar-link-icon"><i class="fas fa-gauge-high"></i></span>
                            <span class="sidebar-link-content">
                                <span class="sidebar-link-text">
                                    <span class="sidebar-link-title">Dashboard</span>
                                    <span class="sidebar-link-caption">Resumo operacional do sistema</span>
                                </span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>requerimentos.php" class="sidebar-link <?= $currentPage === 'requerimentos.php' ? 'active' : '' ?>" title="Requerimentos">
                            <span class="sidebar-link-icon"><i class="fas fa-clipboard-list"></i></span>
                            <span class="sidebar-link-content">
                                <span class="sidebar-link-text">
                                    <span class="sidebar-link-title">Requerimentos</span>
                                    <span class="sidebar-link-caption">Fila principal de protocolos</span>
                                </span>
                                <?php if ($totalNaoVisualizados > 0): ?>
                                    <span class="badge bg-danger sidebar-link-badge"><?= $totalNaoVisualizados > 99 ? '99+' : $totalNaoVisualizados ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $adminBase ?>denuncias.php" class="sidebar-link <?= in_array($currentPage, ['denuncias.php', 'nova_denuncia.php', 'visualizar_denuncia.php'], true) ? 'active' : '' ?>" title="Denúncias">
                            <span class="sidebar-link-icon"><i class="fas fa-bullhorn"></i></span>
                            <span class="sidebar-link-content">
                                <span class="sidebar-link-text">
                                    <span class="sidebar-link-title">Denúncias</span>
                                    <span class="sidebar-link-caption">Canal interno de fiscalização</span>
                                </span>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <div class="menu-header"><span>Operação</span></div>
                <ul>
                    <?php if ($isSecretario): ?>
                        <li>
                            <a href="<?= $adminBase ?><?= $isAdmin ? 'simular_perfil.php?role=secretario' : 'secretario_dashboard.php' ?>" class="sidebar-link <?= in_array($currentPage, ['secretario_dashboard.php', 'revisao_secretario.php'], true) ? 'active' : '' ?>" title="Aprovação de Alvarás">
                                <span class="sidebar-link-icon"><i class="fas fa-signature" style="color:#c084fc;"></i></span>
                                <span class="sidebar-link-content">
                                    <span class="sidebar-link-text">
                                        <span class="sidebar-link-title">Aprovação de Alvarás</span>
                                        <span class="sidebar-link-caption">Fluxo de assinatura e decisão</span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isAnalista): ?>
                        <li>
                            <a href="<?= $adminBase ?><?= $isAdmin ? 'simular_perfil.php?role=analista' : 'requerimentos.php?status=Pendente' ?>" class="sidebar-link <?= ($currentPage === 'requerimentos.php' && isset($_GET['status']) && $_GET['status'] === 'Pendente') ? 'active' : '' ?>" title="Triagem de Protocolos">
                                <span class="sidebar-link-icon"><i class="fas fa-magnifying-glass" style="color:#38bdf8;"></i></span>
                                <span class="sidebar-link-content">
                                    <span class="sidebar-link-text">
                                        <span class="sidebar-link-title">Triagem de Protocolos</span>
                                        <span class="sidebar-link-caption">Pendências de análise inicial</span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isFiscal): ?>
                        <li>
                            <a href="<?= $adminBase ?><?= $isAdmin ? 'simular_perfil.php?role=fiscal' : 'fiscal_dashboard.php' ?>" class="sidebar-link <?= $currentPage === 'fiscal_dashboard.php' ? 'active' : '' ?>" title="Fiscalização de Obras">
                                <span class="sidebar-link-icon"><i class="fas fa-hard-hat" style="color:#34d399;"></i></span>
                                <span class="sidebar-link-content">
                                    <span class="sidebar-link-text">
                                        <span class="sidebar-link-title">Fiscalização de Obras</span>
                                        <span class="sidebar-link-caption">Vistorias e retornos ao fluxo</span>
                                    </span>
                                    <?php if ($totalAguardandoFiscal > 0): ?>
                                        <span class="badge bg-info sidebar-link-badge"><?= $totalAguardandoFiscal > 99 ? '99+' : $totalAguardandoFiscal ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="sidebar-section">
                <div class="menu-header"><span>Acervo e Dados</span></div>
                <ul>
                    <li class="nav-item">
                        <a href="#submenuRelatorios" data-bs-toggle="collapse" class="nav-link <?= $isDataSectionOpen ? '' : 'collapsed' ?>" title="Acervo e Dados">
                            <span class="sidebar-link-icon"><i class="fas fa-folder-open"></i></span>
                            <div class="sidebar-link-content">
                                <span class="sidebar-link-text">
                                    <span class="sidebar-link-title">Acervo e Dados</span>
                                    <span class="sidebar-link-caption">Histórico, indicadores e documentos</span>
                                </span>
                                <i class="fas fa-chevron-down sidebar-link-chevron"></i>
                            </div>
                        </a>
                        <div class="collapse <?= $isDataSectionOpen ? 'show' : '' ?>" id="submenuRelatorios">
                            <ul class="sidebar-submenu">
                                <li>
                                    <a href="<?= $adminBase ?>requerimentos_arquivados.php" class="sidebar-link <?= $currentPage === 'requerimentos_arquivados.php' ? 'active' : '' ?>">
                                        <span class="sidebar-link-icon"><i class="fas fa-box-archive"></i></span>
                                        <span class="sidebar-link-content">
                                            <span class="sidebar-link-text">
                                                <span class="sidebar-link-title">Arquivados</span>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= $adminBase ?>documentos_assinados.php" class="sidebar-link <?= $currentPage === 'documentos_assinados.php' ? 'active' : '' ?>">
                                        <span class="sidebar-link-icon"><i class="fas fa-file-signature"></i></span>
                                        <span class="sidebar-link-content">
                                            <span class="sidebar-link-text">
                                                <span class="sidebar-link-title">Doc. Assinados</span>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= $adminBase ?>estatisticas.php" class="sidebar-link <?= $currentPage === 'estatisticas.php' ? 'active' : '' ?>">
                                        <span class="sidebar-link-icon"><i class="fas fa-chart-column"></i></span>
                                        <span class="sidebar-link-content">
                                            <span class="sidebar-link-text">
                                                <span class="sidebar-link-title">Estatísticas</span>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= $adminBase ?>logs_email.php" class="sidebar-link <?= $currentPage === 'logs_email.php' ? 'active' : '' ?>">
                                        <span class="sidebar-link-icon"><i class="fas fa-envelope-open-text"></i></span>
                                        <span class="sidebar-link-content">
                                            <span class="sidebar-link-text">
                                                <span class="sidebar-link-title">Hist. Envios</span>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

            <?php if ($isHomologHost || (defined('MODO_HOMOLOG') && MODO_HOMOLOG)): ?>
                <div class="sidebar-section">
                    <div class="menu-header"><span>Homologação</span></div>
                    <ul>
                        <li>
                            <a href="<?= $adminBase ?>testes.php" class="sidebar-link <?= $currentPage === 'testes.php' ? 'active' : '' ?>" title="Painel de Testes">
                                <span class="sidebar-link-icon"><i class="fas fa-vial" style="color:#fbbf24;"></i></span>
                                <span class="sidebar-link-content">
                                    <span class="sidebar-link-text">
                                        <span class="sidebar-link-title">Painel de Testes</span>
                                        <span class="sidebar-link-caption">Ferramentas do ambiente de homologação</span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="sidebar-section">
                <div class="menu-header"><span>Administração</span></div>
                <ul>
                    <?php if ($isAdmin): ?>
                        <li>
                            <a href="<?= $adminBase ?>administradores.php" class="sidebar-link <?= $currentPage === 'administradores.php' ? 'active' : '' ?>" title="Gerenciar Usuários">
                                <span class="sidebar-link-icon"><i class="fas fa-users-gear"></i></span>
                                <span class="sidebar-link-content">
                                    <span class="sidebar-link-text">
                                        <span class="sidebar-link-title">Gerenciar Usuários</span>
                                        <span class="sidebar-link-caption">Perfis, acessos e equipe</span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="sidebar-utility">
                <div class="sidebar-utility-label">Ações rápidas</div>
                <a href="https://wa.me/5584981087357" target="_blank" class="sidebar-utility-link" title="Fale conosco no WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                    <span>Problemas? Fale conosco</span>
                </a>
                <?php if (isset($_SESSION['admin_nivel_original'])): ?>
                    <a href="<?= $adminBase ?>simular_perfil.php?sair=1" class="sidebar-utility-action" style="color:#fcd34d;">
                        <i class="fas fa-rotate-left"></i>
                        <span>Voltar ao meu perfil</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $adminBase ?>logout.php" class="sidebar-utility-action">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Sair</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-version">
                <span>SEMA Admin</span>
                <span>v3.9</span>
            </div>
        </div>
    </aside>

    <div class="content-overlay" id="contentOverlay"></div>

    <div class="content-wrapper">
        <div class="topbar">
            <div class="topbar-left">
                <button type="button" class="icon-button" id="sidebarToggle" aria-label="Alternar navegação">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="topbar-heading">
                    <div class="topbar-eyebrow">
                        <span class="pill"><?= htmlspecialchars($roleLabel) ?></span>
                        <?php if (defined('MODO_HOMOLOG') && MODO_HOMOLOG): ?>
                            <span class="pill" style="background:#fff7ed;color:#c2410c;">Homologação</span>
                        <?php else: ?>
                            <span>Secretaria Municipal de Meio Ambiente</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="topbar-title"><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <div class="topbar-search">
                <div class="topbar-search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input
                        type="search"
                        id="globalSearchInput"
                        class="topbar-search-input"
                        placeholder="Busca global de páginas, ações e fluxos"
                        autocomplete="off"
                    >
                    <span class="topbar-search-hint">Preparado para protocolos</span>
                </div>
                <div class="search-results" id="globalSearchResults">
                    <?php foreach ($searchItems as $item): ?>
                        <?php $searchText = strtolower($item['label'] . ' ' . $item['caption']); ?>
                        <a
                            href="<?= htmlspecialchars($item['url']) ?>"
                            class="search-result-item"
                            data-search-item
                            data-search-text="<?= htmlspecialchars($searchText) ?>"
                        >
                            <span class="search-result-icon"><i class="fas <?= htmlspecialchars($item['icon']) ?>"></i></span>
                            <span class="search-result-copy">
                                <span class="search-result-title"><?= htmlspecialchars($item['label']) ?></span>
                                <span class="search-result-caption"><?= htmlspecialchars($item['caption']) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <div class="search-empty d-none" id="globalSearchEmpty">Nenhum atalho encontrado para a busca atual.</div>
                </div>
            </div>

            <div class="topbar-right">
                <div class="notification-toggle">
                    <button type="button" class="icon-button" id="openNotificationSidebar" aria-label="Abrir notificações">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationTotal > 0): ?>
                            <span class="notification-badge"><?= $notificationTotal > 9 ? '9+' : $notificationTotal ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <div class="dropdown topbar-profile">
                    <a href="#" class="topbar-profile-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar">
                            <?php if ($avatarPath): ?>
                                <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Foto de Perfil">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </span>
                        <span class="user-info d-none d-md-flex">
                            <span class="user-name"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
                            <span class="user-role"><?= htmlspecialchars($roleLabel) ?></span>
                        </span>
                        <i class="fas fa-chevron-down text-muted" style="font-size:.72rem;"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="dropdown-header">Conta</div>
                        <a class="dropdown-item" href="<?= $adminBase ?>perfil.php">
                            <i class="fas fa-user me-2"></i> Meu Perfil
                        </a>
                        <?php if (isset($_SESSION['admin_nivel_original'])): ?>
                            <a class="dropdown-item" href="<?= $adminBase ?>simular_perfil.php?sair=1">
                                <i class="fas fa-rotate-left me-2"></i> Sair da simulação
                            </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?= $adminBase ?>logout.php">
                            <i class="fas fa-right-from-bracket me-2"></i> Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="notification-sidebar" id="notificationSidebar">
            <div class="notification-sidebar-header">
                <h5><i class="fas fa-bell me-2"></i>Notificações</h5>
                <button class="notification-sidebar-close" id="closeNotificationSidebar" type="button" aria-label="Fechar notificações">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="notification-sidebar-body">
                <?php if ($totalNaoVisualizados > 0): ?>
                    <div class="alert alert-info border-0" style="border-radius:16px;background:#eff6ff;color:#1d4ed8;">
                        <i class="fas fa-circle-info me-2"></i>
                        Você tem <?= $totalNaoVisualizados ?> requerimentos não visualizados.
                        <a href="<?= $adminBase ?>requerimentos.php?nao_visualizados=1" class="alert-link d-block mt-2">Abrir fila não visualizada</a>
                    </div>
                <?php endif; ?>

                <h6 class="mb-3" style="font-weight:700;color:var(--text);">Requerimentos recentes</h6>

                <ul class="notification-list">
                    <?php if ($todasNotificacoes): ?>
                        <?php foreach ($todasNotificacoes as $notif): ?>
                            <?php $statusSlug = strtolower(str_replace([' ', 'ã', 'á'], ['-', 'a', 'a'], $notif['status'])); ?>
                            <li class="notification-item-sidebar <?= $notif['visualizado'] ? '' : 'notification-unread' ?>">
                                <a href="<?= $adminBase ?>visualizar_requerimento.php?id=<?= (int) $notif['id'] ?>">
                                    <div class="notification-title">
                                        <?php if (!$notif['visualizado']): ?>
                                            <i class="fas fa-circle me-1" style="font-size:.55rem;color:var(--primary);"></i>
                                        <?php endif; ?>
                                        Requerimento #<?= htmlspecialchars($notif['protocolo']) ?>
                                    </div>
                                    <div class="notification-content"><?= htmlspecialchars($notif['requerente']) ?> · <?= htmlspecialchars($notif['tipo_alvara']) ?></div>
                                    <div class="notification-content">
                                        <span class="badge badge-status status-<?= htmlspecialchars($statusSlug) ?>"><?= htmlspecialchars($notif['status']) ?></span>
                                    </div>
                                    <div class="notification-time"><i class="far fa-clock me-1"></i><?= formataData($notif['data_envio']) ?></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="notification-item-sidebar">
                            <div class="notification-content">Nenhuma notificação disponível.</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if (isset($_SESSION['admin_nivel_original'])): ?>
            <div class="simulation-banner">
                <div class="simulation-banner-copy">
                    <strong>Modo simulação ativo:</strong>
                    <?php
                    $labelSetorSimulacao = [
                        'secretario' => 'Secretário(a) — Aprovação de Alvarás',
                        'analista' => 'Analista — Triagem de Protocolos',
                        'fiscal' => 'Fiscal de Obras',
                    ];
                    echo $labelSetorSimulacao[$_SESSION['admin_nivel']] ?? ucfirst($_SESSION['admin_nivel']);
                    ?>
                    <span class="d-block text-muted">Você está navegando com a visão deste setor.</span>
                </div>
                <a href="<?= $adminBase ?>simular_perfil.php?sair=1" class="btn btn-warning fw-semibold">
                    <i class="fas fa-times me-1"></i>Sair da simulação
                </a>
            </div>
        <?php endif; ?>

        <div class="content-wrapper-inner">
            <div class="main-content">
