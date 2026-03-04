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

// Função para verificar se o usuário está logado e com sessão/12h válida
function verificaLogin()
{
    $redirect = false;
    $uri = urlencode($_SERVER['REQUEST_URI']);

    // 1. O usuário não tem a variável essencial na sessão
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        $redirect = true;
    } else {
        // 2. O usuário tem sessão, mas vamos verificar as travas de segurança (Anti-Hijacking)
        $current_ip = $_SERVER['REMOTE_ADDR'];
        $current_ua = $_SERVER['HTTP_USER_AGENT'];
        
        $session_ip = $_SESSION['login_ip'] ?? '';
        $session_ua = $_SESSION['login_user_agent'] ?? '';
        
        if ($current_ip !== $session_ip || $current_ua !== $session_ua) {
            // Acesso detectado de outro IP ou Navegador com a mesma sessão! 
            // Força expiração imediata.
            $redirect = true;
        }

        // 3. Verifica se o Cookie de 12 horas expirou ou sumiu
        // (A sessão estrita do PHP muitas vezes sobrevive ao fechamento se o GC não rodar, 
        // mas o cookie expira perfeitamente após 12h).
        if (!isset($_COOKIE['sema_auth_persist'])) {
            $redirect = true;
        } else {
            // Valida o conteúdo do cookie
            $cookie_data = base64_decode($_COOKIE['sema_auth_persist']);
            $parts = explode('::', $cookie_data);
            if (count($parts) === 2) {
                $c_id = $parts[0];
                $c_token = $parts[1];
                if ($c_id != $_SESSION['admin_id'] || $c_token !== ($_SESSION['auth_token'] ?? '')) {
                    $redirect = true; // Cookie forjado
                }
            } else {
                $redirect = true;
            }
        }
    }

    if ($redirect) {
        // Limpar tudo por segurança
        session_unset();
        session_destroy();
        setcookie('sema_auth_persist', '', time() - 3600, '/');
        
        header("Location: login.php?redirect=" . $uri);
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
