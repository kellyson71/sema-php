<?php
// Script para configurar o banco de dados local
$host = 'localhost';
$user = 'root';
$pass = '';
$database = 'sema_db';

try {
    // Conectar sem especificar o banco
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado ao MySQL/MariaDB com sucesso!\n";

    // Criar banco de dados
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Banco de dados '$database' criado/verificado com sucesso!\n";

    // Usar o banco de dados
    $pdo->exec("USE $database");

    // Ler e executar o schema
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');

    // Dividir o schema em comandos individuais
    $commands = explode(';', $schema);

    foreach ($commands as $command) {
        $command = trim($command);
        if (!empty($command) && !preg_match('/^--/', $command) && !preg_match('/^\/\*/', $command)) {
            try {
                $pdo->exec($command);
                echo "Comando executado: " . substr($command, 0, 50) . "...\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Erro ao executar comando: " . $e->getMessage() . "\n";
                    echo "Comando: " . $command . "\n";
                }
            }
        }
    }

    echo "\nBanco de dados configurado com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
    echo "Tentando conectar sem senha...\n";

    try {
        $pdo = new PDO("mysql:host=$host", $user);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Conectado sem senha!\n";

        // Repetir o processo de criação do banco
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Banco de dados '$database' criado/verificado com sucesso!\n";
    } catch (PDOException $e2) {
        echo "Erro final: " . $e2->getMessage() . "\n";
        echo "Por favor, verifique se o MariaDB está rodando e se as credenciais estão corretas.\n";
    }
}
