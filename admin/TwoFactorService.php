<?php

class TwoFactorService
{
    private string $issuer;

    public function __construct(string $issuer = 'Painel SEMA')
    {
        $this->issuer = $issuer;
    }

    /**
     * Gera uma nova secret e a URI do QR Code usando OTPHP v11+
     */
    public function generateSetup(string $email): array
    {
        // 1. Cria um novo objeto TOTP
        $totp = \OTPHP\TOTP::create();
        
        // 2. Define o issuer (Nome do App) e o label (E-mail do usuário)
        $totp->setIssuer($this->issuer);
        $totp->setLabel($email);

        return [
            'secret' => $totp->getSecret(),
            'uri'    => $totp->getProvisioningUri()
        ];
    }

    /**
     * Gera o QR Code em base64 a partir da URI do TOTP, usando endroid/qr-code v5+.
     */
    public function generateQrCodeBase64(string $uri): string
    {
        $qrCode = \Endroid\QrCode\QrCode::create($uri)
            ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
            ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::Low)
            ->setSize(250)
            ->setMargin(10)
            ->setRoundBlockSizeMode(\Endroid\QrCode\RoundBlockSizeMode::Margin)
            ->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0))
            ->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);

        // Retorna o base64 para injetar diretamente na tag <img src="...">
        return 'data:image/png;base64,' . base64_encode($result->getString());
    }

    /**
     * Valida o código fornecido pelo usuário.
     * Usa a janela (leeway) para tolerar leves dessincronizações de relógio.
     */
    public function verify(string $secret, string $code, int $leeway = 1): bool
    {
        try {
            $totp = \OTPHP\TOTP::createFromSecret($secret);
            // Verifica o código atual com tolerância de janela (leeway)
            return $totp->verify($code, null, $leeway);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Método auxiliar para debug: Retorna o código atual para a secret fornecida.
     */
    public function getCurrentCode(string $secret): string
    {
        try {
            $totp = \OTPHP\TOTP::createFromSecret($secret);
            return $totp->now();
        } catch (\Exception $e) {
            return '';
        }
    }
}
