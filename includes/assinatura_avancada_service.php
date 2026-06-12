<?php
/**
 * Assinatura Eletrônica Avançada — Lei 14.063/2020, art. 4º, II.
 *
 * Cada administrador possui um par RSA-2048 exclusivo. A chave privada é
 * cifrada com AES-256-GCM cuja chave é derivada do PIN de assinatura via
 * PBKDF2 (120k iterações). O servidor armazena apenas o blob cifrado:
 * sem o PIN — que só o admin conhece — a chave privada é inutilizável.
 * Isso atende o requisito legal de "dados de criação de assinatura sob
 * controle exclusivo do signatário".
 *
 * O que é assinado: o SHA-256 do HTML-fonte canônico do documento
 * (hash_conteudo), e não o hash do PDF. Motivo: a co-assinatura regrava o
 * PDF para acrescentar o bloco visual do novo signatário, o que mudaria o
 * hash do arquivo e invalidaria as assinaturas anteriores. O conteúdo-fonte
 * é imutável, então cada assinatura RSA permanece verificável para sempre.
 * A integridade do PDF físico é garantida separadamente pelo hash_documento.
 */

class AssinaturaAvancadaService
{
    private const PBKDF2_ITERACOES = 120000;
    private const PIN_MIN_LEN = 6;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Retorna true se o admin já configurou sua chave de assinatura. */
    public function temChave(int $adminId): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM admin_chaves_assinatura WHERE admin_id = ?");
        $st->execute([$adminId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Cria (ou recria) o par de chaves do admin, protegido pelo PIN.
     * Recriar invalida o uso futuro da chave antiga, mas assinaturas já
     * feitas continuam verificáveis pelo snapshot salvo em assinaturas_digitais.
     */
    public function criarChave(int $adminId, string $pin): void
    {
        $pin = trim($pin);
        if (strlen($pin) < self::PIN_MIN_LEN) {
            throw new InvalidArgumentException('O PIN de assinatura deve ter no mínimo ' . self::PIN_MIN_LEN . ' caracteres.');
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            throw new RuntimeException('Falha ao gerar par de chaves RSA.');
        }

        openssl_pkey_export($res, $privadaPem);
        $publicaPem = openssl_pkey_get_details($res)['key'];

        $salt = bin2hex(random_bytes(16));
        $blob = $this->cifrarPrivada($privadaPem, $pin, $salt);

        // Chave em claro sai da memória o quanto antes
        unset($privadaPem);

        $st = $this->pdo->prepare("
            INSERT INTO admin_chaves_assinatura (admin_id, chave_publica, chave_privada_cifrada, salt, pin_hash)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                chave_publica = VALUES(chave_publica),
                chave_privada_cifrada = VALUES(chave_privada_cifrada),
                salt = VALUES(salt),
                pin_hash = VALUES(pin_hash)
        ");
        $st->execute([$adminId, $publicaPem, $blob, $salt, password_hash($pin, PASSWORD_DEFAULT)]);
    }

    /** Valida o PIN sem decifrar a chave (feedback rápido para a UI). */
    public function validarPin(int $adminId, string $pin): bool
    {
        $st = $this->pdo->prepare("SELECT pin_hash FROM admin_chaves_assinatura WHERE admin_id = ?");
        $st->execute([$adminId]);
        $hash = $st->fetchColumn();
        return $hash !== false && password_verify(trim($pin), $hash);
    }

    /**
     * Assina dados (normalmente o hash_conteudo) com a chave privada do admin.
     *
     * @return array{assinatura: string, chave_publica: string} assinatura em base64
     * @throws RuntimeException se PIN incorreto ou chave inexistente
     */
    public function assinar(int $adminId, string $pin, string $dados): array
    {
        $st = $this->pdo->prepare("SELECT * FROM admin_chaves_assinatura WHERE admin_id = ?");
        $st->execute([$adminId]);
        $registro = $st->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new RuntimeException('PIN_SETUP_REQUIRED');
        }
        if (!password_verify(trim($pin), $registro['pin_hash'])) {
            throw new RuntimeException('PIN_INCORRETO');
        }

        $privadaPem = $this->decifrarPrivada($registro['chave_privada_cifrada'], trim($pin), $registro['salt']);
        $pkey = openssl_pkey_get_private($privadaPem);
        unset($privadaPem);

        if ($pkey === false) {
            throw new RuntimeException('Falha ao carregar chave privada decifrada.');
        }

        if (!openssl_sign($dados, $assinatura, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falha na operação de assinatura RSA.');
        }

        return [
            'assinatura'    => base64_encode($assinatura),
            'chave_publica' => $registro['chave_publica'],
        ];
    }

    /** Verifica uma assinatura contra a chave pública snapshot (PEM). */
    public static function verificar(string $dados, string $assinaturaB64, string $chavePublicaPem): bool
    {
        $pkey = openssl_pkey_get_public($chavePublicaPem);
        if ($pkey === false) {
            return false;
        }
        $sig = base64_decode($assinaturaB64, true);
        if ($sig === false) {
            return false;
        }
        return openssl_verify($dados, $sig, $pkey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Hash canônico do conteúdo-fonte. Centralizado aqui para que assinatura
     * e verificação usem SEMPRE a mesma normalização.
     */
    public static function hashConteudo(string $conteudoHtml): string
    {
        return hash('sha256', trim($conteudoHtml));
    }

    // ── Criptografia interna ────────────────────────────────────────────

    private function cifrarPrivada(string $privadaPem, string $pin, string $saltHex): string
    {
        $key = hash_pbkdf2('sha256', $pin, hex2bin($saltHex), self::PBKDF2_ITERACOES, 32, true);
        $iv  = random_bytes(12);
        $ct  = openssl_encrypt($privadaPem, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new RuntimeException('Falha ao cifrar chave privada.');
        }
        return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ct);
    }

    private function decifrarPrivada(string $blob, string $pin, string $saltHex): string
    {
        $partes = explode(':', $blob);
        if (count($partes) !== 3) {
            throw new RuntimeException('Blob de chave cifrada em formato inválido.');
        }
        [$ivB64, $tagB64, $ctB64] = $partes;
        $key = hash_pbkdf2('sha256', $pin, hex2bin($saltHex), self::PBKDF2_ITERACOES, 32, true);
        $pem = openssl_decrypt(base64_decode($ctB64), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($ivB64), base64_decode($tagB64));
        if ($pem === false) {
            // GCM falha na autenticação se PIN errado — mas o pin_hash já barrou antes;
            // chegar aqui indica blob corrompido
            throw new RuntimeException('Falha ao decifrar chave privada (dados corrompidos?).');
        }
        return $pem;
    }
}
