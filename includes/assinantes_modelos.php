<?php
/**
 * Quem assina os modelos ambientais (LAU e Parecer Técnico de Pendências).
 *
 * Configurado em admin/assinantes_modelos.php e guardado na tabela configuracoes.
 * O documento já sai com os blocos de nome/cargo/matrícula dessas pessoas e,
 * ao assinar, o sistema pede a coassinatura das demais
 * (ver admin/assinatura/processa_assinatura.php).
 */

require_once __DIR__ . '/documento_regras.php';

if (!function_exists('papeisAssinantesModelo')) {
    /** papel => [título na tela, rótulo padrão do cargo] */
    function papeisAssinantesModelo(): array
    {
        return [
            'secretario'       => ['Secretário', 'Secretário Municipal de Meio Ambiente'],
            'eng_ambiental'    => ['Engenheiro(a) Ambiental', 'Eng. Ambiental'],
            'fiscal_ambiental' => ['Fiscal Ambiental', 'Fiscal de Meio Ambiente'],
        ];
    }
}

if (!function_exists('assinantesModelo')) {
    /**
     * @return array<string,array{id:int,nome:string,rotulo:string,matricula:string}>
     */
    function assinantesModelo(?PDO $pdo): array
    {
        $assinantes = [];
        foreach (papeisAssinantesModelo() as $papel => [, $rotuloPadrao]) {
            $assinantes[$papel] = ['id' => 0, 'nome' => '', 'rotulo' => $rotuloPadrao, 'matricula' => ''];
            if (!$pdo) {
                continue;
            }

            $id = (int) DocumentoRegras::configuracao($pdo, "modelo_assinante_{$papel}_id", '0');
            $admin = $id > 0 ? buscarAdminAssinanteModelo($pdo, $id) : null;
            if (!$admin && $papel === 'secretario') {
                $admin = buscarSecretarioPadraoModelo($pdo);
            }

            if ($admin) {
                $assinantes[$papel]['id'] = (int) $admin['id'];
                $assinantes[$papel]['nome'] = trim((string) ($admin['nome_completo'] ?: $admin['nome']));
                $assinantes[$papel]['matricula'] = trim((string) ($admin['matricula_portaria'] ?? ''));
            }
            $assinantes[$papel]['rotulo'] = DocumentoRegras::configuracao($pdo, "modelo_assinante_{$papel}_rotulo", $rotuloPadrao);
            $assinantes[$papel]['matricula'] = DocumentoRegras::configuracao(
                $pdo,
                "modelo_assinante_{$papel}_matricula",
                $assinantes[$papel]['matricula']
            );
        }
        return $assinantes;
    }
}

if (!function_exists('buscarAdminAssinanteModelo')) {
    function buscarAdminAssinanteModelo(PDO $pdo, int $id): ?array
    {
        try {
            $stmt = $pdo->prepare('SELECT id, nome, nome_completo, matricula_portaria FROM administradores WHERE id = ? AND ativo = 1');
            $stmt->execute([$id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            return $admin ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('buscarSecretarioPadraoModelo')) {
    /** Sem configuração, usa o secretário ativo — só se houver exatamente um. */
    function buscarSecretarioPadraoModelo(PDO $pdo): ?array
    {
        try {
            $stmt = $pdo->query("SELECT id, nome, nome_completo, matricula_portaria FROM administradores
                WHERE nivel = 'secretario' AND ativo = 1 LIMIT 2");
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return count($linhas) === 1 ? $linhas[0] : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('variaveisAssinantesModelo')) {
    /** Variáveis {{assinante_<papel>_nome|rotulo|matricula}} para os templates. */
    function variaveisAssinantesModelo(?PDO $pdo): array
    {
        $vars = [];
        foreach (assinantesModelo($pdo) as $papel => $a) {
            $vars["assinante_{$papel}_nome"] = $a['nome'];
            $vars["assinante_{$papel}_rotulo"] = $a['rotulo'];
            $vars["assinante_{$papel}_matricula"] = $a['matricula'];
            // Cargo + matrícula numa linha só, sem "Não informado" quando falta a matrícula.
            $vars["assinante_{$papel}_cargo_linha"] = $a['rotulo'] . ($a['matricula'] !== '' ? ' – ' . $a['matricula'] : '');
        }
        return $vars;
    }
}

if (!function_exists('idsCoassinantesModelo')) {
    /** Quem deve coassinar o modelo, tirando quem está assinando agora. */
    function idsCoassinantesModelo(?PDO $pdo, string $template, int $assinanteAtualId): array
    {
        if (!DocumentoRegras::templateComAssinantesFixos($template)) {
            return [];
        }
        $papeis = $template === DocumentoRegras::TEMPLATE_PARECER_PENDENCIAS
            ? ['eng_ambiental', 'fiscal_ambiental']
            : ['secretario', 'eng_ambiental', 'fiscal_ambiental'];
        $assinantes = assinantesModelo($pdo);
        $ids = [];
        foreach ($papeis as $papel) {
            $id = (int) ($assinantes[$papel]['id'] ?? 0);
            if ($id > 0 && $id !== $assinanteAtualId) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }
}
