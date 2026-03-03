<?php

require_once '../vendor/autoload.php';
require_once 'TwoFactorService.php';

// Inicia a sessão se necessário para simular banco de dados em persistência via sessão.
session_start();

$service = new TwoFactorService('Painel Demo SEMA');
$emailMock = 'admin@exemplo.com';

// 1. SETUP (Ativação)
if (!isset($_SESSION['demo_secret'])) {
    $setup = $service->generateSetup($emailMock);
    $_SESSION['demo_secret'] = $setup['secret'];
    $qrCodeImage = $service->generateQrCodeBase64($setup['uri']);
    
    echo "<h2>Setup do 2FA</h2>";
    echo "<p>Secret gerada (Salve no banco criptografada!): <strong>{$setup['secret']}</strong></p>";
    echo "<p>Escaneie o QR Code abaixo com seu Autenticador:</p>";
    echo "<img src='{$qrCodeImage}' alt='QR Code 2FA' style='border: 1px solid #ccc; padding: 10px; border-radius: 8px;'>";
    echo "<br><br><a href='2fa-demo.php'>Atualizar página para ir à etapa de Verificação (Login)</a>";
    exit;
}

// 2. VERIFICAÇÃO (Login)
$secret = $_SESSION['demo_secret'];
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoDigitado = $_POST['codigo'] ?? '';
    if ($service->verify($secret, $codigoDigitado)) {
        $mensagem = "<div style='color: green;'>✅ Código válido! Login efetuado com sucesso.</div>";
    } else {
        $mensagem = "<div style='color: red;'>❌ Código inválido! Tente novamente.</div>";
    }
}

// Para debug
$codigoAtual = $service->getCurrentCode($secret);

echo "<h2>Verificação de Login (2FA)</h2>";
echo "<p>Secret recuperada do banco (após descriptografar): <strong>{$secret}</strong></p>";
echo "<p><em>Código atual para debug: {$codigoAtual} (Muda a cada 30s)</em></p>";
echo $mensagem;

echo "<form method='post'>
        <label>Digite o código do aplicativo:</label>
        <input type='text' name='codigo' required maxlength='6'>
        <button type='submit'>Verificar</button>
      </form>";

echo "<br><br><a href='?reset=1'>Resetar Exemplo (Gerar nova secret)</a>";

if (isset($_GET['reset'])) {
    unset($_SESSION['demo_secret']);
    header("Location: 2fa-demo.php");
    exit;
}
