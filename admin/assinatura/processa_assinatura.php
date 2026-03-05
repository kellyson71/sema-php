<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug para o usuário ver o progresso
echo "DEBUG: Iniciando processa_assinatura.php...<br>";

// Carregar conexão
echo "DEBUG: Tentando carregar conexao.php...<br>";
require_once '../conexao.php';
echo "DEBUG: conexao.php carregado.<br>";

// Validar login
echo "DEBUG: Verificando login...<br>";
verificaLogin();
echo "DEBUG: Login verificado.<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "DEBUG: Recebido POST.<br>";
    $conteudo = trim($_POST['conteudo_parecer'] ?? '');
    $requerimento_id = trim($_POST['requerimento_id'] ?? '');

    if (empty($conteudo)) {
        die("ERRO: O conteúdo do parecer não pode estar vazio.");
    }

    echo "DEBUG: Buscando admin no banco...<br>";
    $admin_id = $_SESSION['admin_id'];
    
    try {
        // Tentar buscar as colunas desejadas, mas com fallback se falhar
        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            die("ERRO: Administrador não encontrado no banco.");
        }
        
        echo "DEBUG: Admin encontrado. Identificado como: " . ($admin['nome'] ?? 'N/A') . "<br>";
    } catch (Exception $e) {
        die("ERRO SQL: " . $e->getMessage());
    }

    // Preparar dados do assinante com fallbacks seguros
    $assinante = [
        'nome' => ($admin['nome_completo'] ?? $admin['nome'] ?? $_SESSION['admin_nome'] ?? 'Assinante Desconhecido'),
        'cargo' => ($admin['cargo'] ?? 'Administrador(a)'),
        'data_hora' => date('d/m/Y H:i:s')
    ];

    $numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

    echo "DEBUG: Carregando gerar_pdf.php...<br>";
    require_once 'gerar_pdf.php';
    echo "DEBUG: gerar_pdf.php carregado.<br>";
    
    echo "DEBUG: Chamando emitirParecerAssinado...<br>";
    emitirParecerAssinado($conteudo, $assinante, $numero_processo);
    
    // Se chegar aqui, algo deu errado pois o Output do PDF deveria ter encerrado o script
    echo "DEBUG: Fim do script (PDF Output não interrompeu a execução).<br>";
    exit;
} else {
    echo "DEBUG: Não é POST. Redirecionando...<br>";
    header("Location: ../index.php");
    exit;
}
