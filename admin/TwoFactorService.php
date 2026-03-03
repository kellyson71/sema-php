<?php

namespace Admin\Services;

require_once __DIR__ . '/../vendor/autoload.php';

use OTPHP\TOTP;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Writer\PngWriter;

class TwoFactorService
{
    /**
     * Chave de criptografia (em produção, use uma variável de ambiente)
     */
    private string $encryptionKey;

    public function __construct(string $encryptionKey = 'CHAVE_SEC_SISTEMA_SEMA_RN_!@#123')
    {
        // Certifique-se de usar uma chave de 32 bytes para AES-256
        $this->encryptionKey = str_pad(substr($encryptionKey, 0, 32), 32, '0');
    }

    /**
     * Gera uma nova secret e o URI para o QR Code
     * 
     * @param string $email Email do usuário (Label)
     * @param string $issuer Nome do emissor (App)
     * @return array contendo 'secret' (criptografada), 'qrCodeUri' e 'plainSecret' (debug)
     */
    public function generateSetup(string $email, string $issuer = 'SEMA Admin'): array
    {
        // Cria um novo TOTP com uma secret aleatória (otphp v11+)
        $totp = TOTP::create();
        $totp->setLabel($email);
        $totp->setIssuer($issuer);

        $secret = $totp->getSecret();
        $encryptedSecret = $this->encryptSecret($secret);

        return [
            'secret' => $encryptedSecret,
            'qrCodeUri' => $totp->getProvisioningUri(),
            'plainSecret' => $secret // Apenas para exibição em caso de falha da leitura, não salve
        ];
    }

    /**
     * Gera a imagem PNG do QR Code em Base64 a partir do URI
     * Suporta endroid/qr-code v4.x
     * 
     * @param string $uri
     * @return string Base64 da imagem PNG
     */
    public function getQrCodeImage(string $uri): string
    {
        // Suprimir notices de deprecation da biblioteca no PHP 8.1+ para não quebrar o JSON
        $result = @Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($uri)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->build();

        return $result->getDataUri();
    }

    /**
     * Valida um código fornecido pelo usuário
     * 
     * @param string $encryptedSecret Secret criptografada tirada do banco
     * @param string $code Código digitado pelo usuário
     * @return bool
     */
    public function verify(string $encryptedSecret, string $code): bool
    {
        if (empty($encryptedSecret) || empty($code)) {
            return false;
        }

        try {
            $secret = $this->decryptSecret($encryptedSecret);
            $totp = TOTP::createFromSecret($secret);
            
            // window (leeway) = 1 (aceitar o código atual, anterior e próximo para tolerar atrasos de relógio até +/- 30s)
            return $totp->verify($code, null, 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pega o código atual para debugging
     * 
     * @param string $encryptedSecret
     * @return string
     */
    public function getCurrentCode(string $encryptedSecret): string
    {
        try {
            $secret = $this->decryptSecret($encryptedSecret);
            $totp = TOTP::createFromSecret($secret);
            return $totp->now();
        } catch (\Exception $e) {
            return 'Erro ao gerar código';
        }
    }

    /**
     * Criptografa a secret antes de salvar no banco utilizando AES-256-CBC
     */
    private function encryptSecret(string $secret): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($secret, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Descriptografa a secret recebida do banco
     */
    private function decryptSecret(string $encryptedSecretBase64): string
    {
        $data = base64_decode($encryptedSecretBase64);
        if (strpos($data, '::') === false) {
            // Suporte legado ou erro
            return $data;
        }
        list($iv, $encrypted) = explode('::', $data, 2);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }
}
