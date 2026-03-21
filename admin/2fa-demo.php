<?php
// admin/2fa-demo.php
require_once '../vendor/autoload.php';
require_once 'TwoFactorService.php';

use Admin\Services\TwoFactorService;

// Configuração inicial para exibição de erros no demo
ini_set('display_errors', 1);
error_reporting(E_ALL);

$twoFactorService = new TwoFactorService();

session_start();

$step = $_GET['step'] ?? 'setup';

echo '<div style="font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 30px; border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
echo '<h2 style="color: #009851; text-align: center;">Demonstração TOTP 2FA</h2>';

if ($step === 'setup') {
    // 1. Gera o setup
    $setupData = $twoFactorService->generateSetup('admin-demo@sema.rn.gov.br');
    
    // 2. Salva a secret criptografada "no banco" (usaremos a sessão como mock)
    $_SESSION['mock_db_secret'] = $setupData['secret'];
    
    // 3. Gera a imagem do QR Code
    $qrCodeBase64 = $twoFactorService->getQrCodeImage($setupData['qrCodeUri']);
    
    echo '<div style="text-align: center;">';
    echo '<h3>1. Escaneie o QR Code</h3>';
    echo '<p style="color: #555;">Abra seu aplicativo autenticador (Google Authenticator, Microsoft Authenticator, Authy, etc) e escaneie a imagem abaixo:</p>';
    echo '<img src="' . $qrCodeBase64 . '" alt="QR Code TOTP" style="border: 2px solid #009851; padding: 10px; border-radius: 10px; max-width: 250px;">';
    
    echo '<p style="font-size: 14px; margin-top: 20px;">Secret gerada nativa (apenas debug): <br><code style="background: #f4f4f4; padding: 3px 6px; border-radius: 4px;">' . $setupData['plainSecret'] . '</code></p>';
    echo '<p style="font-size: 12px; color: #888; margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #009851;">Secret criptografada p/ o Banco: <br><code style="word-break: break-all;">' . $setupData['secret'] . '</code></p>';
    echo '</div>';
    
    echo '<br><hr style="border-top: 1px solid #eee;"><br>';
    echo '<div style="text-align: center;">';
    echo '<h3>2. Digite o código gerado no App</h3>';
    echo '<form action="?step=verify" method="post">';
    echo '<input type="text" name="code" placeholder="000 000" maxlength="6" style="font-size: 28px; padding: 10px; width: 180px; text-align: center; letter-spacing: 5px; border: 2px solid #ccc; border-radius: 8px;">';
    echo '<br><br><button type="submit" style="padding: 12px 24px; font-size: 16px; cursor: pointer; background: #009851; color: white; border: none; border-radius: 8px; font-weight: bold; transition: all 0.3s ease;">Verificar Código</button>';
    echo '</form>';
    echo '</div>';
    
    echo '<div style="margin-top: 40px; padding: 15px; background: #fff3cd; color: #856404; border-radius: 8px; font-size: 14px;">';
    echo '<strong>Dica de Segurança:</strong> Em produção, forneça "códigos de recuperação" ao usuário para que ele possa entrar caso perca o celular.';
    echo '</div>';

} 
elseif ($step === 'verify') {
    $code = str_replace(' ', '', $_POST['code'] ?? ''); // limpa espaços
    $encryptedSecret = $_SESSION['mock_db_secret'] ?? '';
    
    echo '<div style="text-align: center;">';
    echo '<h3>Resultado da Verificação</h3>';
    echo '<p style="font-size: 18px;">Código recebido: <strong style="letter-spacing: 2px;">' . htmlspecialchars($code) . '</strong></p>';
    
    // Recuperar o código atual exato (para fins de debug)
    $currentCode = $twoFactorService->getCurrentCode($encryptedSecret);
    echo '<p style="font-size: 14px; color: #666;">Código válido no servidor neste momento: <strong style="letter-spacing: 2px;">' . $currentCode . '</strong></p>';
    
    // Verificar se é válido
    $isValid = $twoFactorService->verify($encryptedSecret, $code);
    
    if ($isValid) {
        echo '<div style="background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 5px solid #28a745;">
                <strong style="font-size: 20px;">✓ Sucesso!</strong><br>O código TOTP foi validado e está correto!
              </div>';
    } else {
        echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 5px solid #dc3545;">
                <strong style="font-size: 20px;">✗ Erro!</strong><br>O código TOTP é inválido ou expirou. Tente novamente!
              </div>';
    }
    
    echo '<br><br><a href="?step=setup" style="display: inline-block; padding: 10px 20px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 5px; font-weight: bold;">← Voltar ao Início</a>';
    echo '</div>';
}

echo '</div>';
