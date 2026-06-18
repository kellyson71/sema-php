<?php
/**
 * Helper de progresso de co-assinatura.
 * Centraliza o cálculo de "quem já assinou / quem falta / quem recusou" de um
 * documento, para que a tela dedicada, o visualizar_documento e o editor
 * mostrem sempre os mesmos números.
 */

if (!function_exists('statusAssinaturasDocumento')) {
    /**
     * @return array{
     *   assinantes: array<int,array{nome:string,cargo:?string,data:string}>,
     *   pendentes:  array<int,array{destinatario_id:int,nome:string,solicitante_nome:string,mensagem:?string}>,
     *   recusados:  array<int,array{nome:string,motivo:?string,data:?string}>,
     *   total_assinado: int,
     *   total_esperado: int,
     *   completo: bool,
     *   solicitante_id: ?int
     * }
     */
    function statusAssinaturasDocumento(PDO $pdo, string $documentoId): array
    {
        // Quem já assinou (linhas de assinaturas_digitais, exceto geração sem assinatura)
        $assinantes = [];
        try {
            $st = $pdo->prepare("
                SELECT assinante_id, assinante_nome, assinante_cargo, timestamp_assinatura
                FROM assinaturas_digitais
                WHERE documento_id = ? AND tipo_assinatura <> 'sem_assinatura'
                ORDER BY timestamp_assinatura ASC, id ASC
            ");
            $st->execute([$documentoId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $assinantes[] = [
                    'id'    => (int) $r['assinante_id'],
                    'nome'  => $r['assinante_nome'],
                    'cargo' => $r['assinante_cargo'],
                    'data'  => $r['timestamp_assinatura'],
                ];
            }
        } catch (Throwable $e) {
        }

        // Solicitações por status
        $pendentes = [];
        $recusados = [];
        $solicitanteId = null;
        try {
            $st = $pdo->prepare("
                SELECT sa.destinatario_id, sa.solicitante_id, sa.status, sa.mensagem,
                       sa.motivo_recusa, sa.resolvido_em,
                       d.nome AS destinatario_nome,
                       s.nome AS solicitante_nome
                FROM solicitacoes_assinatura sa
                JOIN administradores d ON d.id = sa.destinatario_id
                JOIN administradores s ON s.id = sa.solicitante_id
                WHERE sa.documento_id = ?
                ORDER BY sa.criado_em ASC
            ");
            $st->execute([$documentoId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $solicitanteId = (int) $r['solicitante_id'];
                if ($r['status'] === 'pendente') {
                    $pendentes[] = [
                        'destinatario_id'  => (int) $r['destinatario_id'],
                        'nome'             => $r['destinatario_nome'],
                        'solicitante_nome' => $r['solicitante_nome'],
                        'mensagem'         => $r['mensagem'],
                    ];
                } elseif ($r['status'] === 'recusado') {
                    $recusados[] = [
                        'nome'   => $r['destinatario_nome'],
                        'motivo' => $r['motivo_recusa'],
                        'data'   => $r['resolvido_em'],
                    ];
                }
            }
        } catch (Throwable $e) {
        }

        $totalAssinado = count($assinantes);
        // Esperado = quem já assinou + quem ainda está pendente
        $totalEsperado = $totalAssinado + count($pendentes);

        return [
            'assinantes'     => $assinantes,
            'pendentes'      => $pendentes,
            'recusados'      => $recusados,
            'total_assinado' => $totalAssinado,
            'total_esperado' => $totalEsperado,
            'completo'       => count($pendentes) === 0 && $totalAssinado > 0,
            'solicitante_id' => $solicitanteId,
        ];
    }
}

if (!function_exists('contarAssinaturasPendentesPara')) {
    /** Nº de solicitações de co-assinatura pendentes para um admin. */
    function contarAssinaturasPendentesPara(PDO $pdo, int $adminId): int
    {
        try {
            $st = $pdo->prepare("
                SELECT COUNT(*) FROM solicitacoes_assinatura
                WHERE destinatario_id = ? AND status = 'pendente'
            ");
            $st->execute([$adminId]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
