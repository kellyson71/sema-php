<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

$notificationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($notificationId <= 0) {
    header('Location: notificacoes.php');
    exit;
}

$notification = findAdminNotificationById($pdo, $notificationId, (int) $_SESSION['admin_id']);
if (!$notification) {
    header('Location: notificacoes.php');
    exit;
}

markAdminNotificationAsRead($pdo, $notificationId, (int) $_SESSION['admin_id']);

$destino = $notification['destino'] ?? 'notificacoes.php';
if (!preg_match('/^[a-z0-9_\\-\\.\\?&=]+$/i', $destino)) {
    $destino = 'notificacoes.php';
}

header('Location: ' . $destino);
exit;
