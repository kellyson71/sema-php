<?php

/**
 * Regras compartilhadas pelos fluxos de assinatura e coassinatura.
 * Mantém mensagens/códigos previsíveis para os clientes AJAX e concentra a
 * resolução da pessoa usada na linha de assinatura física.
 */

final class AssinaturaWorkflowException extends RuntimeException
{
    public function __construct(
        private readonly string $codigoPublico,
        string $mensagemPublica,
        private readonly int $httpStatus = 400,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensagemPublica, 0, $anterior);
    }

    public function codigoPublico(): string
    {
        return $this->codigoPublico;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

/**
 * Endpoints JSON não podem seguir o redirect HTML de verificaLogin().
 * Tenta restaurar a sessão confiada e informa ao chamador se há um admin.
 */
function assinaturaSessaoAdminAtiva(PDO $pdo): bool
{
    if (!empty($_SESSION['admin_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT nivel FROM administradores WHERE id = ? AND ativo = 1 LIMIT 1');
            $stmt->execute([(int) $_SESSION['admin_id']]);
            $nivel = $stmt->fetchColumn();
            if ($nivel === false) {
                return false;
            }
            if (!isset($_SESSION['admin_nivel_original'])) {
                $_SESSION['admin_nivel'] = $nivel;
            }
            return true;
        } catch (Throwable $e) {
            error_log('[assinatura] Falha ao validar administrador ativo: ' . $e->getMessage());
            return false;
        }
    }

    if (function_exists('verificarSessaoConfiada')) {
        try {
            return verificarSessaoConfiada($pdo) && !empty($_SESSION['admin_id']);
        } catch (Throwable $e) {
            error_log('[assinatura] Falha ao restaurar sessão confiada: ' . $e->getMessage());
        }
    }

    return false;
}

function validarCsrfAssinatura(?string $recebido): void
{
    $esperado = (string) ($_SESSION['csrf_token'] ?? '');
    $recebido = (string) $recebido;
    if ($esperado === '' || $recebido === '' || !hash_equals($esperado, $recebido)) {
        throw new AssinaturaWorkflowException(
            'csrf_invalid',
            'A validação de segurança expirou. Recarregue a página e tente novamente.',
            419
        );
    }
}

/**
 * @param array<int,array<string,mixed>> $secretariosAtivos
 * @return array<string,mixed>
 */
function resolverSecretarioAtivoUnico(array $secretariosAtivos): array
{
    $ativos = array_values(array_filter($secretariosAtivos, static function (array $admin): bool {
        return ($admin['nivel'] ?? 'secretario') === 'secretario'
            && (!array_key_exists('ativo', $admin) || (int) $admin['ativo'] === 1);
    }));

    if (count($ativos) === 0) {
        throw new AssinaturaWorkflowException(
            'manual_signer_missing',
            'Não há um secretário ativo cadastrado. Atualize os administradores antes de gerar para assinatura manual.',
            409
        );
    }
    if (count($ativos) > 1) {
        throw new AssinaturaWorkflowException(
            'manual_signer_ambiguous',
            'Há mais de um secretário ativo cadastrado. Mantenha apenas o secretário atual ativo antes de gerar o documento.',
            409
        );
    }

    $secretario = $ativos[0];
    $nome = trim((string) ($secretario['nome_completo'] ?? ''));
    if ($nome === '') {
        $nome = trim((string) ($secretario['nome'] ?? ''));
    }
    if ($nome === '') {
        throw new AssinaturaWorkflowException(
            'manual_signer_invalid',
            'O secretário ativo não possui nome cadastrado.',
            409
        );
    }

    return [
        'id' => (int) ($secretario['id'] ?? 0),
        'nome' => $nome,
        'cargo' => trim((string) ($secretario['cargo'] ?? '')) ?: 'Secretário(a) Municipal de Meio Ambiente',
    ];
}

/** @return array{id:int,nome:string,cargo:string} */
function buscarSecretarioAtivoUnico(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, nome, nome_completo, cargo, nivel, ativo
        FROM administradores WHERE nivel = 'secretario' AND ativo = 1 ORDER BY id");
    return resolverSecretarioAtivoUnico($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Resolve a identidade meramente visual da linha de assinatura manual.
 * O usuário que efetivamente gerou o documento é auditado separadamente.
 *
 * @param array<string,mixed> $adminAtual
 * @param array<string,mixed>|null $secretarioAtivo
 * @return array{id:int,nome:string,cargo:string}
 */
function resolverAssinanteManual(
    string $tipo,
    array $adminAtual,
    ?array $secretarioAtivo = null,
    string $nomePersonalizado = '',
    string $cargoPersonalizado = ''
): array {
    $tipo = trim($tipo);
    if (!in_array($tipo, ['atual', 'secretario', 'personalizado'], true)) {
        throw new AssinaturaWorkflowException(
            'manual_signer_type_invalid',
            'Selecione quem deve aparecer na linha de assinatura manual.',
            422
        );
    }

    if ($tipo === 'secretario') {
        if ($secretarioAtivo === null) {
            throw new AssinaturaWorkflowException(
                'manual_signer_missing',
                'Não foi possível identificar um secretário ativo único.',
                409
            );
        }
        return [
            'id' => (int) ($secretarioAtivo['id'] ?? 0),
            'nome' => (string) ($secretarioAtivo['nome'] ?? ''),
            'cargo' => (string) ($secretarioAtivo['cargo'] ?? ''),
        ];
    }

    if ($tipo === 'atual') {
        $nome = trim((string) ($adminAtual['nome_completo'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($adminAtual['nome'] ?? ''));
        }
        if ($nome === '') {
            throw new AssinaturaWorkflowException(
                'manual_signer_invalid',
                'O usuário atual não possui nome cadastrado.',
                409
            );
        }
        return [
            'id' => (int) ($adminAtual['id'] ?? 0),
            'nome' => $nome,
            'cargo' => trim((string) ($adminAtual['cargo'] ?? '')) ?: 'Servidor(a) Municipal',
        ];
    }

    $normalizar = static function (string $valor): string {
        $valor = strip_tags($valor);
        return trim((string) preg_replace('/\s+/u', ' ', $valor));
    };
    $nome = $normalizar($nomePersonalizado);
    $cargo = $normalizar($cargoPersonalizado);
    $tamanho = static fn(string $valor): int => function_exists('mb_strlen')
        ? mb_strlen($valor, 'UTF-8')
        : strlen($valor);

    if ($nome === '' || $cargo === '') {
        throw new AssinaturaWorkflowException(
            'manual_signer_custom_required',
            'Informe o nome e o cargo da pessoa que assinará o documento.',
            422
        );
    }
    if ($tamanho($nome) > 255 || $tamanho($cargo) > 100) {
        throw new AssinaturaWorkflowException(
            'manual_signer_custom_too_long',
            'O nome ou o cargo informado para a assinatura manual é muito longo.',
            422
        );
    }

    return ['id' => 0, 'nome' => $nome, 'cargo' => $cargo];
}

/** @return array{payload:array<string,mixed>,status:int} */
function respostaErroAssinatura(Throwable $erro, string $contextoLog): array
{
    if (!defined('MODO_TESTE') || MODO_TESTE !== true) {
        error_log($contextoLog . ': ' . $erro->getMessage());
    }

    if ($erro instanceof AssinaturaWorkflowException) {
        return [
            'payload' => [
                'success' => false,
                'code' => $erro->codigoPublico(),
                'error' => $erro->getMessage(),
            ],
            'status' => $erro->httpStatus(),
        ];
    }

    $mensagemTecnica = $erro->getMessage();
    $schemaIncompativel = $erro instanceof PDOException && (
        str_contains($mensagemTecnica, 'Unknown column')
        || str_contains($mensagemTecnica, "doesn't exist")
        || str_contains($mensagemTecnica, 'Data truncated for column')
        || str_contains($mensagemTecnica, 'Incorrect enum value')
    );

    if ($schemaIncompativel) {
        return [
            'payload' => [
                'success' => false,
                'code' => 'schema_incompatible',
                'error' => 'A estrutura do banco precisa ser atualizada antes de concluir esta assinatura.',
            ],
            'status' => 503,
        ];
    }

    return [
        'payload' => [
            'success' => false,
            'code' => 'signature_failed',
            'error' => 'Não foi possível concluir a assinatura. Tente novamente.',
        ],
        'status' => 500,
    ];
}
