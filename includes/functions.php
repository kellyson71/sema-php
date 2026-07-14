<?php
require_once 'config.php';
require_once 'database.php';

/**
 * Retorna o nome legível de um tipo de alvará a partir do slug.
 * Carrega tipos_alvara.php sob demanda.
 *
 * @param string $slug Slug do tipo (ex: 'construcao', 'habite_se')
 * @return string Nome legível (ex: 'ALVARÁ DE CONSTRUÇÃO, REFORMA E/OU AMPLIAÇÃO')
 */
function nomeAlvara(string $slug): string
{
    static $tipos = null;
    if ($tipos === null) {
        $arquivo = dirname(__DIR__) . '/tipos_alvara.php';
        if (file_exists($arquivo)) {
            include $arquivo;
            $tipos = $tipos_alvara ?? [];
        } else {
            $tipos = [];
        }
    }
    return $tipos[$slug]['nome'] ?? ucwords(str_replace('_', ' ', $slug));
}

/**
 * Gera um número de protocolo único
 * @return string Número de protocolo
 */
function gerarProtocolo()
{
    return date('YmdHis') . rand(100, 999);
}

/**
 * Gera um token estável para acesso à página pública de pagamento.
 *
 * @param int $requerimentoId ID do requerimento
 * @param string $protocolo Protocolo interno do requerimento
 * @return string Token assinado
 */
function gerarTokenPagamento(int $requerimentoId, string $protocolo): string
{
    $assinatura = hash_hmac('sha256', $requerimentoId . '|' . $protocolo, DB_PASS . '|' . SMTP_PASSWORD);
    return $requerimentoId . '.' . substr($assinatura, 0, 32);
}

/**
 * Valida o token público de pagamento.
 *
 * @param string $token Token recebido pela URL
 * @param int $requerimentoId ID do requerimento
 * @param string $protocolo Protocolo interno do requerimento
 * @return bool
 */
function validarTokenPagamento(string $token, int $requerimentoId, string $protocolo): bool
{
    if ($token === '') {
        return false;
    }

    return hash_equals(gerarTokenPagamento($requerimentoId, $protocolo), $token);
}

/**
 * Monta a URL pública da página de pagamento.
 *
 * @param int $requerimentoId ID do requerimento
 * @param string $protocolo Protocolo interno do requerimento
 * @return string
 */
function gerarUrlPagamento(int $requerimentoId, string $protocolo): string
{
    $base = rtrim(BASE_URL, '/');
    if (!preg_match('#^https?://#i', $base)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $base = $protocol . '://' . $host . '/' . ltrim($base, '/');
        $base = rtrim($base, '/');
    }
    return $base . '/pagamento.php?token=' . urlencode(gerarTokenPagamento($requerimentoId, $protocolo));
}

/**
 * Prazo de validade do link de entrega de documentos ao cidadão.
 */
if (!defined('ENTREGA_LINK_VALIDADE_DIAS')) {
    define('ENTREGA_LINK_VALIDADE_DIAS', 180);
}

/**
 * Gera o token de um lote de entrega de documentos ao cidadão.
 *
 * O token é aleatório (não derivado do protocolo): dois envios do mesmo
 * requerimento geram links diferentes, e revogar um não afeta o outro. O ID do
 * requerimento vai no prefixo apenas para a página pública localizar o processo
 * antes de validar o token no banco — ele não autentica nada sozinho.
 */
function gerarTokenDocumentoFinal(int $requerimentoId): string
{
    return $requerimentoId . '.df.' . bin2hex(random_bytes(24));
}

/**
 * Extrai o ID do requerimento embutido no prefixo do token.
 */
function requerimentoIdDoToken(string $token): int
{
    $partes = explode('.', $token, 3);
    return isset($partes[0]) ? (int) $partes[0] : 0;
}

/**
 * Busca o lote de entrega válido para um token: existe, não foi revogado e não
 * expirou. Retorna as linhas do lote (uma por documento) ou [] se inválido.
 *
 * Esta é a única autenticação do link público — não há HMAC a conferir.
 */
function buscarLoteEntregaValido(PDO $pdo, string $token): array
{
    if ($token === '') {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT id, requerimento_id, lote_id, caminho_arquivo, nome_arquivo,
               instrucoes, enviado_em, expira_em, visualizado_em
        FROM documentos_finais
        WHERE token_acesso = ?
          AND revogado_em IS NULL
          AND (expira_em IS NULL OR expira_em > NOW())
        ORDER BY id ASC
    ");
    $stmt->execute([$token]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gerarUrlDocumentoFinal(string $token): string
{
    return urlBasePublica() . '/documento_final.php?token=' . urlencode($token);
}

/**
 * URL absoluta da raiz pública, mesmo quando BASE_URL é relativa.
 */
function urlBasePublica(): string
{
    $base = rtrim(BASE_URL, '/');
    if (!preg_match('#^https?://#i', $base)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $base = rtrim($protocol . '://' . $host . '/' . ltrim($base, '/'), '/');
    }
    return $base;
}

/**
 * URL de download de um arquivo de uploads, servido por arquivo.php.
 *
 * A pasta uploads/ é fechada no .htaccess: nada lá é acessível por URL direta.
 * Sem $token, arquivo.php só entrega o arquivo para admin logado.
 */
function urlArquivo(string $caminhoRelativo, string $token = '', bool $absoluta = false): string
{
    $caminho = ltrim(str_replace('\\', '/', $caminhoRelativo), '/');
    $url = 'arquivo.php?path=' . rawurlencode($caminho);
    if ($token !== '') {
        $url .= '&token=' . urlencode($token);
    }
    return $absoluta ? urlBasePublica() . '/' . $url : $url;
}

/**
 * Salva um arquivo enviado
 * @param array $arquivo Array $_FILES do arquivo
 * @param string $diretorio Diretório onde o arquivo será salvo
 * @param string $prefixo Prefixo para o nome do arquivo
 * @return array|false Informações do arquivo salvo ou false em caso de erro
 */
function salvarArquivo($arquivo, $diretorio, $prefixo = '', $maxSize = null)
{
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $limite = $maxSize ?? MAX_FILE_SIZE;
    if ($arquivo['size'] > $limite) {
        return false;
    }

    // Verifica se é um arquivo PDF
    $nome_original = $arquivo['name'];
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));

    // Valida o MIME type real pelo conteúdo do arquivo (não pelo cabeçalho enviado pelo cliente)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_real = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);

    if ($extensao !== 'pdf' || $mime_real !== 'application/pdf') {
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
    // Quando chamada via fetch (X-Requested-With: fetch), devolve JSON com a URL
    // para que o JS navegue manualmente — evita que o fetch consuma a sessão antes
    // do browser chegar em sucesso.php.
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        header('Content-Type: application/json');
        echo json_encode(['redirect' => $url]);
        exit;
    }
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

function registrarHistoricoAssinatura($pdo, $dados)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO historico_assinaturas (
                documento_id, requerimento_id, admin_id, evento, origem, status,
                email_destino, codigo_hash, codigo_ultimos, ip, user_agent,
                accept_language, host, nome_arquivo, hash_documento, erro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $dados['documento_id'] ?? null,
            $dados['requerimento_id'] ?? null,
            $dados['admin_id'] ?? null,
            $dados['evento'] ?? null,
            $dados['origem'] ?? null,
            $dados['status'] ?? null,
            $dados['email_destino'] ?? null,
            $dados['codigo_hash'] ?? null,
            $dados['codigo_ultimos'] ?? null,
            $dados['ip'] ?? null,
            $dados['user_agent'] ?? null,
            $dados['accept_language'] ?? null,
            $dados['host'] ?? null,
            $dados['nome_arquivo'] ?? null,
            $dados['hash_documento'] ?? null,
            $dados['erro'] ?? null
        ]);
        return true;
    } catch (Exception $e) {
        error_log('Erro ao registrar historico de assinatura: ' . $e->getMessage());
        return false;
    }
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
