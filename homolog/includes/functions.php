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

    // Verifica se é um arquivo PDF
    $nome_original = $arquivo['name'];
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $tipo = $arquivo['type'];

    if ($extensao !== 'pdf' || $tipo !== 'application/pdf') {
        return false;
    }

    // Cria o diretório se não existir
    if (!file_exists($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    $novo_nome = $prefixo . '_' . uniqid() . '.' . $extensao;

    // Garantir que o diretório não termine com barra
    $diretorio = rtrim($diretorio, '/\\');

    // Caminho do sistema de arquivos para salvar o arquivo
    $caminho_arquivo = $diretorio . '/' . $novo_nome;

    // Caminho relativo para o banco de dados (URL)
    // Extrair apenas a parte relativa do caminho
    $uploads_base = realpath(dirname(__DIR__) . '/uploads');
    $diretorio_real = realpath($diretorio);

    if ($diretorio_real && $uploads_base) {
        $caminho_relativo = str_replace($uploads_base, '', $diretorio_real) . '/' . $novo_nome;
        $caminho_relativo = str_replace('\\', '/', $caminho_relativo); // Converter barras do Windows
        $caminho_relativo = ltrim($caminho_relativo, '/'); // Remover barra inicial
    } else {
        // Fallback caso realpath falhe
        $caminho_relativo = basename($diretorio) . '/' . $novo_nome;
    }

    // Move o arquivo enviado para o diretório de uploads
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_arquivo)) {
        return [
            'nome_original' => $nome_original,
            'caminho' => $caminho_relativo, // Caminho relativo para o banco de dados
            'caminho_completo' => $caminho_arquivo, // Caminho completo no sistema de arquivos
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
 * @return string Status formatado
 */
function formatarStatus($status)
{
    $statusText = $status;

    // Normalizar o status para garantir consistência
    switch (strtolower($status)) {
        case 'analise':
        case 'em análise':
        case 'em analise':
            $statusText = 'Em Análise';
            break;
        case 'aprovado':
            $statusText = 'Aprovado';
            break;
        case 'rejeitado':
        case 'reprovado':
            $statusText = 'Rejeitado';
            break;
        case 'pendente':
            $statusText = 'Pendente';
            break;
    }

    // Não retorna HTML, apenas o texto formatado
    return $statusText;
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
    if (empty($data)) {
        return 'Data não informada';
    }

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

/**
 * Formata o tamanho de um arquivo em bytes para uma representação legível
 * @param int $tamanho Tamanho do arquivo em bytes
 * @return string Tamanho formatado (KB, MB, GB)
 */
function formatarTamanhoArquivo($tamanho)
{
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;

    while ($tamanho >= 1024 && $i < count($unidades) - 1) {
        $tamanho /= 1024;
        $i++;
    }

    return round($tamanho, 2) . ' ' . $unidades[$i];
}

/**
 * Retorna o nome do mês em português
 * @param int $mes Número do mês (1-12)
 * @return string Nome do mês
 */
function formatarNomeMes($mes)
{
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];

    return $meses[$mes] ?? '';
}

/**
 * Formata CPF ou CNPJ com máscara
 * @param string $cpf_cnpj CPF ou CNPJ a ser formatado
 * @return string CPF ou CNPJ formatado
 */
function formatarCpfCnpj($cpf_cnpj)
{
    // Remove caracteres não numéricos
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $cpf_cnpj);

    // Se não tiver nada, retorna vazio
    if (empty($cpf_cnpj)) {
        return 'Não informado';
    }

    // Formata como CPF (###.###.###-##)
    if (strlen($cpf_cnpj) <= 11) {
        // Completa com zeros à esquerda se necessário
        $cpf_cnpj = str_pad($cpf_cnpj, 11, '0', STR_PAD_LEFT);
        return substr($cpf_cnpj, 0, 3) . '.' .
            substr($cpf_cnpj, 3, 3) . '.' .
            substr($cpf_cnpj, 6, 3) . '-' .
            substr($cpf_cnpj, 9, 2);
    }
    // Formata como CNPJ (##.###.###/####-##)
    else {
        // Completa com zeros à esquerda se necessário
        $cpf_cnpj = str_pad($cpf_cnpj, 14, '0', STR_PAD_LEFT);
        return substr($cpf_cnpj, 0, 2) . '.' .
            substr($cpf_cnpj, 2, 3) . '.' .
            substr($cpf_cnpj, 5, 3) . '/' .
            substr($cpf_cnpj, 8, 4) . '-' .
            substr($cpf_cnpj, 12, 2);
    }
}
