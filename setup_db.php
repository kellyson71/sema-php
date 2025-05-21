<?php
// Script para criação do banco de dados e tabelas
require_once 'includes/config.php';

// Definir variáveis de conexão para o servidor MySQL
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;

// Determinar se está sendo executado via web ou linha de comando
$isConsole = php_sapi_name() == 'cli';

// Função para mostrar mensagens no console
function consoleOutput($message)
{
    if (php_sapi_name() == 'cli') {
        echo $message . PHP_EOL;
        // Força a saída imediata
        flush();
    }
}

consoleOutput("Iniciando configuração do banco de dados SEMA...");

try {
    // Primeiro conectar ao servidor sem especificar banco de dados
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    if ($isConsole) {
        consoleOutput("Conectado ao servidor MySQL com sucesso.");
    } else {
        echo "<h2>Configurando banco de dados SEMA</h2>";
    }

    // Ler o conteúdo do arquivo SQL
    $sql = file_get_contents('database/schema.sql');

    if ($isConsole) {
        consoleOutput("Arquivo schema.sql lido com sucesso.");
    }

    // Executar os comandos SQL
    $result = $pdo->exec($sql);

    if ($isConsole) {
        consoleOutput("Schema SQL executado com sucesso.");
    }

    // Agora conectar ao banco de dados criado
    $pdo = new PDO("mysql:host=$host;dbname=sema_db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    if ($isConsole) {
        consoleOutput("Conectado ao banco de dados sema_db com sucesso.");
    }

    // Verificar se o administrador já existe
    $stmt = $pdo->prepare("SELECT id FROM administradores WHERE email = 'admin@sema.gov.br'");
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        // Inserir o administrador padrão apenas se ele não existir
        $stmt = $pdo->prepare("INSERT INTO administradores (nome, email, senha, nivel) 
                              VALUES ('Administrador', 'admin@sema.gov.br', 
                                     '$2y$10$KnmRiQO/1jKcMZGgBZc4ieLu2W.OZsHyI6rLJf.B9EKt2FU/.JCx.', 'admin')");
        $stmt->execute();

        if ($isConsole) {
            consoleOutput("Um usuário administrador padrão foi criado.");
        } else {
            echo "<p>Um usuário administrador padrão foi criado:</p>";
        }
    } else {
        if ($isConsole) {
            consoleOutput("O usuário administrador padrão já existe.");
        } else {
            echo "<p>O usuário administrador padrão já existe:</p>";
        }
    }

    if ($isConsole) {
        consoleOutput("Banco de dados configurado com sucesso!");
        consoleOutput("As seguintes tabelas foram criadas ou já existem:");
        consoleOutput("- requerentes");
        consoleOutput("- proprietarios");
        consoleOutput("- requerimentos");
        consoleOutput("- documentos");
        consoleOutput("- administradores");
        consoleOutput("- historico_acoes");
        consoleOutput("");
        consoleOutput("Dados de acesso do administrador:");
        consoleOutput("Email: admin@sema.gov.br");
        consoleOutput("Senha: admin123");
    } else {
        echo "<h2>Banco de dados configurado com sucesso!</h2>";
        echo "<p style='color:green;'>As seguintes tabelas foram criadas ou já existem:</p>";
        echo "<ul>";
        echo "<li>requerentes</li>";
        echo "<li>proprietarios</li>";
        echo "<li>requerimentos</li>";
        echo "<li>documentos</li>";
        echo "<li>administradores</li>";
        echo "<li>historico_acoes</li>";
        echo "</ul>";

        echo "<ul>";
        echo "<li><strong>Email:</strong> admin@sema.gov.br</li>";
        echo "<li><strong>Senha:</strong> admin123</li>";
        echo "</ul>";

        echo "<p><a href='index.php'>Clique aqui</a> para acessar o sistema.</p>";
    }
} catch (PDOException $e) {
    if ($isConsole) {
        consoleOutput("Erro ao configurar o banco de dados: " . $e->getMessage());
        consoleOutput("Verifique as configurações de conexão em includes/config.php");
    } else {
        echo "<h2>Erro ao configurar o banco de dados</h2>";
        echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
        echo "<p>Verifique as configurações de conexão em includes/config.php</p>";
    }
}