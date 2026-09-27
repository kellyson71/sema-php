<?php
require_once 'conexao.php';
verificaLogin();
require_once __DIR__ . '/../includes/fiscalizacao_obras_helpers.php';

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$isAdmin = in_array($nivelAtual, ['admin', 'admin_geral'], true);
if ($nivelAtual !== 'fiscal' && !$isAdmin) {
    http_response_code(403);
    header('Location: index.php?error=sem_permissao');
    exit;
}

$denunciaId = (int) ($_GET['id'] ?? 0);
$dados = $denunciaId > 0 ? dadosDenunciaParaNotificacao($pdo, $denunciaId) : null;

if (!$dados) {
    header('Location: visualizar_denuncia.php?id=' . $denunciaId . '&error=nao_encontrada');
    exit;
}

header('Location: fiscalizacao_obras/nova.php?' . http_build_query($dados));
exit;
