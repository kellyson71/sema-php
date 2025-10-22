<?php
// Script para configurar o banco SQLite local
$db_path = __DIR__ . '/database/sema_local.db';
$schema_path = __DIR__ . '/database/schema_sqlite.sql';

try {
    // Criar diretório se não existir
    $db_dir = dirname($db_path);
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }

    // Conectar ao SQLite
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado ao SQLite com sucesso!\n";
    echo "Banco de dados: $db_path\n";

    // Ler e executar o schema
    $schema = file_get_contents($schema_path);

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

    echo "\nBanco SQLite configurado com sucesso!\n";
    echo "Arquivo do banco: $db_path\n";

    // Verificar se as tabelas foram criadas
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas criadas: " . implode(', ', $tables) . "\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
