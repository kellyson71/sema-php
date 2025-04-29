<?php
require_once 'config.php';
require_once 'database.php';

/**
 * Gera um número de protocolo único
 * @return string Número de protocolo
 */
function gerarProtocolo()
{
    return date('YmdHis') . rand(100, 999);
}

/**
 * Salva um arquivo enviado
 * @param array $arquivo Array $_FILES do arquivo
 * @param string $diretorio Diretório onde o arquivo será salvo
 * @param string $prefixo Prefixo para o nome do arquivo
 * @return array|false Informações do arquivo salvo ou false em caso de erro
 */
function salvarArquivo($arquivo, $diretorio, $prefixo = '')
{
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Verifica o tamanho do arquivo (10MB)
    if ($arquivo['size'] > MAX_FILE_SIZE) {
        return false;
    }

    // Cria o diretório se não existir
    if (!file_exists($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    $nome_original = $arquivo['name'];
    $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
    $novo_nome = $prefixo . '_' . uniqid() . '.' . $extensao;
    $caminho_arquivo = $diretorio . '/' . $novo_nome;

    // Move o arquivo enviado para o diretório de uploads
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_arquivo)) {
        return [
            'nome_original' => $nome_original,
            'caminho' => $caminho_arquivo,
            'tamanho' => $arquivo['size'],
            'tipo' => $arquivo['type'],
            'nome_salvo' => $novo_nome
        ];
    }

    return false;
}

/**
 * Exibe uma mensagem flash
 * @param string $tipo Tipo da mensagem (sucesso, erro, info, alerta)
 * @param string $mensagem Texto da mensagem
 * @return void
 */
function setMensagem($tipo, $mensagem)
{
    $_SESSION['mensagem'] = [
        'tipo' => $tipo,
        'texto' => $mensagem
    ];
}

/**
 * Obtém a mensagem flash atual e a remove da sessão
 * @return array|null Mensagem ou null se não existir
 */
function getMensagem()
{
    if (isset($_SESSION['mensagem'])) {
        $mensagem = $_SESSION['mensagem'];
        unset($_SESSION['mensagem']);
        return $mensagem;
    }
    return null;
}

/**
 * Redirecionamento
 * @param string $url URL para redirecionamento
 * @return void
 */
function redirect($url)
{
    header("Location: {$url}");
    exit;
}

/**
 * Saneia uma string para evitar XSS
 * @param string $string String a ser saneada
 * @return string String saneada
 */
function sanitize($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Obtém o status formatado de um requerimento
 * @param string $status Status do requerimento
 * @return string HTML formatado com o status
 */
function formatarStatus($status)
{
    $class = '';
    switch ($status) {
        case 'Em análise':
            $class = 'status-analise';
            break;
        case 'Aprovado':
            $class = 'status-aprovado';
            break;
        case 'Reprovado':
            $class = 'status-reprovado';
            break;
        case 'Pendente':
            $class = 'status-pendente';
            break;
        default:
            $class = 'status-outro';
    }

    return '<span class="status ' . $class . '">' . $status . '</span>';
}

/**
 * Formata a data no padrão brasileiro
 * @param string $data Data no formato Y-m-d H:i:s
 * @return string Data formatada
 */
function formatarData($data)
{
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Formata a data e hora no padrão brasileiro mais detalhado
 * @param string $data Data no formato Y-m-d H:i:s
 * @return string Data e hora formatadas
 */
function formatarDataHora($data)
{
    $timestamp = strtotime($data);
    return date('d/m/Y \à\s H:i:s', $timestamp);
}

/**
 * Valida se o usuário está logado como admin
 * @return bool
 */
function isAdmin()
{
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

/**
 * Formata o tamanho do arquivo em KB, MB
 * @param int $bytes Tamanho em bytes
 * @return string Tamanho formatado
 */
function formatarTamanho($bytes)
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . ' MB';
    }

    return round($bytes / 1073741824, 2) . ' GB';
}
