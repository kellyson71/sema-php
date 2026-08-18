<?php

/**
 * Funções puras da entrega de documentos ao cidadão.
 *
 * Este arquivo NÃO pode ter require de config.php/database.php: é justamente por
 * isso que ele existe separado — assim os testes unitários o carregam direto, sem
 * precisar de banco, em vez de manter uma cópia paralela das funções.
 * Ver tests/helpers/pure_functions.php.
 */

/**
 * Converte o corpo HTML de um e-mail em texto puro, para o AltBody do PHPMailer.
 *
 * Preserva os links (o cidadão precisa deles) e descarta <style>/<script>, que
 * de outra forma virariam um bloco de CSS no meio da mensagem.
 */
function textoSimplesDoEmail(string $html): string
{
    $texto = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

    // Cada link vira "rótulo: url" para continuar clicável em cliente sem HTML
    $texto = preg_replace_callback(
        '#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
        function (array $m): string {
            $rotulo = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
            return $rotulo !== '' ? $rotulo . ': ' . $m[1] : $m[1];
        },
        $texto
    ) ?? $texto;

    $texto = preg_replace('#<(br|/p|/div|/tr|/h[1-6])\b[^>]*>#i', "\n", $texto) ?? $texto;
    $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES, 'UTF-8');
    $texto = preg_replace('/[ \t]+/', ' ', $texto) ?? $texto;
    $texto = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $texto) ?? $texto;

    return trim($texto);
}

/**
 * Rótulo legível para um documento a partir do nome do arquivo.
 *
 * Os arquivos gerados carregam carimbo de data e hash no nome
 * ("PARECER_TECNICO_20260714_a7f2c9.pdf"), que não diz nada ao cidadão. Isto
 * devolve "Parecer Técnico". Se não sobrar nada reconhecível, devolve string
 * vazia — quem chama decide o rótulo de reserva.
 */
function rotuloDocumento(string $nomeArquivo): string
{
    $base = pathinfo($nomeArquivo, PATHINFO_FILENAME);
    $base = str_replace(['_', '-', '.'], ' ', $base);

    // Descarta os pedaços que são só ruído de nome de arquivo: datas, horas,
    // números soltos e hashes hexadecimais.
    $palavras = array_filter(
        preg_split('/\s+/', $base) ?: [],
        static function (string $p): bool {
            if ($p === '') return false;
            if (preg_match('/^\d+$/', $p)) return false;              // 20260714, 2
            if (preg_match('/^[0-9a-f]{6,}$/i', $p)) return false;    // a7f2c9de
            return true;
        }
    );

    if (empty($palavras)) {
        return '';
    }

    return mb_convert_case(implode(' ', $palavras), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Título em português a partir de um texto todo em caixa alta.
 *
 * Os tipos em tipos_alvara.php são gritados ("ALVARÁ DE CONSTRUÇÃO, REFORMA E/OU
 * AMPLIAÇÃO"). MB_CASE_TITLE sozinho produz "Alvará De Construção, Reforma E/Ou
 * Ampliação" — preposições e conjunções ficam maiúsculas. Aqui elas voltam para
 * minúscula, exceto quando abrem a frase.
 */
function tituloAmigavel(string $texto): string
{
    static $minusculas = [
        'de', 'da', 'do', 'das', 'dos', 'e', 'ou', 'em', 'no', 'na', 'nos', 'nas',
        'a', 'o', 'as', 'os', 'ao', 'aos', 'à', 'às', 'para', 'com', 'por', 'sem',
    ];

    $palavras = preg_split('/\s+/', trim($texto)) ?: [];

    foreach ($palavras as $i => $palavra) {
        // "E/OU" precisa ser tratado pedaço a pedaço, senão vira "E/Ou"
        $partes = explode('/', $palavra);
        foreach ($partes as $j => $parte) {
            $limpa = mb_strtolower(trim($parte, ",.;:—-"), 'UTF-8');
            $ehPrimeira = ($i === 0 && $j === 0);

            $partes[$j] = (!$ehPrimeira && in_array($limpa, $minusculas, true))
                ? mb_strtolower($parte, 'UTF-8')
                : mb_convert_case(mb_strtolower($parte, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }
        $palavras[$i] = implode('/', $partes);
    }

    return implode(' ', $palavras);
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
 * Resolve o caminho físico de um documento a partir do `caminho_arquivo`
 * gravado em `assinaturas_digitais`.
 *
 * O valor gravado não é uniforme: há registros absolutos
 * (/home/u49.../pareceres/...), relativos à raiz do projeto e relativos à pasta
 * admin/ (ex: 'pareceres/905/arquivo.pdf') — este último é o formato que o
 * fluxo de assinatura grava, porque salva o PDF em admin/pareceres/. Assumir um
 * único prefixo faz o arquivo "sumir" mesmo existindo em disco, que é o que
 * impedia a entrega de qualquer documento ao cidadão.
 *
 * @return string|null Caminho físico existente, ou null se não encontrar.
 */
function resolverCaminhoDocumento(?string $caminhoArquivo): ?string
{
    $caminho = trim((string) $caminhoArquivo);
    if ($caminho === '') {
        return null;
    }

    $raiz     = dirname(__DIR__);
    $relativo = ltrim(str_replace('\\', '/', $caminho), '/');

    $candidatos = [
        $caminho,                          // absoluto
        $raiz . '/admin/' . $relativo,     // relativo a admin/ (fluxo de assinatura)
        $raiz . '/' . $relativo,           // relativo à raiz do projeto
    ];
    if (defined('UPLOAD_DIR')) {
        $candidatos[] = rtrim(UPLOAD_DIR, '/\\') . '/' . $relativo; // dentro de uploads/
    }

    foreach ($candidatos as $candidato) {
        if ($candidato !== '' && is_file($candidato)) {
            return $candidato;
        }
    }

    return null;
}
