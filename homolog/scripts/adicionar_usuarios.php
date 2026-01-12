<?php

/**
 * Script para adicionar novos usuários administradores
 * Este script gera as senhas criptografadas corretamente e insere os usuários
 */

// Configuração da conexão com o banco
require_once '../includes/database.php';

// Dados dos novos usuários
$usuarios = [
    [
        'nome' => 'Julia',
        'email' => 'julia@sema.gov.br',
        'senha' => '1300'
    ],
    [
        'nome' => 'Samara',
        'email' => 'samara@sema.gov.br',
        'senha' => '2518'
    ],
    [
        'nome' => 'Sabrina',
        'email' => 'sabrina@sema.gov.br',
        'senha' => '2505'
    ],
    [
        'nome' => 'Isabely',
        'email' => 'isabely@sema.gov.br',
        'senha' => '1348'
    ]
];

try {
    echo "Iniciando inserção de usuários...\n\n";

    foreach ($usuarios as $usuario) {
        // Verificar se o usuário já existe
        $stmt = $pdo->prepare("SELECT id FROM administradores WHERE email = ?");
        $stmt->execute([$usuario['email']]);

        if ($stmt->fetch()) {
            echo "❌ Usuário {$usuario['nome']} ({$usuario['email']}) já existe!\n";
            continue;
        }

        // Criptografar a senha
        $senhaHash = password_hash($usuario['senha'], PASSWORD_DEFAULT);

        // Inserir o usuário
        $stmt = $pdo->prepare("
            INSERT INTO administradores (nome, email, senha, nivel, ativo, data_cadastro) 
            VALUES (?, ?, ?, 'operador', TRUE, NOW())
        ");

        if ($stmt->execute([$usuario['nome'], $usuario['email'], $senhaHash])) {
            echo "✅ Usuário {$usuario['nome']} criado com sucesso!\n";
            echo "   Email: {$usuario['email']}\n";
            echo "   Senha: {$usuario['senha']}\n";
            echo "   Nível: operador\n\n";
        } else {
            echo "❌ Erro ao criar usuário {$usuario['nome']}\n\n";
        }
    }

    // Verificar usuários criados
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "USUÁRIOS ADMINISTRADORES CADASTRADOS:\n";
    echo str_repeat("=", 50) . "\n";

    $stmt = $pdo->query("
        SELECT id, nome, email, nivel, ativo, data_cadastro 
        FROM administradores 
        ORDER BY data_cadastro DESC
    ");

    while ($admin = $stmt->fetch()) {
        $status = $admin['ativo'] ? 'Ativo' : 'Inativo';
        echo "ID: {$admin['id']} | {$admin['nome']} | {$admin['email']} | {$admin['nivel']} | {$status}\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n✅ Script finalizado!\n";
