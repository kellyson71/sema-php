<?php
require_once __DIR__ . '/config.php';

class AssinaturaDigitalService
{
    private $keysPath;
    private $privateKeyPath;
    private $publicKeyPath;
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->keysPath = dirname(__DIR__) . '/includes/keys/';
        $this->privateKeyPath = $this->keysPath . 'private.pem';
        $this->publicKeyPath = $this->keysPath . 'public.pem';

        if (!is_dir($this->keysPath)) {
            mkdir($this->keysPath, 0700, true);
        }

        if (!file_exists($this->privateKeyPath) || !file_exists($this->publicKeyPath)) {
            $this->gerarChavesRSA();
        }
    }

    private function gerarChavesRSA()
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);

        openssl_pkey_export($res, $privateKey);
        file_put_contents($this->privateKeyPath, $privateKey);
        chmod($this->privateKeyPath, 0600);

        $publicKey = openssl_pkey_get_details($res);
        file_put_contents($this->publicKeyPath, $publicKey['key']);
        chmod($this->publicKeyPath, 0644);
    }

    public function calcularHashDocumento($caminhoArquivo)
    {
        if (!file_exists($caminhoArquivo)) {
            throw new Exception('Arquivo não encontrado para hash');
        }
        return hash_file('sha256', $caminhoArquivo);
    }

    public function assinarHash($hash)
    {
        if (!file_exists($this->privateKeyPath)) {
            throw new Exception('Chave privada não encontrada');
        }

        $privateKey = file_get_contents($this->privateKeyPath);
        $pkeyid = openssl_pkey_get_private($privateKey);

        if ($pkeyid === false) {
            throw new Exception('Erro ao carregar chave privada');
        }

        $success = openssl_sign($hash, $signature, $pkeyid, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new Exception('Erro ao assinar hash');
        }

        return base64_encode($signature);
    }

    public function verificarAssinatura($hash, $assinaturaCriptografada)
    {
        if (!file_exists($this->publicKeyPath)) {
            throw new Exception('Chave pública não encontrada');
        }

        $publicKey = file_get_contents($this->publicKeyPath);
        $pkeyid = openssl_pkey_get_public($publicKey);

        if ($pkeyid === false) {
            throw new Exception('Erro ao carregar chave pública');
        }

        $signature = base64_decode($assinaturaCriptografada);
        $result = openssl_verify($hash, $signature, $pkeyid, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    public function registrarAssinatura($dados)
    {
        $documentoId = bin2hex(random_bytes(32));

        $hash = $this->calcularHashDocumento($dados['caminho_arquivo']);
        $assinaturaCriptografada = $this->assinarHash($hash);

        $metadados = [
            'documento_id' => $documentoId,
            'signer' => $dados['assinante_nome'],
            'nome_completo' => $dados['assinante_nome'],
            'cpf' => $dados['assinante_cpf'] ?? 'N/A',
            'email' => $dados['assinante_email'] ?? 'N/A',
            'role' => $dados['assinante_cargo'] ?? 'Administrador',
            'matricula_portaria' => $dados['assinante_matricula_portaria'] ?? 'N/A',
            'timestamp' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'hash_algorithm' => 'SHA-256',
            'signature_algorithm' => 'RSA-2048',
            'hash' => $hash,
            'signature' => $assinaturaCriptografada,
            'tipo_documento' => $dados['tipo_documento'],
            'requerimento_id' => $dados['requerimento_id']
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO assinaturas_digitais (
                documento_id, requerimento_id, tipo_documento, nome_arquivo,
                caminho_arquivo, hash_documento, assinante_id, assinante_nome,
                assinante_cpf, assinante_cargo, tipo_assinatura, assinatura_visual,
                assinatura_criptografada, timestamp_assinatura, ip_assinante, metadados_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $stmt->execute([
            $documentoId,
            $dados['requerimento_id'],
            $dados['tipo_documento'],
            $dados['nome_arquivo'],
            $dados['caminho_arquivo'],
            $hash,
            $dados['assinante_id'],
            $dados['assinante_nome'],
            $dados['assinante_cpf'] ?? null,
            $dados['assinante_cargo'] ?? 'Administrador',
            $dados['tipo_assinatura'],
            $dados['assinatura_visual'] ?? null,
            $assinaturaCriptografada,
            $_SERVER['REMOTE_ADDR'] ?? null,
            json_encode($metadados, JSON_PRETTY_PRINT)
        ]);

        $jsonPath = dirname($dados['caminho_arquivo']) . '/' .
                    pathinfo($dados['nome_arquivo'], PATHINFO_FILENAME) . '.json';
        file_put_contents($jsonPath, json_encode($metadados, JSON_PRETTY_PRINT));

        return [
            'documento_id' => $documentoId,
            'hash' => $hash,
            'assinatura' => $assinaturaCriptografada,
            'metadados' => $metadados
        ];
    }

    public function verificarDocumento($documentoId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM assinaturas_digitais WHERE documento_id = ?");
        $stmt->execute([$documentoId]);
        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assinatura) {
            return [
                'valido' => false,
                'erro' => 'Documento não encontrado no sistema'
            ];
        }

        if (!file_exists($assinatura['caminho_arquivo'])) {
            return [
                'valido' => false,
                'erro' => 'Arquivo físico não encontrado',
                'dados' => $assinatura
            ];
        }

        $hashAtual = $this->calcularHashDocumento($assinatura['caminho_arquivo']);

        if ($hashAtual !== $assinatura['hash_documento']) {
            return [
                'valido' => false,
                'erro' => 'Documento foi modificado após assinatura (hash divergente)',
                'dados' => $assinatura
            ];
        }

        $assinaturaValida = $this->verificarAssinatura(
            $assinatura['hash_documento'],
            $assinatura['assinatura_criptografada']
        );

        if (!$assinaturaValida) {
            return [
                'valido' => false,
                'erro' => 'Assinatura digital inválida',
                'dados' => $assinatura
            ];
        }

        return [
            'valido' => true,
            'dados' => $assinatura,
            'metadados' => json_decode($assinatura['metadados_json'], true)
        ];
    }
}

