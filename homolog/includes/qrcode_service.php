<?php
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    throw new Exception('Arquivo vendor/autoload.php não encontrado. Execute: composer install');
}
require_once $autoloadPath;

if (!class_exists('Endroid\QrCode\Builder\Builder')) {
    throw new Exception('Biblioteca endroid/qr-code não encontrada. Execute: composer require endroid/qr-code:^5.0');
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeService
{
    public static function gerarQRCode($url, $tamanho = 200)
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($tamanho)
            ->margin(10)
            ->build();

        return $result->getDataUri();
    }
}

