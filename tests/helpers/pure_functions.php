<?php

/**
 * Funções puras extraídas de includes/functions.php para uso nos testes unitários.
 * Essas funções não dependem de banco de dados ou sessão.
 */

function gerarProtocolo(): string
{
    return date('YmdHis') . rand(100, 999);
}

function gerarTokenPagamento(int $requerimentoId, string $protocolo): string
{
    $assinatura = hash_hmac('sha256', $requerimentoId . '|' . $protocolo, DB_PASS . '|' . SMTP_PASSWORD);
    return $requerimentoId . '.' . substr($assinatura, 0, 32);
}

function validarTokenPagamento(string $token, int $requerimentoId, string $protocolo): bool
{
    if ($token === '') {
        return false;
    }

    return hash_equals(gerarTokenPagamento($requerimentoId, $protocolo), $token);
}

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

function sanitize(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function formatarStatus(string $status): string
{
    switch (strtolower($status)) {
        case 'analise':
        case 'em análise':
        case 'em analise':
            return 'Em Análise';
        case 'aprovado':
            return 'Aprovado';
        case 'rejeitado':
        case 'reprovado':
            return 'Rejeitado';
        case 'pendente':
            return 'Pendente';
        default:
            return $status;
    }
}

function formatarData(string $data): string
{
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}

function formatarDataHora(string $data): string
{
    if (empty($data)) {
        return 'Data não informada';
    }
    $timestamp = strtotime($data);
    return date('d/m/Y \à\s H:i:s', $timestamp);
}

function formatarCpfCnpj(string $cpf_cnpj): string
{
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $cpf_cnpj);

    if (empty($cpf_cnpj)) {
        return 'Não informado';
    }

    if (strlen($cpf_cnpj) <= 11) {
        $cpf_cnpj = str_pad($cpf_cnpj, 11, '0', STR_PAD_LEFT);
        return substr($cpf_cnpj, 0, 3) . '.' .
            substr($cpf_cnpj, 3, 3) . '.' .
            substr($cpf_cnpj, 6, 3) . '-' .
            substr($cpf_cnpj, 9, 2);
    }

    $cpf_cnpj = str_pad($cpf_cnpj, 14, '0', STR_PAD_LEFT);
    return substr($cpf_cnpj, 0, 2) . '.' .
        substr($cpf_cnpj, 2, 3) . '.' .
        substr($cpf_cnpj, 5, 3) . '/' .
        substr($cpf_cnpj, 8, 4) . '-' .
        substr($cpf_cnpj, 12, 2);
}

function formatarTamanho(int $bytes): string
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

function formatarTamanhoArquivo(int $tamanho): string
{
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($tamanho >= 1024 && $i < count($unidades) - 1) {
        $tamanho /= 1024;
        $i++;
    }
    return round($tamanho, 2) . ' ' . $unidades[$i];
}

function formatarNomeMes(int $mes): string
{
    $meses = [
        1  => 'Janeiro',
        2  => 'Fevereiro',
        3  => 'Março',
        4  => 'Abril',
        5  => 'Maio',
        6  => 'Junho',
        7  => 'Julho',
        8  => 'Agosto',
        9  => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];
    return $meses[$mes] ?? '';
}

function nomeAlvara(string $slug): string
{
    static $tipos = null;
    if ($tipos === null) {
        $arquivo = dirname(__DIR__, 2) . '/tipos_alvara.php';
        if (file_exists($arquivo)) {
            include $arquivo;
            $tipos = $tipos_alvara ?? [];
        } else {
            $tipos = [];
        }
    }
    return $tipos[$slug]['nome'] ?? ucwords(str_replace('_', ' ', $slug));
}

function setMensagem(string $tipo, string $mensagem): void
{
    $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $mensagem];
}

function getMensagem(): ?array
{
    if (isset($_SESSION['mensagem'])) {
        $msg = $_SESSION['mensagem'];
        unset($_SESSION['mensagem']);
        return $msg;
    }
    return null;
}

function isAdmin(): bool
{
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}
