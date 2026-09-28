<?php
/**
 * Leitura do histórico do processo (historico_acoes) para a tela.
 *
 * O histórico é texto livre gravado por cada ação ("Alterou status para
 * 'Pendente' com a observação: ..."). Aqui cada texto vira um item com tipo,
 * ícone, cor, título curto e o detalhe separado (observação, motivo, nome do
 * documento), para a linha do tempo mostrar o que aconteceu de relance e o
 * detalhe logo abaixo — em vez de uma frase cortada numa linha só.
 */


if (!function_exists('categoriasHistorico')) {
    /** tipo => [rótulo do filtro, ícone, cor] */
    function categoriasHistorico(): array
    {
        return [
            'status'     => ['Status', 'fa-arrows-rotate', '#2563eb'],
            'documento'  => ['Documentos', 'fa-file-signature', '#0a6b34'],
            'fluxo'      => ['Tramitação', 'fa-share-from-square', '#7c3aed'],
            'email'      => ['E-mails', 'fa-envelope', '#0e7490'],
            'decisao'    => ['Decisões', 'fa-gavel', '#b13232'],
            'pagamento'  => ['Pagamento', 'fa-barcode', '#b45309'],
            'consulta'   => ['Consultas', 'fa-eye', '#64748b'],
            'correcao'   => ['Correções', 'fa-screwdriver-wrench', '#475569'],
            'outro'      => ['Outros', 'fa-circle-dot', '#64748b'],
        ];
    }
}

if (!function_exists('corStatusHistorico')) {
    function corStatusHistorico(string $status): string
    {
        $s = mb_strtolower($status, 'UTF-8');
        return match (true) {
            str_contains($s, 'indefer') || str_contains($s, 'reprov') || str_contains($s, 'cancel') => '#b13232',
            str_contains($s, 'finaliz') || str_contains($s, 'aprov') || str_contains($s, 'emitido') || str_contains($s, 'apto') || str_contains($s, 'pago') => '#0a6b34',
            str_contains($s, 'pendente') || str_contains($s, 'aguardando') || str_contains($s, 'devolvido') => '#b45309',
            default => '#2563eb',
        };
    }
}

if (!function_exists('nomeDocumentoHistorico')) {
    /** "PARECER TECNICO HABITE SE AMBIENTAL" / "parecer_tecnico_habite_se" → "Parecer técnico habite-se ambiental". */
    function nomeDocumentoHistorico(string $bruto): string
    {
        $t = trim($bruto);
        $t = preg_replace('/\s*\(ID:[^)]*\)\s*/i', ' ', $t) ?? $t;
        $t = mb_strtolower(str_replace('_', ' ', trim($t)), 'UTF-8');
        $t = strtr($t, [
            'tecnico' => 'técnico', 'licenca' => 'licença', 'previa' => 'prévia', 'alvara' => 'alvará',
            'construcao' => 'construção', 'habite se' => 'habite-se', 'atividade economica' => 'atividade econômica',
            'unica' => 'única', 'pendencias' => 'pendências',
        ]);
        return $t === '' ? '' : mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($t, 1, null, 'UTF-8');
    }
}

if (!function_exists('classificarAcaoHistorico')) {
    /**
     * @return array{tipo:string, icone:string, cor:string, titulo:string, detalhe:string, status:?string}
     */
    function classificarAcaoHistorico(string $acao, ?string $adminNome = null): array
    {
        $acao = trim($acao);
        $cats = categoriasHistorico();
        $item = static function (string $tipo, string $titulo, string $detalhe = '', ?string $icone = null, ?string $cor = null, ?string $status = null) use ($cats): array {
            return [
                'tipo' => $tipo,
                'icone' => $icone ?? $cats[$tipo][1],
                'cor' => $cor ?? $cats[$tipo][2],
                'titulo' => $titulo,
                'detalhe' => trim($detalhe),
                'status' => $status,
            ];
        };

        // Status: "Alterou status para 'X'[ com a observação: Y]"
        if (preg_match("/^Alterou status para '([^']+)'(?:\\s*com a observação:\\s*(.*))?$/su", $acao, $m)) {
            return $item('status', 'Status alterado para ' . $m[1], $m[2] ?? '', 'fa-arrows-rotate', corStatusHistorico($m[1]), $m[1]);
        }
        if (preg_match("/^Reabriu o processo finalizado e alterou status para '([^']+)'(?:\\s*-\\s*Motivo:\\s*(.*))?$/su", $acao, $m)) {
            return $item('status', 'Processo reaberto (' . $m[1] . ')', $m[2] ?? '', 'fa-lock-open', '#b45309', $m[1]);
        }
        if (preg_match('/^Indeferiu o processo.*?Motivo:\s*(.*)$/su', $acao, $m)) {
            return $item('decisao', 'Processo indeferido', $m[1], 'fa-ban', '#b13232', 'Indeferido');
        }
        if (preg_match('/^Arquivou o processo(?:\s*-\s*Motivo:\s*(.*))?$/su', $acao, $m)) {
            return $item('decisao', 'Processo arquivado', $m[1] ?? '', 'fa-box-archive', '#475569');
        }
        if (preg_match('/^Aprovou e Assinou o Alvará/u', $acao)) {
            return $item('decisao', 'Secretário aprovou e assinou o alvará', '', 'fa-stamp', '#0a6b34');
        }
        if (preg_match('/^(Finalizou o processo|Concluiu (?:e finalizou )?(?:o )?processo|Concluiu a vistoria)(.*)$/su', $acao, $m)) {
            $falhou = stripos($m[2], 'FALHOU') !== false;
            return $item('decisao', trim($m[1] . preg_replace('/,.*$/su', '', $m[2])), preg_match('/,\s*(.*)$/su', $m[2], $d) ? $d[1] : '',
                $falhou ? 'fa-triangle-exclamation' : 'fa-flag-checkered', $falhou ? '#b13232' : '#0a6b34');
        }
        if (preg_match('/^Restaurou processo arquivado(.*)$/su', $acao)) {
            return $item('decisao', 'Restaurou processo arquivado', '', 'fa-box-open', '#b45309');
        }

        // Documentos
        if (preg_match('/^Recusou a co-assinatura do documento\s*(\S*)(.*)$/su', $acao, $m)) {
            return $item('documento', 'Recusou a coassinatura', trim((string) preg_replace('/^[\s:—–-]*(?:Motivo:)?\s*/u', '', $m[2])), 'fa-circle-xmark', '#b13232');
        }
        if (preg_match('/^Assinou digitalmente um parecer/u', $acao)) {
            return $item('documento', 'Assinou parecer pelo editor', '', 'fa-file-signature');
        }
        if (preg_match('/^Co-assinou digitalmente o documento:\s*(.*)$/su', $acao, $m)) {
            return $item('documento', 'Coassinou documento', nomeDocumentoHistorico($m[1]), 'fa-signature');
        }
        if (preg_match('/^Gerou e assinou (?:eletronicamente|digitalmente)(?: o documento| parecer técnico)?(\s*\(requisitou co-assinatura\))?:?\s*(.*)$/su', $acao, $m)) {
            return $item('documento', $m[1] !== '' ? 'Assinou documento e pediu coassinatura' : 'Gerou e assinou documento', nomeDocumentoHistorico($m[2]), 'fa-file-signature');
        }
        if (preg_match('/^Gerou (?:e assinou )?parecer técnico usando template:\s*(.*)$/su', $acao, $m)
            || preg_match('/^Gerou documento sem assinatura:\s*(.*)$/su', $acao, $m)) {
            return $item('documento', 'Gerou documento', nomeDocumentoHistorico($m[1]), 'fa-file-lines');
        }
        if (preg_match('/^(Removeu da listagem|Excluiu permanentemente) o documento assinado(.*)$/su', $acao, $m)
            || preg_match('/^(Excluiu parecer técnico):?(.*)$/su', $acao, $m)) {
            return $item('documento', $m[1] === 'Removeu da listagem' ? 'Removeu documento da listagem' : 'Excluiu documento', nomeDocumentoHistorico($m[2]), 'fa-trash-can', '#b13232');
        }
        if (preg_match('/^Baixou todos os documentos(.*)$/su', $acao, $m)) {
            return $item('consulta', 'Baixou os documentos do processo', trim($m[1]), 'fa-download');
        }
        if (preg_match('/^Gerou placa/u', $acao)) {
            return $item('documento', 'Gerou placa de licenciamento', '', 'fa-sign-hanging');
        }

        // Tramitação entre setores
        if (preg_match('/^Enviou processo ao (Setor \d+)(?:\s*—\s*([^:]*))?(?::\s*(.*))?$/su', $acao, $m)) {
            return $item('fluxo', 'Enviou ao ' . $m[1] . (!empty($m[2]) ? ' — ' . trim($m[2]) : ''), $m[3] ?? '', 'fa-share-from-square');
        }
        if (preg_match('/^Enviou processo para (.*)$/su', $acao, $m)) {
            return $item('fluxo', 'Enviou para ' . trim($m[1]), '', 'fa-share-from-square');
        }
        if (preg_match('/^(Setor \d+ (?:aprovou|revisou).*)$/su', $acao, $m)) {
            return $item('fluxo', trim(preg_replace('/\s*—\s*/u', ' — ', $m[1])), '', 'fa-reply');
        }
        if (preg_match('/^(Devolveu|Retornou|Encaminhou)(.*)$/su', $acao, $m)) {
            [$titulo, $detalhe] = array_pad(preg_split('/:\s*|\s+-\s*Motivo:\s*/u', $m[1] . $m[2], 2) ?: [], 2, '');
            return $item('fluxo', trim($titulo), $detalhe, 'fa-reply');
        }

        // E-mails
        if (preg_match('/^Enviou e-?mail com protocolo oficial:\s*(\S+)(.*)$/su', $acao, $m)) {
            return $item('email', 'Enviou protocolo oficial por e-mail', 'Protocolo ' . rtrim($m[1], '.,') . (trim($m[2]) !== '' ? ' ' . trim($m[2]) : ''), 'fa-envelope-circle-check');
        }
        if (preg_match('/^(Enviou|Reenviou).*e-?mail(.*)$/sui', $acao)) {
            return $item('email', 'E-mail enviado', $acao, 'fa-envelope');
        }

        // Pagamento
        if (preg_match('/boleto|pagamento|comprovante/iu', $acao)) {
            return $item('pagamento', $acao, '');
        }

        // Consultas
        if (str_starts_with($acao, 'Visualizou o requerimento pela primeira vez')) {
            return $item('consulta', 'Abriu o processo pela primeira vez', '', 'fa-eye');
        }
        if (str_starts_with($acao, 'Marcou o requerimento como não lido')) {
            return $item('consulta', 'Marcou como não lido', '', 'fa-envelope');
        }

        // Correções do sistema
        if (preg_match('/^(Correção|Migração automática):\s*(.*)$/su', $acao, $m)) {
            return $item('correcao', $m[1] === 'Correção' ? 'Correção de dados' : 'Ajuste automático do sistema', $m[2]);
        }

        // Genérico: separa "Título: detalhe" quando houver.
        $partes = preg_split('/:\s+/u', $acao, 2) ?: [$acao];
        if (count($partes) === 2 && mb_strlen($partes[0], 'UTF-8') <= 70) {
            return $item('outro', $partes[0], $partes[1]);
        }
        return $item('outro', $acao);
    }
}

if (!function_exists('quandoHistorico')) {
    /** "hoje, 14:32" / "ontem, 09:10" / "há 5 dias · 22/09/2026 10:15" / "12/08/2026 15:05". */
    function quandoHistorico(string $dataHora, ?DateTimeImmutable $agora = null): string
    {
        try {
            $d = new DateTimeImmutable($dataHora);
        } catch (Throwable $e) {
            return $dataHora;
        }
        $agora ??= new DateTimeImmutable();
        $dias = (int) $agora->setTime(0, 0)->diff($d->setTime(0, 0))->format('%r%a');
        return match (true) {
            $dias === 0 => 'hoje, ' . $d->format('H:i'),
            $dias === -1 => 'ontem, ' . $d->format('H:i'),
            $dias < -1 && $dias >= -6 => 'há ' . abs($dias) . ' dias · ' . $d->format('d/m/Y H:i'),
            default => $d->format('d/m/Y H:i'),
        };
    }
}

if (!function_exists('iniciaisHistorico')) {
    function iniciaisHistorico(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $ini = mb_substr($partes[0] ?? '', 0, 1, 'UTF-8') . (count($partes) > 1 ? mb_substr(end($partes), 0, 1, 'UTF-8') : '');
        return mb_strtoupper($ini !== '' ? $ini : '?', 'UTF-8');
    }
}

if (!function_exists('itemHistoricoHtml')) {
    /**
     * Um item da linha do tempo do processo.
     * $compacto: resumo do fim da página (detalhe em até 2 linhas).
     */
    function itemHistoricoHtml(array $h, bool $compacto = false, bool $ultimo = false): string
    {
        $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $autor = trim((string) ($h['admin_nome'] ?? '')) ?: 'Sistema';
        $c = classificarAcaoHistorico((string) ($h['acao'] ?? ''), $autor);
        $cat = categoriasHistorico()[$c['tipo']] ?? categoriasHistorico()['outro'];

        $html = '<div class="hist-item' . ($compacto ? ' hist-item--compacto' : '') . '" data-hist-tipo="' . $e($c['tipo']) . '">'
            . '<div class="hist-marca"><span class="hist-icone" style="--hist-cor:' . $e($c['cor']) . '"><i class="fas ' . $e($c['icone']) . '"></i></span>'
            . ($ultimo ? '' : '<span class="hist-fio"></span>') . '</div>'
            . '<div class="hist-corpo">'
            . '<div class="hist-topo"><span class="hist-titulo">' . $e($c['titulo']) . '</span>'
            . '<span class="hist-tag" style="--hist-cor:' . $e($cat[2]) . '">' . $e($cat[0]) . '</span></div>';
        if ($c['detalhe'] !== '') {
            $html .= '<div class="hist-detalhe">' . nl2br($e($c['detalhe'])) . '</div>';
        }
        $html .= '<div class="hist-meta"><span class="hist-autor" title="' . $e($autor) . '">'
            . '<span class="hist-avatar">' . $e(iniciaisHistorico($autor)) . '</span>' . $e($autor) . '</span>'
            . '<span class="hist-quando" title="' . $e(date('d/m/Y H:i:s', strtotime((string) ($h['data_acao'] ?? 'now')))) . '">'
            . '<i class="far fa-clock"></i> ' . $e(quandoHistorico((string) ($h['data_acao'] ?? ''))) . '</span></div>'
            . '</div></div>';
        return $html;
    }
}
