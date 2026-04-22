<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

$adminBase = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) !== 'admin') ? '../' : '';
$adminData = getDadosAdmin($pdo, $_SESSION['admin_id']);
$currentPage = basename($_SERVER['PHP_SELF']);

$totalNaoVisualizados = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn();
$totalAguardandoFiscal = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aguardando Fiscalização'")->fetchColumn();
ensureAdminNotificationTables($pdo);
$notificationCounts = fetchAdminNotificationCounts($pdo, (int) $_SESSION['admin_id']);
$totalNotificacoes = $notificationCounts['total'];
$notificationTotal = $notificationCounts['unread'];
$notificacoesNaoLidas = fetchAdminNotifications($pdo, (int) $_SESSION['admin_id'], 'unread', 20, 0);
$notificacoesLidas = fetchAdminNotifications($pdo, (int) $_SESSION['admin_id'], 'read', 20, 0);

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
    'notificacoes.php' => 'Notificações',
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
$isOperacaoSectionOpen = in_array($currentPage, ['secretario_dashboard.php', 'revisao_secretario.php', 'fiscal_dashboard.php'], true)
    || ($currentPage === 'requerimentos.php' && isset($_GET['status']) && $_GET['status'] === 'Pendente');

$searchItems = [
    ['label' => 'Dashboard', 'caption' => 'Visão geral do painel', 'url' => $adminBase . 'index.php', 'icon' => 'fa-gauge-high'],
    ['label' => 'Requerimentos', 'caption' => 'Lista principal de protocolos', 'url' => $adminBase . 'requerimentos.php', 'icon' => 'fa-clipboard-list'],
    ['label' => 'Notificações', 'caption' => 'Central operacional do admin', 'url' => $adminBase . 'notificacoes.php', 'icon' => 'fa-bell'],
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
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&family=Geist+Mono:wght@500&family=Dancing+Script:wght@400;700&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #102117;
            --ink-2: #21372b;
            --muted: #66756d;
            --muted-2: #8e9b94;
            --line: #e3e8e4;
            --line-strong: #d3dbd5;
            --bg: #f4f6f3;
            --surface: #ffffff;
            --surface-soft: #f7f9f7;
            --surface-tint: #edf3ef;
            --primary: #14532d;
            --primary-strong: #0f4425;
            --primary-soft: #e5f2ea;
            --primary-soft-2: #d8eadf;
            --sidebar-bg: #14532d;
            --sidebar-bg-2: #14532d;
            --sidebar-line: rgba(255, 255, 255, 0.08);
            --sidebar-text: rgba(255, 255, 255, 0.78);
            --sidebar-text-strong: #ffffff;
            --sidebar-active: rgba(255, 255, 255, 0.12);
            --warning: #d89a00;
            --warning-soft: #fdf5d7;
            --info: #3762d9;
            --info-soft: #e8effd;
            --success: #0d5433;
            --success-soft: #def2e6;
            --danger: #b13232;
            --danger-soft: #fce7e7;
            --sidebar-width: 258px;
            --sidebar-collapsed-width: 84px;
            --topbar-height: 72px;
            --radius-lg: 22px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --shell-shadow: 0 12px 28px rgba(16, 33, 23, 0.06);
            --card-shadow: 0 8px 20px rgba(16, 33, 23, 0.04);
        }

        * {
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        html,
        body {
            min-height: 100%;
            background: var(--bg);
        }

        body {
            margin: 0;
            font-family: 'Inter Tight', system-ui, sans-serif;
            color: var(--ink);
            background: var(--bg);
            overflow-x: hidden;
        }

        body.sidebar-collapsed {
            --sidebar-width: var(--sidebar-collapsed-width);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .mono {
            font-family: 'Geist Mono', ui-monospace, monospace;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, var(--sidebar-bg-2) 100%);
            color: var(--sidebar-text);
            border-right: 1px solid var(--sidebar-line);
            display: flex;
            flex-direction: column;
            padding: 18px 14px 14px;
            transition: width 0.24s ease, transform 0.24s ease;
            z-index: 1040;
            overflow: hidden;
        }

        .sidebar::before {
            display: none;
        }

        .sidebar > * {
            position: relative;
            z-index: 1;
        }

        .sidebar-header {
            padding: 2px 6px 18px;
        }

        .sidebar-brand {
            display: flex;
            justify-content: center;
            text-align: center;
        }

        .sidebar-logo {
            height: 52px;
            width: auto;
            max-width: 164px;
            object-fit: contain;
            filter: brightness(1.02);
            display: block;
            margin: 0 auto 10px;
        }

        .sidebar-brand-copy {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .sidebar-brand-subtitle {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.2;
            letter-spacing: 0.03em;
            font-weight: 700;
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
            margin-right: -4px;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.14);
            border-radius: 999px;
        }

        .sidebar-section {
            margin-bottom: 18px;
        }

        .menu-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 8px 10px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255, 255, 255, 0.48);
            font-weight: 700;
        }

        .sidebar-menu ul,
        .sidebar-submenu {
            list-style: none;
            margin: 0;
            padding: 0;
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
            min-height: 46px;
            padding: 7px 10px;
            border: 1px solid transparent;
            border-radius: 14px;
            background: transparent;
            color: var(--sidebar-text) !important;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active,
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link:not(.collapsed) {
            color: var(--sidebar-text-strong) !important;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.05);
        }

        .sidebar-link.active {
            background: var(--sidebar-active);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .sidebar-link-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.88rem;
            color: inherit;
            flex-shrink: 0;
        }

        .sidebar-link-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }

        .sidebar-link-text {
            min-width: 0;
        }

        .sidebar-link-title {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.15;
            color: inherit;
        }

        .sidebar-link-caption {
            display: block;
            margin-top: 2px;
            font-size: 0.72rem;
            line-height: 1.2;
            color: rgba(255, 255, 255, 0.56);
        }

        .sidebar-link-badge {
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 0.68rem;
            font-weight: 700;
            box-shadow: none;
        }

        .sidebar-link-chevron {
            font-size: 0.72rem;
            opacity: 0.72;
            transition: transform 0.2s ease;
        }

        .sidebar-menu .nav-link:not(.collapsed) .sidebar-link-chevron {
            transform: rotate(180deg);
        }

        .sidebar-submenu {
            margin: 8px 0 2px 16px;
            padding-left: 16px;
            border-left: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-submenu .sidebar-link {
            min-height: 44px;
            border-radius: 14px;
        }

        .sidebar-submenu .sidebar-link-icon {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            font-size: 0.78rem;
        }

        .sidebar-utility {
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.09);
        }

        .sidebar-utility-label {
            margin: 0 10px 8px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(238, 244, 239, 0.44);
            font-weight: 700;
        }

        .sidebar-utility-link,
        .sidebar-utility-action {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 8px;
            padding: 11px 12px;
            border-radius: 14px;
            color: var(--sidebar-text);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .sidebar-utility-link:hover,
        .sidebar-utility-action:hover {
            color: var(--sidebar-text-strong);
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .content-wrapper {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.24s ease;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 520px) auto;
            align-items: center;
            gap: 16px;
            min-height: var(--topbar-height);
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .icon-button {
            width: 42px;
            height: 42px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease, color 0.2s ease;
        }

        .icon-button:hover {
            transform: translateY(-1px);
            color: var(--primary);
            background: #fff;
            border-color: var(--primary-soft-2);
        }

        .topbar-heading {
            min-width: 0;
        }

        .topbar-eyebrow {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 2px;
            font-size: 0.74rem;
            color: var(--muted);
        }

        .topbar-eyebrow .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-strong);
            font-weight: 700;
        }

        .topbar-title {
            margin: 0;
            font-size: 1.18rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--ink);
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
            min-height: 46px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .topbar-search-box:focus-within {
            border-color: rgba(13, 84, 51, 0.24);
            box-shadow: 0 0 0 4px rgba(13, 84, 51, 0.08);
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
            color: var(--ink);
            font-size: 0.92rem;
        }

        .topbar-search-input::placeholder {
            color: #8b9991;
        }

        .topbar-search-hint {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            background: var(--surface-soft);
            border: 1px solid var(--line);
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            display: none;
            max-height: 380px;
            overflow-y: auto;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shell-shadow);
        }

        .search-results.active {
            display: block;
        }

        .search-empty {
            padding: 14px;
            text-align: center;
            color: var(--muted);
            font-size: 0.84rem;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            color: var(--ink);
            transition: background-color 0.2s ease, transform 0.2s ease;
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
            font-size: 0.9rem;
            font-weight: 700;
        }

        .search-result-caption {
            display: block;
            font-size: 0.75rem;
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
            top: -3px;
            right: -3px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--danger);
            color: #fff;
            font-size: 0.66rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 3px var(--bg);
        }

        .topbar-profile {
            min-width: 0;
        }

        .topbar-profile-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 5px 8px 5px 5px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            color: var(--ink);
        }

        .topbar-profile-toggle:hover {
            background: #fff;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--primary-soft), #dceae2);
            color: var(--primary-strong);
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
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.72rem;
            color: var(--muted);
            line-height: 1.2;
        }

        .dropdown-menu {
            min-width: 260px;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: var(--shell-shadow);
        }

        .dropdown-header {
            padding: 10px 14px;
            color: var(--muted);
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .dropdown-item {
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.88rem;
        }

        .dropdown-item:hover {
            background: var(--surface-soft);
        }

        .content-wrapper-inner {
            padding: 20px 18px 26px;
        }

        .main-content {
            padding: 0;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            box-shadow: var(--card-shadow);
            background: rgba(255, 255, 255, 0.94);
        }

        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
            background: transparent;
            font-weight: 700;
        }

        .card-body {
            padding: 20px;
        }

        .section-title {
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--ink);
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1.3;
            border: 1px solid transparent;
        }

        .status-em-analise { background-color: var(--warning-soft); color: #7a5b00; border-color: #f2d56c; }
        .status-aprovado { background-color: var(--success-soft); color: #166534; border-color: #9fddb4; }
        .status-reprovado { background-color: #fff1f2; color: #9f1239; border-color: #fecdd3; }
        .status-pendente { background-color: var(--info-soft); color: #1e429f; border-color: #c7d7fb; }
        .status-cancelado { background-color: #f4f6f5; color: #42514a; border-color: #d9e1dc; }
        .status-aguardando-fiscalização,
        .status-aguardando-fiscalizacao { background-color: #eef6ff; color: #0c4a6e; border-color: #bfdbfe; }
        .status-apto-a-gerar-alvará,
        .status-apto-a-gerar-alvara { background-color: #f6f0ff; color: #6d28d9; border-color: #ddd6fe; }
        .status-alvará-emitido,
        .status-alvara-emitido { background-color: var(--success-soft); color: #047857; border-color: #9fddb4; }
        .status-finalizado { background-color: var(--success-soft); color: #0d5433; border-color: #89d1a3; }
        .status-indeferido { background-color: #f5f7f6; color: #46554d; border-color: #d4ddd7; }
        .status-aguardando-boleto { background-color: #fff7e8; color: #8a4b08; border-color: #f4ca8b; }
        .status-boleto-pago { background-color: #e9f8f0; color: #0f766e; border-color: #9edbcc; }

        .notification-sidebar {
            position: absolute;
            top: calc(100% + 14px);
            right: 0;
            width: min(430px, calc(100vw - 28px));
            max-height: min(78vh, 720px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.99);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 22px 48px rgba(16, 33, 23, 0.14);
            opacity: 0;
            transform: translateY(10px) scale(0.98);
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 1060;
        }

        .notification-sidebar.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .notification-sidebar-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 18px 14px;
            border-bottom: 1px solid var(--line);
            background:
                radial-gradient(circle at top right, rgba(20, 83, 45, 0.1), transparent 32%),
                linear-gradient(180deg, #ffffff 0%, #fbfcfb 100%);
        }

        .notification-sidebar-header h5 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 800;
            color: var(--ink);
        }

        .notification-sidebar-header p {
            margin: 0;
            font-size: 0.78rem;
            color: var(--muted);
        }

        .notification-sidebar-close {
            width: 40px;
            height: 40px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            color: var(--muted);
        }

        .notification-sidebar-body {
            padding: 16px 18px 18px;
            overflow-y: auto;
        }

        .notification-summary-card {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .notification-summary-item {
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface-soft);
        }

        .notification-summary-item strong {
            display: block;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--ink);
        }

        .notification-summary-item span {
            font-size: 0.76rem;
            color: var(--muted);
            font-weight: 700;
        }

        .notification-panel-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .notification-tabs {
            display: inline-flex;
            align-items: center;
            padding: 4px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            gap: 4px;
        }

        .notification-tab {
            min-height: 34px;
            padding: 0 12px;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .notification-tab.active {
            background: var(--primary-soft);
            color: var(--primary-strong);
        }

        .notification-panel-link {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary);
        }

        .notification-tab-panel {
            display: none;
        }

        .notification-tab-panel.active {
            display: block;
        }

        .notification-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification-item-sidebar {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            transition: border-color 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
        }

        .notification-item-sidebar:hover {
            transform: translateY(-1px);
            border-color: var(--line-strong);
            background: var(--surface-soft);
        }

        .notification-item-sidebar a,
        .notification-item-sidebar .notification-empty {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr);
            gap: 12px;
            padding: 14px;
        }

        .notification-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--surface-tint);
            color: var(--primary-strong);
        }

        .notification-icon-badge.accent-blue { background: #e8effd; color: #1d4ed8; }
        .notification-icon-badge.accent-amber { background: #fff3dc; color: #b45309; }
        .notification-icon-badge.accent-teal { background: #e6f7f4; color: #0f766e; }
        .notification-icon-badge.accent-slate { background: #eef2f0; color: #475569; }
        .notification-icon-badge.accent-green { background: var(--primary-soft); color: var(--primary-strong); }

        .notification-copy {
            min-width: 0;
        }

        .notification-title {
            margin-bottom: 4px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .notification-content {
            margin-bottom: 6px;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.45;
        }

        .notification-time {
            font-size: 0.74rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-unread {
            background: #fbfdfb;
            border-color: rgba(20, 83, 45, 0.16);
            box-shadow: inset 3px 0 0 var(--primary);
        }

        .notification-unread-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
            flex-shrink: 0;
        }

        .notification-empty {
            color: var(--muted);
            align-items: center;
        }

        .content-overlay {
            position: fixed;
            inset: 0;
            background: rgba(11, 31, 23, 0.38);
            display: none;
            z-index: 1050;
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
            padding: 15px 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #fff7df 0%, #fff6ea 100%);
            border: 1px solid #f4d19c;
            box-shadow: var(--card-shadow);
            color: #9a3412;
        }

        .simulation-banner-copy {
            font-size: 0.88rem;
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
        body.sidebar-collapsed .sidebar-utility-label,
        body.sidebar-collapsed .sidebar-utility span {
            display: none !important;
        }

        body.sidebar-collapsed .sidebar-link,
        body.sidebar-collapsed .sidebar-menu .nav-link,
        body.sidebar-collapsed .sidebar-brand,
        body.sidebar-collapsed .sidebar-utility-link,
        body.sidebar-collapsed .sidebar-utility-action {
            justify-content: center;
        }

        body.sidebar-collapsed .sidebar-link,
        body.sidebar-collapsed .sidebar-menu .nav-link {
            padding-inline: 0;
        }

        body.sidebar-collapsed .sidebar-submenu {
            display: none !important;
        }

        body.sidebar-collapsed .sidebar-link-content {
            display: none;
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
                top: 14px;
                bottom: 14px;
                left: 14px;
                width: min(286px, calc(100vw - 28px));
                border-radius: 18px;
                transform: translateX(calc(-100% - 20px));
                box-shadow: 0 28px 55px rgba(11, 31, 23, 0.26);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .content-wrapper {
                margin-left: 0;
            }

            .topbar {
                padding: 14px 16px;
            }
        }

        @media (max-width: 767px) {
            .topbar {
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 12px;
                padding: 12px 14px;
            }

            .topbar-title {
                font-size: 1.08rem;
            }

            .topbar-search-hint,
            .topbar-profile-toggle .user-info,
            .topbar-eyebrow span:not(.pill) {
                display: none !important;
            }

            .content-wrapper-inner {
                padding: 18px 14px 24px;
            }

            .notification-sidebar {
                top: calc(100% + 10px);
                right: -8px;
                width: min(430px, calc(100vw - 20px));
                max-height: min(76vh, 620px);
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
                <span class="sidebar-brand-copy">
                    <img src="<?= $adminBase ?>../assets/img/Logo_sema.png" alt="SEMA" class="sidebar-logo">
                    <span class="sidebar-brand-subtitle">Painel Administrativo</span>
                </span>
            </a>
        </div>

        <div class="sidebar-scroll sidebar-menu">
            <div class="sidebar-section">
                <div class="menu-header"><span>Principal</span></div>
                <ul>
                    <li>
                        <a href="<?= $adminBase ?>index.php" class="sidebar-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" title="Painel Inicial">
                            <span class="sidebar-link-icon"><i class="fas fa-house"></i></span>
                            <span class="sidebar-link-content">
                                <span class="sidebar-link-text">
                                    <span class="sidebar-link-title">Painel Inicial</span>
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
                <ul>
                    <li class="nav-item">
                        <a href="#submenuOperacao" data-bs-toggle="collapse"
                           class="nav-link <?= $isOperacaoSectionOpen ? '' : 'collapsed' ?>" title="Operação">
                            <span class="sidebar-link-icon"><i class="fas fa-briefcase" style="color:#86efac;"></i></span>
                            <div class="sidebar-link-content">
                                <span class="sidebar-link-text">
                                    <span class="sidebar-link-title">Operação</span>
                                </span>
                                <i class="fas fa-chevron-down sidebar-link-chevron"></i>
                            </div>
                        </a>
                        <div class="collapse <?= $isOperacaoSectionOpen ? 'show' : '' ?>" id="submenuOperacao">
                            <ul class="sidebar-submenu">
                                <?php if ($isSecretario): ?>
                                    <li>
                                        <a href="<?= $adminBase ?><?= $isAdmin ? 'simular_perfil.php?role=secretario' : 'secretario_dashboard.php' ?>" class="sidebar-link <?= in_array($currentPage, ['secretario_dashboard.php', 'revisao_secretario.php'], true) ? 'active' : '' ?>" title="Aprovação de Alvarás">
                                            <span class="sidebar-link-icon"><i class="fas fa-signature" style="color:#c084fc;"></i></span>
                                            <span class="sidebar-link-content">
                                                <span class="sidebar-link-text">
                                                    <span class="sidebar-link-title">Aprovação de Alvarás</span>
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($isAnalista): ?>
                                    <li>
                                        <a href="<?= $adminBase ?><?= $isAdmin ? 'simular_perfil.php?role=analista' : 'requerimentos.php?status=Pendente' ?>" class="sidebar-link <?= ($currentPage === 'requerimentos.php' && isset($_GET['status']) && $_GET['status'] === 'Pendente') ? 'active' : '' ?>" title="Triagem de Protocolos">
                                            <span class="sidebar-link-icon"><i class="fas fa-magnifying-glass" style="color:#86efac;"></i></span>
                                            <span class="sidebar-link-content">
                                                <span class="sidebar-link-text">
                                                    <span class="sidebar-link-title">Triagem de Protocolos</span>
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($isFiscal): ?>
                                    <li>
                                        <a href="<?= $adminBase ?><?= $isAdmin ? 'simular_perfil.php?role=fiscal' : 'fiscal_dashboard.php' ?>" class="sidebar-link <?= $currentPage === 'fiscal_dashboard.php' ? 'active' : '' ?>" title="Fiscalização de Obras">
                                            <span class="sidebar-link-icon"><i class="fas fa-hard-hat" style="color:#86efac;"></i></span>
                                            <span class="sidebar-link-content">
                                                <span class="sidebar-link-text">
                                                    <span class="sidebar-link-title">Fiscalização de Obras</span>
                                                </span>
                                                <?php if ($totalAguardandoFiscal > 0): ?>
                                                    <span class="badge sidebar-link-badge" style="background:rgba(134,239,172,.18);color:#86efac;border:1px solid rgba(134,239,172,.3);"><?= $totalAguardandoFiscal > 99 ? '99+' : $totalAguardandoFiscal ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </li>
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
                        placeholder="Buscar atalhos, requerimentos ou nomes..."
                        autocomplete="off"
                    >
                    <span class="topbar-search-hint">/ buscar</span>
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
                    <button type="button" class="icon-button" id="openNotificationSidebar" aria-label="Abrir notificações" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationTotal > 0): ?>
                            <span class="notification-badge"><?= $notificationTotal > 9 ? '9+' : $notificationTotal ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notification-sidebar" id="notificationSidebar">
                        <div class="notification-sidebar-header">
                            <div>
                                <h5><i class="fas fa-bell me-2"></i>Notificações</h5>
                                <p>Central do admin com eventos operacionais recentes.</p>
                            </div>
                            <button class="notification-sidebar-close" id="closeNotificationSidebar" type="button" aria-label="Fechar notificações">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="notification-sidebar-body">
                            <div class="notification-summary-card">
                                <div class="notification-summary-item">
                                    <strong><?= (int) $notificationCounts['unread'] ?></strong>
                                    <span>Notificações não lidas</span>
                                </div>
                                <div class="notification-summary-item">
                                    <strong><?= (int) $totalNaoVisualizados ?></strong>
                                    <span>Protocolos ainda não abertos</span>
                                </div>
                            </div>

                            <div class="notification-panel-actions">
                                <div class="notification-tabs" id="notificationTabs">
                                    <button type="button" class="notification-tab active" data-notification-tab="unread">Não lidas <span><?= (int) $notificationCounts['unread'] ?></span></button>
                                    <button type="button" class="notification-tab" data-notification-tab="read">Lidas <span><?= (int) $notificationCounts['read'] ?></span></button>
                                </div>
                                <a href="<?= $adminBase ?>notificacoes.php?acao=marcar_todas" class="notification-panel-link">Marcar todas</a>
                            </div>

                            <?php if ($totalNaoVisualizados > 0): ?>
                                <div class="alert alert-info border-0 mb-3" style="border-radius:16px;background:#eff6ff;color:#1d4ed8;">
                                    <i class="fas fa-circle-info me-2"></i>
                                    Você tem <?= $totalNaoVisualizados ?> protocolo(s) ainda não abertos.
                                    <a href="<?= $adminBase ?>requerimentos.php?nao_visualizados=1" class="alert-link d-block mt-2">Abrir fila de protocolos</a>
                                </div>
                            <?php endif; ?>

                            <div class="notification-tab-panel active" data-notification-panel="unread">
                                <ul class="notification-list">
                                    <?php if ($notificacoesNaoLidas): ?>
                                        <?php foreach ($notificacoesNaoLidas as $notif): ?>
                                            <li class="notification-item-sidebar notification-unread">
                                                <a href="<?= $adminBase ?>notificacao_ir.php?id=<?= (int) $notif['id'] ?>">
                                                    <span class="notification-icon-badge <?= htmlspecialchars($notif['accent_class']) ?>">
                                                        <i class="fas <?= htmlspecialchars($notif['icon']) ?>"></i>
                                                    </span>
                                                    <span class="notification-copy">
                                                        <span class="notification-title">
                                                            <span class="notification-unread-dot"></span>
                                                            <?= htmlspecialchars($notif['titulo']) ?>
                                                        </span>
                                                        <span class="notification-content"><?= htmlspecialchars($notif['descricao']) ?></span>
                                                        <span class="notification-time"><i class="far fa-clock"></i><?= formataData($notif['criado_em']) ?></span>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="notification-item-sidebar">
                                            <div class="notification-empty">
                                                <span class="notification-icon-badge accent-green"><i class="fas fa-check"></i></span>
                                                <span class="notification-copy">
                                                    <span class="notification-title">Sem notificações não lidas</span>
                                                    <span class="notification-content">As novas entradas vão aparecer aqui primeiro.</span>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div class="notification-tab-panel" data-notification-panel="read">
                                <ul class="notification-list">
                                    <?php if ($notificacoesLidas): ?>
                                        <?php foreach ($notificacoesLidas as $notif): ?>
                                            <li class="notification-item-sidebar">
                                                <a href="<?= $adminBase ?>notificacao_ir.php?id=<?= (int) $notif['id'] ?>">
                                                    <span class="notification-icon-badge <?= htmlspecialchars($notif['accent_class']) ?>">
                                                        <i class="fas <?= htmlspecialchars($notif['icon']) ?>"></i>
                                                    </span>
                                                    <span class="notification-copy">
                                                        <span class="notification-title"><?= htmlspecialchars($notif['titulo']) ?></span>
                                                        <span class="notification-content"><?= htmlspecialchars($notif['descricao']) ?></span>
                                                        <span class="notification-time"><i class="far fa-clock"></i><?= formataData($notif['criado_em']) ?></span>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="notification-item-sidebar">
                                            <div class="notification-empty">
                                                <span class="notification-icon-badge accent-green"><i class="fas fa-check"></i></span>
                                                <span class="notification-copy">
                                                    <span class="notification-title">Nenhuma notificação lida</span>
                                                    <span class="notification-content">Ao abrir uma notificação, ela passa a aparecer aqui.</span>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div class="mt-3">
                                <a href="<?= $adminBase ?>notificacoes.php" class="notification-panel-link">Abrir página completa</a>
                            </div>
                        </div>
                    </div>
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
