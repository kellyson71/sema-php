<?php
require_once 'conexao.php';
verificaLogin();

if (!isset($_GET['id'])) {
    exit('ID não fornecido');
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT mensagem FROM email_logs WHERE id = ?");
    $stmt->execute([$id]);
    $log = $stmt->fetch();

    if (!$log) {
        exit('Log não encontrado');
    }

    // Limpar output buffers anteriores se houver
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Imprimir apenas o conteúdo do email
    echo $log['mensagem'];
} catch (PDOException $e) {
    exit('Erro ao buscar dados');
}
