<?php
require_once '../includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8");
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    
    // Instalação temporária (Módulo Denúncias)
    $pdo->exec("CREATE TABLE IF NOT EXISTS denuncias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        infrator_nome VARCHAR(255) NOT NULL,
        infrator_cpf_cnpj VARCHAR(20) NULL,
        infrator_endereco TEXT NULL,
        observacoes TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pendente',
        admin_id INT NOT NULL,
        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS denuncia_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        denuncia_id INT NOT NULL,
        nome_arquivo VARCHAR(255) NOT NULL,
        caminho_arquivo VARCHAR(255) NOT NULL,
        tipo_arquivo VARCHAR(50) NOT NULL,
        data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
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
