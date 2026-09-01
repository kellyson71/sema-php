<?php

final class DocumentoRegras
{
    private const TEMPLATES_NUMERADOS = [
        'alvara_de_construcao',
        'alvara_de_desmembramento',
        'carta_habite_se',
    ];

    public static function templateNumerado(string $template): bool
    {
        return in_array($template, self::TEMPLATES_NUMERADOS, true);
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

    public static function lotesDesmembramentoHtml(array $r): string
    {
        $json = json_decode((string) ($r['desmembramento_lotes_json'] ?? ''), true);
        $lotes = is_array($json['lotes'] ?? null) ? $json['lotes'] : [];
        if (!$lotes) {
            $espec = trim((string) ($r['especificacao'] ?? ''));
            return mb_strlen($espec, 'UTF-8') > 3 ? '<p style="text-align:justify;">' . htmlspecialchars($espec, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        }

        $html = '';
        foreach ($lotes as $indice => $lote) {
            $numero = (int) ($lote['ordem'] ?? ($indice + 1));
            $cadastro = trim((string) ($lote['cadastro_imobiliario'] ?? $r['cadastro_imobiliario'] ?? ''));
            $area = self::formatarArea($lote['area'] ?? '');
            $cadastroTexto = $cadastro !== '' ? ' DO CADASTRO ' . htmlspecialchars($cadastro, ENT_QUOTES, 'UTF-8') : '';
            $html .= '<p><strong>DESCRIÇÃO DO LOTE Nº ' . $numero . $cadastroTexto
                . ' COM ' . htmlspecialchars($area, ENT_QUOTES, 'UTF-8') . ' M²:</strong></p>';

            if (($lote['geometria'] ?? 'regular') === 'irregular') {
                $descricaoIrregular = mb_strtoupper(trim((string) ($lote['descricao_irregular'] ?? '')), 'UTF-8');
                $html .= '<p>' . htmlspecialchars($descricaoIrregular, ENT_QUOTES, 'UTF-8') . '</p>';
                continue;
            }

            foreach (['norte' => 'AO NORTE', 'oeste' => 'A OESTE', 'leste' => 'AO LESTE', 'sul' => 'AO SUL'] as $rumo => $textoRumo) {
                $lado = (array) ($lote['confrontacoes'][$rumo] ?? []);
                $metragem = self::formatarArea($lado['metragem'] ?? '');
                $confinante = mb_strtoupper(trim((string) ($lado['descricao'] ?? '')), 'UTF-8');
                $html .= '<p>' . htmlspecialchars($metragem, ENT_QUOTES, 'UTF-8') . ' METROS ' . $textoRumo
                    . ' CONFINANTE COM ' . htmlspecialchars($confinante, ENT_QUOTES, 'UTF-8') . '.</p>';
            }
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
        return trim((string) $valor);
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
