<?php
require_once '../includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8");
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Função para verificar se o usuário está logado
function verificaLogin()
{
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Função para obter dados do administrador logado
function getDadosAdmin($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Função para formatar data
function formataData($data)
{
    return date('d/m/Y H:i', strtotime($data));
}
