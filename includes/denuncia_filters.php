<?php

/**
 * Regras compartilhadas da listagem de denúncias.
 *
 * Este arquivo mantém a validação independente da interface para que a mesma
 * regra seja usada ao ler a URL, persistir preferências e montar o feed geral.
 */

const DENUNCIA_PREFERENCE_PAGE = 'denuncias';

function denunciaFilterOptions(): array
{
    return [
        'setor' => ['', 'meio_ambiente', 'obras_urbanismo'],
        'origem' => ['', 'publico', 'interno', 'minhas'],
        'status' => ['', 'pendente', 'em_analise', 'concluida'],
        'anonimo' => ['', '1', '0'],
        'concluidas' => ['0', '1'],
    ];
}

function normalizarStatusProcesso(string $status): string
{
    $status = mb_strtolower(trim($status), 'UTF-8');
    $status = strtr($status, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        '-' => ' ', '_' => ' ',
    ]);
    $status = preg_replace('/\s+/', ' ', $status) ?? $status;

    return match ($status) {
        'em analise' => 'em_analise',
        'concluida', 'concluido', 'finalizada', 'finalizado' => 'concluida',
        default => str_replace(' ', '_', $status),
    };
}

function validarFiltrosDenuncia(array $values, bool $strict = false): array
{
    $options = denunciaFilterOptions();
    $result = [];

    foreach ($options as $key => $allowed) {
        if (!array_key_exists($key, $values)) {
            continue;
        }

        $value = is_scalar($values[$key]) ? trim((string) $values[$key]) : '';
        if (!in_array($value, $allowed, true)) {
            if ($strict) {
                throw new InvalidArgumentException("Filtro de denúncia inválido: {$key}");
            }
            continue;
        }
        $result[$key] = $value;
    }

    return $result;
}

function filtrosSistemaDenuncia(string $setorAdmin): array
{
    return [
        'setor' => in_array($setorAdmin, ['meio_ambiente', 'obras_urbanismo'], true) ? $setorAdmin : '',
        'origem' => '',
        'status' => '',
        'anonimo' => '',
        'concluidas' => '0',
    ];
}

function filtrosLimposDenuncia(): array
{
    return [
        'setor' => '',
        'origem' => '',
        'status' => '',
        'anonimo' => '',
        'concluidas' => '0',
    ];
}

function carregarPreferenciaDenuncia(PDO $pdo, int $adminId): ?array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT filtros_json FROM admin_preferencias WHERE admin_id = ? AND pagina_chave = ? LIMIT 1'
        );
        $stmt->execute([$adminId, DENUNCIA_PREFERENCE_PAGE]);
        $json = $stmt->fetchColumn();
        if ($json === false) {
            return null;
        }

        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return null;
        }

        return validarFiltrosDenuncia($decoded);
    } catch (PDOException $e) {
        // Compatibilidade durante o deploy: antes da migration, aplica o padrão
        // do sistema em vez de interromper a área administrativa.
        return null;
    }
}

function salvarPreferenciaDenuncia(PDO $pdo, int $adminId, array $values): array
{
    $validated = validarFiltrosDenuncia($values, true);
    $expected = array_keys(denunciaFilterOptions());
    foreach ($expected as $key) {
        if (!array_key_exists($key, $validated)) {
            throw new InvalidArgumentException("Filtro de denúncia ausente: {$key}");
        }
    }

    $json = json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare(
        'INSERT INTO admin_preferencias (admin_id, pagina_chave, filtros_json)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE filtros_json = VALUES(filtros_json), data_atualizacao = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$adminId, DENUNCIA_PREFERENCE_PAGE, $json]);

    return $validated;
}

function setorAdministrador(PDO $pdo, int $adminId): string
{
    try {
        $stmt = $pdo->prepare('SELECT setor FROM administradores WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $setor = (string) ($stmt->fetchColumn() ?: 'ambos');
        return in_array($setor, ['meio_ambiente', 'obras_urbanismo', 'ambos'], true) ? $setor : 'ambos';
    } catch (PDOException $e) {
        return 'ambos';
    }
}

/**
 * Resolve campo a campo: URL explícita > preferência salva > padrão do setor.
 * `limpar=1` é intencionalmente temporário e não grava nem apaga a preferência.
 */
function resolverFiltrosDenuncia(array $query, ?array $saved, string $setorAdmin): array
{
    if (($query['limpar'] ?? '') === '1') {
        return filtrosLimposDenuncia();
    }

    $resolved = array_merge(filtrosSistemaDenuncia($setorAdmin), validarFiltrosDenuncia($saved ?? []));
    $explicit = validarFiltrosDenuncia($query);

    foreach ($explicit as $key => $value) {
        $resolved[$key] = $value;
    }

    return $resolved;
}

function tituloDenuncia(array $denuncia): string
{
    $infrator = trim((string) ($denuncia['infrator_nome'] ?? ''));
    $infratorNormalizado = mb_strtolower($infrator, 'UTF-8');
    $naoIdentificado = $infrator === '' || in_array($infratorNormalizado, ['não informado', 'nao informado'], true);
    if (!$naoIdentificado) {
        return $infrator;
    }
    if (!empty($denuncia['anonimo'])) {
        return 'Denúncia anônima';
    }
    return 'Infrator não identificado';
}

function tiposDenuncia(array $denuncia): array
{
    $tipos = $denuncia['tipo_denuncia'] ?? [];
    if (is_string($tipos)) {
        $decoded = json_decode($tipos, true);
        $tipos = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($tipos)) {
        return [];
    }
    $labels = [
        'obstrucao_via' => 'Obstrução de via',
        'terreno_sujo' => 'Terreno sujo',
        'terreno_baldio' => 'Terreno baldio',
        'esgoto_via' => 'Esgoto em via pública',
        'construcao_irregular' => 'Construção irregular',
        'entulho_construcao' => 'Entulho em construção civil',
        'entulho_via' => 'Entulho em via pública',
        'outros' => 'Outros',
    ];
    $tipos = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $tipos)));
    return array_map(static function (string $tipo) use ($labels): string {
        if (isset($labels[$tipo])) {
            return $labels[$tipo];
        }
        return mb_convert_case(str_replace('_', ' ', $tipo), MB_CASE_TITLE, 'UTF-8');
    }, $tipos);
}
