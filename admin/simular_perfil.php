<?php
require_once 'conexao.php';
verificaLogin();

// Apenas admin e admin_geral podem simular perfis
if (!in_array($_SESSION['admin_nivel'], ['admin', 'admin_geral'])) {
    // Se está em modo simulação e quer sair
    if (isset($_GET['sair']) && isset($_SESSION['admin_nivel_original'])) {
        $_SESSION['admin_nivel'] = $_SESSION['admin_nivel_original'];
        unset($_SESSION['admin_nivel_original']);
        header("Location: index.php");
        exit;
    }
    header("Location: index.php");
    exit;
}

$rolesSuportados = ['analista', 'fiscal', 'secretario', 'operador'];

// Sair da simulação
if (isset($_GET['sair'])) {
    if (isset($_SESSION['admin_nivel_original'])) {
        $_SESSION['admin_nivel'] = $_SESSION['admin_nivel_original'];
        unset($_SESSION['admin_nivel_original']);
    }
    header("Location: index.php");
    exit;
}

// Entrar em modo de simulação
if (isset($_GET['role']) && in_array($_GET['role'], $rolesSuportados)) {
    $role = $_GET['role'];
    // Guarda o nível original se ainda não estiver em simulação
    if (!isset($_SESSION['admin_nivel_original'])) {
        $_SESSION['admin_nivel_original'] = $_SESSION['admin_nivel'];
    }
    $_SESSION['admin_nivel'] = $role;

    $redirect = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : null;
    if ($redirect && str_starts_with($redirect, '/')) {
        header("Location: " . $redirect);
    } else {
        header("Location: requerimentos.php");
    }
    exit;
}

header("Location: index.php");
exit;
