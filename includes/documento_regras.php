<?php

final class DocumentoRegras
{
    private const TEMPLATES_NUMERADOS = [
        'alvara_de_construcao',
        'alvara_de_desmembramento',
        'carta_habite_se',
    ];

    /** Tipos de alvará do licenciamento ambiental (LAU, parecer de pendências e placa). */
    private const TIPOS_AMBIENTAIS = [
        'licenca_ambiental_unica',
        'licenca_previa_ambiental',
        'licenca_instalacao_operacao',
        'licenca_operacao',
        'licenca_ampliacao',
        'licenca_operacional_corretiva',
        'lac',
    ];

    /** Modelos que já saem com os blocos do Secretário/Eng. Ambiental/Fiscal e pedem a coassinatura deles. */
    private const TEMPLATES_ASSINANTES_FIXOS = [
        'licenca_ambiental_unica',
        'parecer_tecnico_pendencias_ambiental',
    ];

    public const TEMPLATE_PARECER_PENDENCIAS = 'parecer_tecnico_pendencias_ambiental';

    public static function templateNumerado(string $template): bool
    {
        return in_array($template, self::TEMPLATES_NUMERADOS, true);
    }

    /** Nome da licença para usar no meio do texto e na placa ("Licença Ambiental Única (LAU)"). */
    public static function nomeLicenca(string $tipoAlvara, string $nomeCadastro = ''): string
    {
        $nomes = [
            'licenca_ambiental_unica' => 'Licença Ambiental Única (LAU)',
            'licenca_previa_ambiental' => 'Licença Prévia (LP)',
            'licenca_instalacao_operacao' => 'Licença de Instalação e Operação (LIO)',
            'licenca_operacao' => 'Licença de Operação (LO)',
            'licenca_ampliacao' => 'Licença de Ampliação',
            'licenca_operacional_corretiva' => 'Licença de Operação Corretiva (LOC)',
            'lac' => 'Licença Ambiental por Adesão e Compromisso (LAC)',
        ];
        return $nomes[$tipoAlvara] ?? ($nomeCadastro !== '' ? $nomeCadastro : ucwords(str_replace('_', ' ', $tipoAlvara)));
    }

    public static function tiposAmbientais(): array
    {
        return self::TIPOS_AMBIENTAIS;
    }

    public static function tipoAmbiental(string $tipoAlvara): bool
    {
        return in_array($tipoAlvara, self::TIPOS_AMBIENTAIS, true);
    }

    public static function templateComAssinantesFixos(string $template): bool
    {
        return in_array($template, self::TEMPLATES_ASSINANTES_FIXOS, true);
    }

    /** Validade da licença ambiental: recebimento do processo + 5 anos (regra fixa, reunião de 25/09/2026). */
    public static function validadeLicenca(?string $dataRecebimento): string
    {
        $data = self::interpretarData($dataRecebimento);
        return $data ? $data->modify('+5 years')->format('d/m/Y') : '';
    }

    public static function interpretarData(?string $valor): ?DateTimeImmutable
    {
        $valor = trim((string) $valor);
        if ($valor === '' || str_starts_with($valor, '0000-00-00')) {
            return null;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', substr($valor, 0, 10))
            ?: DateTimeImmutable::createFromFormat('!d/m/Y', $valor);
        return $data ?: null;
    }

    /**
     * O 2º parecer de pendências emitido no mesmo processo vira "Parecer Técnico Final".
     * Conta só os já assinados/emitidos e ainda vigentes — rascunho não conta, e a
     * retificação reabre o HTML original, então o 1º continua com o título dele.
     */
    public static function tituloParecerPendencias(?PDO $pdo, int $requerimentoId): string
    {
        return self::pareceresPendenciasEmitidos($pdo, $requerimentoId) >= 1
            ? 'PARECER TÉCNICO FINAL'
            : 'PARECER TÉCNICO – PENDÊNCIAS';
    }

    public static function pareceresPendenciasEmitidos(?PDO $pdo, int $requerimentoId): int
    {
        if (!$pdo || $requerimentoId <= 0) {
            return 0;
        }
        try {
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT documento_id) FROM assinaturas_digitais
                WHERE requerimento_id = ? AND tipo_documento = ? AND substituido_por_documento_id IS NULL');
            $stmt->execute([$requerimentoId, self::TEMPLATE_PARECER_PENDENCIAS]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Nome legível de cada documento anexado, a partir do campo do formulário
     * (doc_{tipo}_{índice} → item {índice} da lista do tipo em tipos_alvara.php).
     */
    public static function nomesDocumentosAnexados(array $camposFormulario, array $listaDocumentosTipo): array
    {
        $nomes = [];
        foreach ($camposFormulario as $campo) {
            if (!preg_match('/_(\d+)$/', (string) $campo, $m) || !isset($listaDocumentosTipo[(int) $m[1]])) {
                continue;
            }
            $nome = preg_replace('/^\s*\d+\.\s*/', '', (string) $listaDocumentosTipo[(int) $m[1]]);
            $nome = rtrim(trim((string) $nome), ';.');
            $nomes[(int) $m[1]] = $nome;
        }
        ksort($nomes);
        return array_values($nomes);
    }

    /** Tabela "Documento | Análise Técnica" do parecer de pendências, com uma linha por documento. */
    public static function tabelaAnaliseDocumentosHtml(array $nomesDocumentos): string
    {
        if (!$nomesDocumentos) {
            $nomesDocumentos = ['Requerimento'];
        }
        $td = 'border:1px solid #000; padding:4px 6px; vertical-align:top;';
        $html = '<table width="100%" border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse; font-size:11pt;">'
            . '<tr><td width="40%" style="' . $td . '"><strong>Documento</strong></td>'
            . '<td width="60%" style="' . $td . '"><strong>Análise Técnica</strong></td></tr>';
        foreach ($nomesDocumentos as $nome) {
            $html .= '<tr><td width="40%" style="' . $td . '">' . htmlspecialchars((string) $nome, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td width="60%" style="' . $td . '">Documento Aceito / Aceito parcialmente / Pendente</td></tr>';
        }
        return $html . '</table>';
    }

    public static function proximoNumero(PDO $pdo, string $template, ?int $ano = null): string
    {
        $ano ??= (int) date('Y');
        if (!self::templateNumerado($template)) {
            return '1/' . $ano;
        }

        try {
            $stmt = $pdo->prepare('SELECT ultimo_numero FROM document_number_sequences
                WHERE template_key = ? AND ano = ?');
            $stmt->execute([$template, $ano]);
            $ultimo = (int) ($stmt->fetchColumn() ?: 0);

            $stmtUsados = $pdo->prepare('SELECT COALESCE(MAX(numero), 0) FROM document_numbers
                WHERE template_key = ? AND ano = ?');
            $stmtUsados->execute([$template, $ano]);
            $ultimo = max($ultimo, (int) $stmtUsados->fetchColumn());
            return ($ultimo + 1) . '/' . $ano;
        } catch (Throwable $e) {
            // Compatibilidade enquanto a migration ainda não foi aplicada.
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT documento_id) FROM assinaturas_digitais
                WHERE tipo_documento = ? AND YEAR(timestamp_assinatura) = ?');
            $stmt->execute([$template, $ano]);
            return ((int) $stmt->fetchColumn() + 1) . '/' . $ano;
        }
    }

    public static function interpretarNumero(string $valor, ?int $anoPadrao = null): ?array
    {
        $anoPadrao ??= (int) date('Y');
        $valor = trim($valor);
        if (preg_match('/^(\d+)\s*\/\s*(\d{4})$/', $valor, $m)) {
            return ['numero' => (int) $m[1], 'ano' => (int) $m[2]];
        }
        if (preg_match('/^\d+$/', $valor)) {
            return ['numero' => (int) $valor, 'ano' => $anoPadrao];
        }
        return null;
    }

    public static function formatarEndereco(array $r): string
    {
        $logradouro = trim((string) ($r['obra_logradouro'] ?? ''));
        $bairro = trim((string) ($r['obra_bairro'] ?? ''));
        if ($logradouro === '' || $bairro === '') {
            return trim((string) ($r['endereco_objetivo'] ?? ''));
        }

        $partes = [mb_strtoupper($logradouro, 'UTF-8')];
        $semLote = !empty($r['obra_sem_lote_quadra']);
        $lote = trim((string) ($r['obra_lote'] ?? ''));
        $quadra = trim((string) ($r['obra_quadra'] ?? ''));
        if (!$semLote && $lote !== '') {
            $bloco = 'LOTE ' . mb_strtoupper($lote, 'UTF-8');
            if ($quadra !== '') $bloco .= ', QUADRA ' . mb_strtoupper($quadra, 'UTF-8');
            $partes[] = '(' . $bloco . ')';
        }

        $numero = !empty($r['obra_sem_numero']) ? 'SN' : trim((string) ($r['obra_numero'] ?? ''));
        $partes[] = $numero !== '' ? mb_strtoupper($numero, 'UTF-8') : 'SN';
        $partes[] = 'BAIRRO ' . mb_strtoupper($bairro, 'UTF-8');
        $partes[] = 'PAU DOS FERROS/RN.';
        return implode(', ', $partes);
    }

    public static function conselhoResponsavel(array $r): string
    {
        $valor = mb_strtoupper(trim((string) ($r['responsavel_tecnico_tipo_documento'] ?? '')), 'UTF-8');
        if (in_array($valor, ['CAU', 'RRT'], true)) return 'CAU';
        if ($valor === 'CTF') return 'CTF';
        return 'CREA';
    }

    public static function rotuloDocumentoTecnico(array $r): string
    {
        $conselho = self::conselhoResponsavel($r);
        if ($conselho === 'CAU') return 'RRT';
        if ($conselho === 'CTF') return 'Registro';
        return 'ART';
    }

    public static function textoPavimentos($quantidade): string
    {
        $n = max(1, (int) $quantidade);
        $nomes = [1 => 'UM', 2 => 'DOIS', 3 => 'TRÊS', 4 => 'QUATRO', 5 => 'CINCO', 6 => 'SEIS'];
        if ($n === 1) return 'PAVIMENTO TÉRREO';
        if ($n === 2) return 'DOIS PAVIMENTOS (TÉRREO E PRIMEIRO PAVIMENTO)';
        if ($n === 3) return 'TRÊS PAVIMENTOS (TÉRREO, PRIMEIRO E SEGUNDO PAVIMENTO)';
        return ($nomes[$n] ?? (string) $n) . ' PAVIMENTOS';
    }

    public static function especificacaoConstrucao(array $r): string
    {
        $tipo = trim((string) ($r['tipo_edificacao'] ?? ''));
        $area = trim((string) ($r['area_construcao'] ?? $r['area_construida'] ?? ''));
        if ($tipo === '' || $area === '') {
            return trim((string) ($r['especificacao'] ?? ''));
        }
        return 'CONSTRUÇÃO DE UMA ' . mb_strtoupper($tipo, 'UTF-8') . ' DE '
            . self::textoPavimentos($r['numero_pavimentos'] ?? 1)
            . ' COM ' . self::formatarArea($area) . ' M² DE ÁREA A SER CONSTRUÍDA.';
    }

    private static function numeroPorExtenso(int $n, bool $feminino): string
    {
        $masc = [1 => 'UM', 2 => 'DOIS', 3 => 'TRÊS', 4 => 'QUATRO', 5 => 'CINCO', 6 => 'SEIS', 7 => 'SETE', 8 => 'OITO', 9 => 'NOVE', 10 => 'DEZ'];
        $fem = [1 => 'UMA', 2 => 'DUAS'] + $masc;
        return ($feminino ? $fem : $masc)[$n] ?? (string) $n;
    }

    private static function listaPortugues(array $itens): string
    {
        if (!$itens) return '';
        if (count($itens) === 1) return $itens[0];
        $ultimo = array_pop($itens);
        return implode(', ', $itens) . ' E ' . $ultimo;
    }

    public static function caracteristicasHabite(array $r): string
    {
        $especificacao = trim((string) ($r['especificacao'] ?? ''));
        if ($especificacao !== '') {
            return $especificacao;
        }

        $campos = [
            'habite_uso', 'habite_pavimento', 'area_construida', 'habite_tipo_construcao',
            'habite_padrao', 'habite_estrutura', 'habite_portas', 'habite_janelas',
            'habite_piso', 'habite_paredes', 'habite_forro', 'habite_cobertura',
        ];
        foreach ($campos as $campo) {
            if (trim((string) ($r[$campo] ?? '')) === '') {
                return trim((string) ($r['especificacao'] ?? ''));
            }
        }

        $ambientesDados = json_decode((string) ($r['habite_ambientes_json'] ?? ''), true);
        $ambientesDados = is_array($ambientesDados) ? $ambientesDados : [];

        // Suporte ao novo modelo com compatibilidade graciosa para registros legados
        if (isset($ambientesDados['total_dormitorios'])) {
            $totalDormitorios = max(0, (int) $ambientesDados['total_dormitorios']);
            $suites = max(0, (int) ($ambientesDados['suites'] ?? 0));
            $banheirosSociais = max(0, (int) ($ambientesDados['banheiros_sociais'] ?? $ambientesDados['banheiros'] ?? 0));
        } elseif (isset($ambientesDados['banheiros_sociais'])) {
            $quartos = max(0, (int) ($ambientesDados['quartos'] ?? 0));
            $suites = max(0, (int) ($ambientesDados['suites'] ?? 0));
            $totalDormitorios = $quartos + $suites;
            $banheirosSociais = max(0, (int) $ambientesDados['banheiros_sociais']);
        } else {
            // Legado: onde 'quartos' era o total do imóvel e 'banheiros' incluía suítes
            $qLegacy = max(0, (int) ($ambientesDados['quartos'] ?? 0));
            $suites = max(0, (int) ($ambientesDados['suites'] ?? 0));
            $totalDormitorios = max($qLegacy, $suites);
            $bLegacy = max(0, (int) ($ambientesDados['banheiros'] ?? 0));
            $banheirosSociais = max(0, $bLegacy - $suites);
        }

        $salas = max(0, (int) ($ambientesDados['salas'] ?? 0));
        $cozinhas = max(0, (int) ($ambientesDados['cozinhas'] ?? 0));

        $ambientes = [];

        // 1. Dormitórios e Suítes
        if ($totalDormitorios > 0) {
            $dormTexto = self::numeroPorExtenso($totalDormitorios, false) . ' ' . ($totalDormitorios === 1 ? 'DORMITÓRIO' : 'DORMITÓRIOS');
            if ($suites > 0) {
                $dormTexto .= ', SENDO ' . self::numeroPorExtenso($suites, true) . ' ' . ($suites === 1 ? 'SUÍTE' : 'SUÍTES');
            }
            $ambientes[] = $dormTexto;
        }

        // 2. Banheiros sociais
        if ($banheirosSociais > 0) {
            $ambientes[] = self::numeroPorExtenso($banheirosSociais, false) . ' ' . ($banheirosSociais === 1 ? 'BANHEIRO SOCIAL' : 'BANHEIROS SOCIAIS');
        }

        // 3. Salas
        if ($salas > 0) {
            $ambientes[] = self::numeroPorExtenso($salas, true) . ' ' . ($salas === 1 ? 'SALA' : 'SALAS');
        }

        // 4. Cozinhas
        if ($cozinhas > 0) {
            $ambientes[] = self::numeroPorExtenso($cozinhas, true) . ' ' . ($cozinhas === 1 ? 'COZINHA' : 'COZINHAS');
        }

        // 5. Ambientes extras
        if (!empty($ambientesDados['extras']) && is_array($ambientesDados['extras'])) {
            foreach ($ambientesDados['extras'] as $extra) {
                $nExtra = max(0, (int) ($extra['quantidade'] ?? 0));
                $nomeExtra = mb_strtoupper(trim((string) ($extra['nome'] ?? '')), 'UTF-8');
                if ($nExtra > 0 && $nomeExtra !== '') {
                    $ambientes[] = self::numeroPorExtenso($nExtra, false) . ' ' . $nomeExtra;
                }
            }
        }

        $area = self::formatarArea($r['area_construida'] ?? $r['area_construcao'] ?? '');
        $ambientesTexto = $ambientes ? '. CONSTITUÍDO POR ' . self::listaPortugues($ambientes) . '.' : '.';

        return 'A EDIFICAÇÃO ' . mb_strtoupper((string) $r['habite_uso'], 'UTF-8')
            . ' COM ' . mb_strtoupper((string) $r['habite_pavimento'], 'UTF-8')
            . ' COM ÁREA CONSTRUÍDA DE ' . $area . ' M². O TIPO DA CONSTRUÇÃO É UMA '
            . mb_strtoupper((string) $r['habite_tipo_construcao'], 'UTF-8')
            . ' COM PADRÃO CONSTRUTIVO ' . mb_strtoupper((string) $r['habite_padrao'], 'UTF-8')
            . ', ESTRUTURA EM ' . mb_strtoupper((string) $r['habite_estrutura'], 'UTF-8')
            . ', ESQUADRIAS DE PORTAS EM ' . mb_strtoupper((string) $r['habite_portas'], 'UTF-8')
            . ' E JANELAS EM ' . mb_strtoupper((string) $r['habite_janelas'], 'UTF-8')
            . ', REVESTIMENTO DE PISO ' . mb_strtoupper((string) $r['habite_piso'], 'UTF-8')
            . ', REVESTIMENTO DAS PAREDES EM ' . mb_strtoupper((string) $r['habite_paredes'], 'UTF-8')
            . ', REVESTIMENTO SUPERIOR DE ' . mb_strtoupper((string) $r['habite_forro'], 'UTF-8')
            . ' E COBERTURA COM ' . mb_strtoupper((string) $r['habite_cobertura'], 'UTF-8')
            . $ambientesTexto;
    }

    /** Tipo assumido quando o requerimento não traz o campo (ver abaixo). */
    private const EDIFICACAO_TIPO_PADRAO = 'residencial';

    /**
     * Trecho do parecer de construção: "uma edificação residencial unifamiliar
     * com 53,00 m²".
     *
     * O tipo da edificação só passou a ser coletado no formulário reformulado,
     * então requerimento antigo não tem o campo. Nesses casos assume-se
     * "residencial", que é a esmagadora maioria do acervo; quando for outro
     * (comercial, mista...), quem analisa troca a palavra no editor, como já
     * fazia antes de o campo existir.
     */
    public static function edificacaoReferencia(array $r): string
    {
        $tipo = mb_strtolower(trim((string) ($r['tipo_edificacao'] ?? '')), 'UTF-8');
        if ($tipo === '') {
            $tipo = self::EDIFICACAO_TIPO_PADRAO;
        }
        $area = self::formatarArea($r['area_construcao'] ?? $r['area_construida'] ?? '');

        $sujeito = 'uma edificação ' . $tipo;
        if ($area === '') {
            return $sujeito;
        }
        return $sujeito . ' com ' . $area . ' m²';
    }

    public static function lotesDesmembramentoHtml(array $r): string
    {
        $json = json_decode((string) ($r['desmembramento_lotes_json'] ?? ''), true);
        $lotes = is_array($json['lotes'] ?? null) ? $json['lotes'] : [];
        if (!$lotes) {
            $espec = trim((string) ($r['especificacao'] ?? ''));
            return mb_strlen($espec, 'UTF-8') > 3
                ? '<p style="text-align:justify;">' . htmlspecialchars($espec, ENT_QUOTES, 'UTF-8') . '</p><br>'
                : '';
        }

        // O editor zera margin de <p>/<div> pra bater com o TCPDF (que ignora
        // margem vertical) — o espaçamento entre blocos tem que vir de <br>.
        $html = '';
        foreach ($lotes as $indice => $lote) {
            $numero = (int) ($lote['ordem'] ?? ($indice + 1));
            $cadastro = trim((string) ($lote['cadastro_imobiliario'] ?? $r['cadastro_imobiliario'] ?? ''));
            $area = self::formatarArea($lote['area'] ?? '');
            $cadastroTexto = $cadastro !== '' ? ' DO CADASTRO ' . htmlspecialchars($cadastro, ENT_QUOTES, 'UTF-8') : '';

            $linhas = ['<strong>DESCRIÇÃO DO LOTE Nº ' . $numero . '</strong>'
                . $cadastroTexto . ' <strong>COM ' . htmlspecialchars($area, ENT_QUOTES, 'UTF-8') . ' M²:</strong>'];

            if (($lote['geometria'] ?? 'regular') === 'irregular') {
                $descricaoIrregular = mb_strtoupper(trim((string) ($lote['descricao_irregular'] ?? '')), 'UTF-8');
                $linhas[] = htmlspecialchars($descricaoIrregular, ENT_QUOTES, 'UTF-8');
            } else {
                foreach (['norte' => 'AO NORTE', 'oeste' => 'A OESTE', 'leste' => 'AO LESTE', 'sul' => 'AO SUL'] as $rumo => $textoRumo) {
                    $lado = (array) ($lote['confrontacoes'][$rumo] ?? []);
                    $metragem = self::formatarArea($lado['metragem'] ?? '');
                    $confinante = mb_strtoupper(trim((string) ($lado['descricao'] ?? '')), 'UTF-8');
                    $linhas[] = htmlspecialchars($metragem, ENT_QUOTES, 'UTF-8') . ' METROS ' . $textoRumo
                        . ' CONFINANTE COM ' . htmlspecialchars($confinante, ENT_QUOTES, 'UTF-8') . '.';
                }
            }

            $html .= '<p style="text-align:justify;">' . implode('<br>', $linhas) . '</p><br>';
        }
        return $html;
    }

    public static function numerosLotesDesmembramento(array $r): string
    {
        $json = json_decode((string) ($r['desmembramento_lotes_json'] ?? ''), true);
        $lotes = is_array($json['lotes'] ?? null) ? $json['lotes'] : [];
        if (!$lotes) return '1';
        $numeros = [];
        foreach ($lotes as $indice => $lote) {
            $numeros[] = (string) ((int) ($lote['ordem'] ?? ($indice + 1)));
        }
        return self::listaPortugues($numeros);
    }

    public static function somaLotesDesmembramento(array $r): string
    {
        $json = json_decode((string) ($r['desmembramento_lotes_json'] ?? ''), true);
        if (is_array($json) && isset($json['soma_lotes'])) {
            return self::formatarArea($json['soma_lotes']);
        }
        return self::formatarArea($r['area_lote'] ?? '');
    }

    public static function formatarArea($valor): string
    {
        if (is_numeric($valor)) {
            return number_format((float) $valor, 2, ',', '.');
        }
        // Parte dos requerimentos foi enviada com a unidade dentro do campo
        // ("52,03 m²"). Os modelos escrevem o "m²" por conta própria, então a
        // unidade sai daqui pra não sobrar "52,03 m² m²" no documento.
        $texto = trim((string) $valor);
        $semUnidade = preg_replace('/\s*(m²|m2|metros\s+quadrados)\s*$/iu', '', $texto);
        return trim((string) $semUnidade);
    }

    public static function configuracao(PDO $pdo, string $chave, string $padrao): string
    {
        try {
            $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $valor = trim((string) $stmt->fetchColumn());
            return $valor !== '' ? $valor : $padrao;
        } catch (Throwable $e) {
            return $padrao;
        }
    }
}
