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

    /**
     * Resolve o caminho físico do PDF. Os registros guardam caminhos relativos
     * à pasta admin/ (ex: 'pareceres/12/arquivo.pdf'); este resolvedor aceita
     * também caminhos absolutos e relativos à raiz do projeto, para que a
     * verificação funcione independente de onde o script foi chamado.
     */
    public function resolverCaminhoArquivo(string $caminho): ?string
    {
        if ($caminho === '') return null;
        $raiz = dirname(__DIR__);
        $candidatos = [
            $caminho,                                       // absoluto ou relativo ao cwd
            $raiz . '/admin/' . ltrim($caminho, '/'),       // relativo a admin/ (padrão)
            $raiz . '/' . ltrim($caminho, '/'),             // relativo à raiz
        ];
        foreach ($candidatos as $c) {
            if (is_file($c)) return $c;
        }
        return null;
    }

    /**
     * Extrai o documento_id embutido nos metadados do PDF (Keywords
     * 'SEMA-DOC-ID:<hex>'). Best-effort: o TCPDF grava o dicionário Info de
     * forma legível, mas um re-save externo pode remover/reescrever — nesse
     * caso retorna null e o documento cai no estado DESCONHECIDO.
     */
    public function extrairDocumentoIdDeArquivo(string $caminho): ?string
    {
        if (!is_file($caminho)) return null;
        $bytes = file_get_contents($caminho);
        if ($bytes === false) return null;
        if (preg_match('/SEMA-DOC-ID:([a-f0-9]{16,64})/', $bytes, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Verifica a autenticidade a partir do ARQUIVO enviado (upload público).
     * Retorna um dos estados: 'autentico', 'alterado', 'desconhecido'.
     * Não armazena o arquivo — apenas registra um log da verificação.
     *
     * @param string $caminhoTmp caminho temporário do upload ($_FILES[...]['tmp_name'])
     */
    public function verificarPorArquivo(string $caminhoTmp): array
    {
        if (!is_file($caminhoTmp)) {
            return ['estado' => 'desconhecido', 'erro' => 'Arquivo não recebido.'];
        }

        $hashEnviado = hash_file('sha256', $caminhoTmp);

        // 1. Match exato por hash do PDF → documento íntegro e autêntico
        $stmt = $this->pdo->prepare("
            SELECT documento_id FROM assinaturas_digitais
            WHERE hash_documento = ?
            ORDER BY timestamp_assinatura DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$hashEnviado]);
        $docId = $stmt->fetchColumn();

        if ($docId) {
            $res = $this->verificarDocumento($docId);
            $res['estado'] = !empty($res['valido']) ? 'autentico' : 'alterado';
            $this->registrarVerificacao($docId, $hashEnviado, $res['estado'], 'upload');
            return $res;
        }

        // 2. Hash não bateu → tenta o ID embutido nos metadados
        $idEmbutido = $this->extrairDocumentoIdDeArquivo($caminhoTmp);
        if ($idEmbutido) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM assinaturas_digitais
                WHERE documento_id = ?
                ORDER BY timestamp_assinatura ASC, id ASC
                LIMIT 1
            ");
            $stmt->execute([$idEmbutido]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registro) {
                // Documento é da SEMA, mas o arquivo não corresponde ao oficial
                $this->registrarVerificacao($idEmbutido, $hashEnviado, 'alterado', 'upload');
                return [
                    'valido'      => false,
                    'estado'      => 'alterado',
                    'erro'        => 'Este documento foi emitido pelo SEMA, mas o arquivo enviado foi alterado após a assinatura (não corresponde à versão oficial registrada).',
                    'dados'       => $registro,
                ];
            }
        }

        // 3. Nada reconhecido
        $this->registrarVerificacao(null, $hashEnviado, 'desconhecido', 'upload');
        return [
            'valido' => false,
            'estado' => 'desconhecido',
            'erro'   => 'Este arquivo não foi emitido pelo SEMA ou não pôde ser reconhecido.',
        ];
    }

    /**
     * Registra a verificação para auditoria (sem armazenar o arquivo).
     * Silencioso: falha de log nunca deve quebrar a verificação.
     */
    public function registrarVerificacao(?string $documentoId, ?string $hashEnviado, string $resultado, string $metodo = 'upload'): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO verificacoes_log (documento_id, hash_enviado, metodo, resultado, ip_origem)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $documentoId,
                $hashEnviado,
                $metodo,
                $resultado,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[verificacoes_log] ' . $e->getMessage());
        }
    }

    /**
     * Verifica um documento e TODAS as suas assinaturas.
     *
     * Documentos novos (nivel 'avancada'): cada assinante tem RSA própria sobre
     * o hash do conteúdo-fonte, verificada com a chave pública snapshot.
     * Documentos legados (sem chave_publica): valida-se apenas o registro e a
     * integridade do PDF — exibidos como "registro eletrônico simples".
     */
    public function verificarDocumento($documentoId)
    {
        require_once __DIR__ . '/assinatura_avancada_service.php';

        $stmt = $this->pdo->prepare("
            SELECT * FROM assinaturas_digitais
            WHERE documento_id = ?
            ORDER BY timestamp_assinatura ASC, id ASC
        ");
        $stmt->execute([$documentoId]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$linhas) {
            return ['valido' => false, 'erro' => 'Documento não encontrado no sistema'];
        }

        $principal = $linhas[0];

        $caminhoFisico = $this->resolverCaminhoArquivo($principal['caminho_arquivo']);
        if ($caminhoFisico === null) {
            return [
                'valido' => false,
                'erro'   => 'Arquivo físico não encontrado no servidor',
                'dados'  => $principal,
            ];
        }

        // Integridade do PDF: o hash mais recente reflete a última regravação
        // legítima (co-assinatura atualiza todas as linhas).
        $hashAtual = $this->calcularHashDocumento($caminhoFisico);
        $hashEsperado = end($linhas)['hash_documento'];

        if ($hashAtual !== $hashEsperado) {
            return [
                'valido' => false,
                'erro'   => 'Documento foi modificado após a assinatura (hash divergente)',
                'dados'  => $principal,
            ];
        }

        // Verificação criptográfica por assinante
        $assinantes = [];
        $todasValidas = true;
        foreach ($linhas as $linha) {
            if ($linha['tipo_assinatura'] === 'sem_assinatura') {
                continue; // linha de geração sem assinatura não conta como assinante
            }
            $temRsa = !empty($linha['chave_publica']) && !empty($linha['hash_conteudo']);
            $rsaOk  = $temRsa && AssinaturaAvancadaService::verificar(
                $linha['hash_conteudo'],
                $linha['assinatura_criptografada'],
                $linha['chave_publica']
            );
            if ($temRsa && !$rsaOk) {
                $todasValidas = false;
            }
            $assinantes[] = [
                'nome'      => $linha['assinante_nome'],
                'cpf'       => $linha['assinante_cpf'],
                'cargo'     => $linha['assinante_cargo'],
                'data'      => $linha['timestamp_assinatura'],
                'nivel'     => $linha['nivel_assinatura'] ?? ($temRsa ? 'avancada' : 'simples'),
                'rsa_valida' => $temRsa ? $rsaOk : null,
            ];
        }

        if (!$todasValidas) {
            return [
                'valido' => false,
                'erro'   => 'Uma ou mais assinaturas eletrônicas falharam na verificação criptográfica',
                'dados'  => $principal,
                'assinantes' => $assinantes,
            ];
        }

        return [
            'valido'         => true,
            'dados'          => $principal,
            'assinantes'     => $assinantes,
            'caminho_fisico' => $caminhoFisico,
            'metadados'      => !empty($principal['metadados_json']) ? json_decode($principal['metadados_json'], true) : null,
        ];
    }
}

