<?php

declare(strict_types=1);

/**
 * Remoção da estrutura visual de paginação do editor antes do PDF.
 *
 * O editor insere separadores de folha (`.page-gap`, e a variante `<tr>` dentro
 * de tabelas) puramente para dar a aparência de folhas A4 separadas. Nada disso
 * pode chegar ao TCPDF — quem pagina o documento final é exclusivamente o
 * TCPDF, e qualquer resíduo desses blocos vira texto solto no corpo do PDF.
 *
 * A varredura conta abertura/fechamento de tags para achar o fechamento CERTO.
 * Uma regex preguiçosa (`[\s\S]*?</div>`) para no primeiro `</div>` e deixa
 * escapar todo o miolo aninhado do separador — foi exatamente esse o bug que
 * derramava "PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN" dentro do parecer.
 */

/** Classes que representam separadores visuais descartáveis. */
const CLASSES_SEPARADOR_PAGINA = ['page-gap', 'page-cut', 'page-break-indicator'];

/**
 * Remove os resíduos de conteúdo colado do Microsoft Word que o TCPDF não
 * sabe interpretar.
 *
 * Quando alguém cola um parecer redigido no Word direto no editor, o
 * clipboard HTML do Word traz:
 *  - <img src="file:///C:/Users/.../clip_image00X.gif">, um caminho no disco
 *    de quem colou. Some sempre — nunca existe no servidor — mas o TCPDF
 *    ainda reserva o espaço em branco do tamanho declarado (width/height do
 *    próprio <img>), porque a leitura do atributo width/height acontece
 *    antes da tentativa (fracassada) de abrir o arquivo. Resultado: um vão
 *    em branco exatamente do tamanho da imagem que devia estar lá.
 *  - bordas de célula em propriedades separadas (border-width/-style/-color
 *    com 4 valores, um por lado) em vez do shorthand único "border". O
 *    parser de CSS do TCPDF trata mal o valor 'none' nesse formato (não
 *    limpa cor/largura já aplicadas por border-color/border-width, só pula
 *    o traço) — na prática sobra risco solto onde o Word não queria borda
 *    nenhuma. Sem essas declarações, quem desenha a grade é o CSS do
 *    documento (td/th com borda única e consistente).
 *  - propriedades mso-* e a tag <o:p>, que não têm efeito nenhum e só
 *    poluem a árvore.
 */
function limparColagemWord(string $html): string
{
    $marcasWord = ['mso-', 'urn:schemas-microsoft-com', 'msohtmlclip', '<o:p', 'MsoNormal'];
    $temMarcaWord = false;
    foreach ($marcasWord as $marca) {
        if (stripos($html, $marca) !== false) {
            $temMarcaWord = true;
            break;
        }
    }
    if (!$temMarcaWord) {
        // Sinal de que não é conteúdo colado do Word: nada a fazer.
        return $html;
    }

    $dom = new DOMDocument();
    $wrapped = '<?xml encoding="utf-8"?><div id="__wrap_word__">' . $html . '</div>';
    libxml_use_internal_errors(true);
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $wrapEl = $dom->getElementById('__wrap_word__');
    if (!$wrapEl) {
        return $html;
    }

    $xpath = new DOMXPath($dom);

    // <img> sem origem que o TCPDF consiga abrir (file://, blob:, caminho
    // relativo do computador de quem colou) — remove a tag inteira, em vez
    // de deixar o TCPDF reservar um vão em branco do tamanho dela.
    foreach ($xpath->query('.//img', $wrapEl) as $img) {
        $src = (string) $img->getAttribute('src');
        // Caminho do próprio sistema (/assets/...) fica: quem resolve (ou tira,
        // se não existir) é ajustarImagensETabelasParaPdf().
        if (!preg_match('#^(data:image/|https?://|/(?!/))#i', $src)) {
            $img->parentNode->removeChild($img);
        }
    }

    // <o:p> do Word: sem função fora do Word, mantém só o texto de dentro.
    // (busca por local-name(), não pelo prefixo "o:", que o XPath trataria
    // como namespace não registrado e falharia a consulta inteira)
    foreach ($xpath->query('.//*[local-name()="o:p"]', $wrapEl) as $op) {
        while ($op->firstChild) {
            $op->parentNode->insertBefore($op->firstChild, $op);
        }
        $op->parentNode->removeChild($op);
    }

    // Propriedades mso-* e as bordas por lado (border-width/-style/-color)
    // de tabelas/células coladas do Word: o CSS do documento já define a
    // borda da grade. Só mexe em elementos que o próprio Word marcou (style
    // com "mso-" ou algum ancestral com classe "Mso*") — um documento pode
    // ter um trecho colado do Word ao lado de uma tabela criada direto no
    // editor, e essa segunda não pode perder uma borda manual só porque o
    // documento como um todo tem resíduo de Word em outro lugar.
    foreach ($xpath->query('.//*[@style]', $wrapEl) as $el) {
        $estiloProprio = (string) $el->getAttribute('style');
        $ehTrechoWord = stripos($estiloProprio, 'mso-') !== false
            || $xpath->query('ancestor-or-self::*[contains(@class, "Mso")]', $el)->length > 0;

        $declaracoes = array_filter(array_map('trim', explode(';', $estiloProprio)));
        $mantidas = array_filter($declaracoes, static function (string $decl) use ($ehTrechoWord): bool {
            if (!$ehTrechoWord) return true;
            $prop = strtolower(trim(explode(':', $decl, 2)[0] ?? ''));
            if (strpos($prop, 'mso-') === 0) return false;
            if (in_array($prop, ['border', 'border-width', 'border-style', 'border-color',
                'border-top', 'border-right', 'border-bottom', 'border-left'], true)) return false;
            return true;
        });
        if ($mantidas === []) {
            $el->removeAttribute('style');
        } else {
            $el->setAttribute('style', implode('; ', $mantidas));
        }
    }

    $out = '';
    foreach ($wrapEl->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

/**
 * Encaixa imagens e tabelas na largura útil da folha antes do TCPDF.
 *
 * Problemas que isso resolve (relatados em 09/2026, doc do processo 932):
 *  - tabela colada do Word traz largura fixa (width="614", células em pt):
 *    no TCPDF isso passava da margem e o texto da coluna da direita saía
 *    cortado. Tabela com largura fixa passa a 100%, e as colunas viram
 *    porcentagem, na mesma proporção do original;
 *  - imagem com largura em % (o "Redimensionar 50%" do editor grava assim):
 *    o TCPDF não entende % em <img> e desenhava um pontinho. Vira mm;
 *  - imagem sem largura, maior que a folha: é limitada à largura útil;
 *  - imagem por caminho do próprio sistema (/assets/...): o TCPDF não abre
 *    caminho relativo e deixava um vão. Vira caminho de arquivo no servidor;
 *    se o arquivo não existe, a tag sai (em vez do vão em branco);
 *  - aviso de "imagem do Word não colada" que o editor deixa no lugar da
 *    imagem perdida (.img-colagem-pendente): é só para quem está editando.
 */
function ajustarImagensETabelasParaPdf(string $html, float $larguraUtilMm = 180.0, ?string $raizProjeto = null): string
{
    if (stripos($html, '<img') === false && stripos($html, '<table') === false
        && stripos($html, 'img-colagem-pendente') === false) {
        return $html;
    }
    $raizProjeto ??= dirname(__DIR__);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div id="__wrap_pdf__">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $wrap = $dom->getElementById('__wrap_pdf__');
    if (!$wrap) {
        return $html;
    }
    $xpath = new DOMXPath($dom);

    foreach (iterator_to_array($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " img-colagem-pendente ")]', $wrap)) as $aviso) {
        $aviso->parentNode->removeChild($aviso);
    }

    foreach (iterator_to_array($xpath->query('.//img', $wrap)) as $img) {
        /** @var DOMElement $img */
        $src = trim((string) $img->getAttribute('src'));
        $larguraNatural = null;

        if (preg_match('#^data:image/[^;]+;base64,(.+)$#is', $src, $m)) {
            $info = @getimagesizefromstring((string) base64_decode($m[1]));
            $larguraNatural = $info ? (float) $info[0] : null;
        } elseif (!preg_match('#^https?://#i', $src)) {
            $arquivo = resolverImagemLocalParaPdf($src, $raizProjeto);
            if ($arquivo === null) {
                $img->parentNode->removeChild($img);
                continue;
            }
            $img->setAttribute('src', $arquivo);
            $info = @getimagesize($arquivo);
            $larguraNatural = $info ? (float) $info[0] : null;
        }

        $estilo = (string) $img->getAttribute('style');
        $largura = null;
        if (preg_match('/(?:^|;)\s*width\s*:\s*([\d.]+)\s*(%|px|pt|mm)?/i', $estilo, $m)) {
            $largura = [(float) $m[1], strtolower($m[2] ?? 'px')];
        } elseif (preg_match('/^\s*([\d.]+)\s*(%|px)?\s*$/', (string) $img->getAttribute('width'), $m)) {
            $largura = [(float) $m[1], strtolower($m[2] ?? 'px')];
        }

        $mm = match (true) {
            $largura === null => $larguraNatural !== null ? $larguraNatural * 25.4 / 96 : null,
            $largura[1] === '%' => $larguraUtilMm * $largura[0] / 100,
            $largura[1] === 'pt' => $largura[0] * 25.4 / 72,
            $largura[1] === 'mm' => $largura[0],
            default => $largura[0] * 25.4 / 96,
        };
        if ($mm === null) {
            continue;
        }
        $mm = min($mm, $larguraUtilMm);
        // Só a largura: o TCPDF calcula a altura mantendo a proporção.
        $img->setAttribute('width', round($mm, 2) . 'mm');
        $img->removeAttribute('height');
        $estiloSemTamanho = preg_replace('/(?:^|;)\s*(?:max-|min-)?(?:width|height)\s*:[^;]*/i', '', $estilo);
        if (trim((string) $estiloSemTamanho, " ;") === '') {
            $img->removeAttribute('style');
        } else {
            $img->setAttribute('style', trim((string) $estiloSemTamanho, " ;"));
        }
    }

    foreach (iterator_to_array($xpath->query('.//table', $wrap)) as $tabela) {
        /** @var DOMElement $tabela */
        // Largura fixa (px/pt/cm) em tabela ou célula: o TCPDF lê px como 1/72 pol,
        // então até uma tabela "menor" que a folha no navegador estoura a margem
        // no PDF (width="614" ≈ 216 mm). Tabela com largura fixa vira 100%.
        $temLarguraFixa = larguraCssEmPx((string) $tabela->getAttribute('width'), (string) $tabela->getAttribute('style')) > 0;
        if (!$temLarguraFixa) {
            foreach ($xpath->query('./tr/td|./tr/th|./*/tr/td|./*/tr/th', $tabela) as $c) {
                if (larguraCssEmPx((string) $c->getAttribute('width'), (string) $c->getAttribute('style')) > 0) {
                    $temLarguraFixa = true;
                    break;
                }
            }
        }
        if (!$temLarguraFixa) {
            continue;
        }
        $tabela->setAttribute('width', '100%');
        $tabela->setAttribute('style', trim((string) preg_replace('/(?:^|;)\s*width\s*:[^;]*/i', '', (string) $tabela->getAttribute('style')), " ;"));
        if ($tabela->getAttribute('style') === '') {
            $tabela->removeAttribute('style');
        }

        // Colunas proporcionais, a partir da linha com mais células.
        $linhas = iterator_to_array($xpath->query('./tr|./thead/tr|./tbody/tr|./tfoot/tr', $tabela));
        foreach ($linhas as $tr) {
            $celulas = iterator_to_array($xpath->query('./td|./th', $tr));
            $larguras = array_map(
                static fn(DOMElement $c): float => larguraCssEmPx((string) $c->getAttribute('width'), (string) $c->getAttribute('style')),
                $celulas
            );
            $total = array_sum($larguras);
            foreach ($celulas as $i => $celula) {
                $celula->setAttribute('style', trim((string) preg_replace('/(?:^|;)\s*width\s*:[^;]*/i', '', (string) $celula->getAttribute('style')), " ;"));
                if ($total > 0 && $larguras[$i] > 0) {
                    $celula->setAttribute('width', round($larguras[$i] / $total * 100, 2) . '%');
                } else {
                    $celula->removeAttribute('width');
                }
                if ($celula->getAttribute('style') === '') {
                    $celula->removeAttribute('style');
                }
            }
        }
    }

    $out = '';
    foreach ($wrap->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

/** Largura declarada (atributo width ou CSS width) em px; 0 quando não há ou é %. */
function larguraCssEmPx(string $atributo, string $estilo): float
{
    if (preg_match('/(?:^|;)\s*width\s*:\s*([\d.]+)\s*(px|pt|cm|mm|in)?/i', $estilo, $m)) {
        [$valor, $unidade] = [(float) $m[1], strtolower($m[2] ?? 'px')];
    } elseif (preg_match('/^\s*([\d.]+)\s*(px)?\s*$/i', $atributo, $m)) {
        [$valor, $unidade] = [(float) $m[1], 'px'];
    } else {
        return 0.0;
    }
    return match ($unidade) {
        'pt' => $valor * 96 / 72,
        'cm' => $valor * 96 / 2.54,
        'mm' => $valor * 96 / 25.4,
        'in' => $valor * 96,
        default => $valor,
    };
}

/**
 * Caminho de imagem do próprio sistema (/assets/img/x.png, assets/..., ou URL
 * do mesmo host já cortada) → arquivo no servidor. Só aceita arquivo dentro do
 * projeto; qualquer outra coisa (file://, blob:, C:\...) volta null.
 */
function resolverImagemLocalParaPdf(string $src, string $raizProjeto): ?string
{
    if ($src === '' || preg_match('#^(?:file|blob|about|javascript):#i', $src) || preg_match('#^[a-z]:[\\\\/]#i', $src)) {
        return null;
    }
    $caminho = rawurldecode((string) parse_url($src, PHP_URL_PATH));
    $raiz = realpath($raizProjeto);
    if ($caminho === '' || $raiz === false) {
        return null;
    }
    // Instalação em subpasta (/sema-php/assets/...): tenta cortando prefixos.
    $partes = array_values(array_filter(explode('/', $caminho), 'strlen'));
    for ($i = 0; $i < count($partes); $i++) {
        $candidato = realpath($raiz . '/' . implode('/', array_slice($partes, $i)));
        if ($candidato !== false && is_file($candidato) && str_starts_with($candidato, $raiz . DIRECTORY_SEPARATOR)
            && preg_match('/\.(png|jpe?g|gif)$/i', $candidato)) {
            return $candidato;
        }
    }
    return null;
}

/** Classes de invólucros de folha: somem, mas o conteúdo dentro é preservado. */
const CLASSES_ENVOLTORIO_PAGINA = ['doc-page-content'];

/**
 * Devolve o HTML do editor sem nenhum vestígio da paginação visual.
 */
function removerEstruturaPaginacaoHtml(string $html): string
{
    // Separadores de folha, na versão bloco e na versão linha de tabela
    $html = processarTagsPorClasse($html, 'div', CLASSES_SEPARADOR_PAGINA, true);
    $html = processarTagsPorClasse($html, 'tr', CLASSES_SEPARADOR_PAGINA, true);
    // Invólucros de folha de versões anteriores do editor: desembrulha
    $html = processarTagsPorClasse($html, 'div', CLASSES_ENVOLTORIO_PAGINA, false);

    return $html;
}

/**
 * Remove (ou desembrulha) toda tag `$tag` que carregue uma das `$classes`.
 *
 * @param bool $remover true = apaga a tag e todo o conteúdo dela;
 *                      false = apaga só as tags de abertura/fechamento.
 */
function processarTagsPorClasse(string $html, string $tag, array $classes, bool $remover): string
{
    if ($classes === []) {
        return $html;
    }

    $padraoClasse = '(?:' . implode('|', array_map(
        static fn(string $c): string => preg_quote($c, '/'),
        $classes
    )) . ')';
    $padraoAbertura = '/<' . preg_quote($tag, '/') . '\b[^>]*\bclass\s*=\s*("|\')([^"\']*)\1[^>]*>/i';

    $offset = 0;
    while ($offset < strlen($html)
        && preg_match($padraoAbertura, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {

        $aberturaInicio = $m[0][1];
        $aberturaFim    = $aberturaInicio + strlen($m[0][0]);

        if (!preg_match('/(?:^|\s)' . $padraoClasse . '(?:\s|$)/', $m[2][0])) {
            $offset = $aberturaFim;
            continue;
        }

        $fechamento = localizarFechamentoDaTag($html, $tag, $aberturaFim);

        if ($fechamento === null) {
            // HTML truncado: sem par de fechamento, some só com a abertura para
            // não engolir o resto do documento por engano.
            $html = substr($html, 0, $aberturaInicio) . substr($html, $aberturaFim);
            $offset = $aberturaInicio;
            continue;
        }

        if ($remover) {
            $html = substr($html, 0, $aberturaInicio) . substr($html, $fechamento['fim']);
        } else {
            // Corta o fechamento primeiro: ele está depois da abertura, então os
            // índices da abertura continuam válidos.
            $html = substr($html, 0, $fechamento['inicio']) . substr($html, $fechamento['fim']);
            $html = substr($html, 0, $aberturaInicio) . substr($html, $aberturaFim);
        }

        $offset = $aberturaInicio;
    }

    return $html;
}

/**
 * Acha o fechamento que realmente pertence à abertura, contando aninhamento.
 *
 * @return array{inicio:int,fim:int}|null
 */
function localizarFechamentoDaTag(string $html, string $tag, int $offset): ?array
{
    $padrao = '/<\s*(\/?)' . preg_quote($tag, '/') . '\b[^>]*>/i';
    $profundidade = 1;
    $limite = strlen($html);

    while ($offset < $limite && preg_match($padrao, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $inicio = $m[0][1];
        $fim    = $inicio + strlen($m[0][0]);

        if ($m[1][0] === '/') {
            if (--$profundidade === 0) {
                return ['inicio' => $inicio, 'fim' => $fim];
            }
        } else {
            $profundidade++;
        }

        $offset = $fim;
    }

    return null;
}
