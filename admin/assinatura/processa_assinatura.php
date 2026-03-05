<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug para o usuário ver o progresso
echo "DEBUG: Iniciando processa_assinatura.php...<br>";

// WORKAROUND: Mudar o diretório de execução para a pasta 'admin' para que os requires
// relativos de arquivos dependentes (como conexao.php) funcionem mesmo se o arquivo
// conexao.php estiver antigo no servidor.
echo "DEBUG: Alterando diretório de execução para /admin/...<br>";
chdir(dirname(__DIR__));

// Carregar conexão (agora o caminho '../' irá funcionar vindo direto da raiz do admin)
echo "DEBUG: Carregando conexao.php...<br>";
require_once 'conexao.php';
echo "DEBUG: conexao.php carregado.<br>";

// Validar login
if (function_exists('verificaLogin')) {
    echo "DEBUG: Verificando login...<br>";
    verificaLogin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conteudo = trim($_POST['conteudo_parecer'] ?? '');
    // Recuperar o req_id vindo do POST
    $requerimento_id = trim($_POST['requerimento_id'] ?? '');

    if (empty($conteudo)) {
        die("ERRO: O conteúdo do parecer não pode estar vazio.");
    }

    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        die("ERRO: Sessão de administrador ausente.");
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
             die("ERRO: Administrador não encontrado no banco.");
        }
    } catch (Exception $e) {
        die("ERRO SQL: " . $e->getMessage());
    }

    // Preparar dados do assinante
    $assinante = [
        'nome' => ($admin['nome_completo'] ?? $admin['nome'] ?? $_SESSION['admin_nome']),
        'cargo' => ($admin['cargo'] ?? 'Administrador(a)'),
        'data_hora' => date('d/m/Y H:i:s')
    ];

    $numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

    // Carregar biblioteca de PDF da pasta assinatura (que agora é './assinatura/' do ponto de vista do admin)
    echo "DEBUG: Chamando gerar_pdf.php de dentro da pasta assinatura/...<br>";
    require_once 'assinatura/gerar_pdf.php';
    
    emitirParecerAssinado($conteudo, $assinante, $numero_processo);
    exit;
} else {
    header("Location: ../index.php");
    exit;
}
