<?php
/**
 * AJAX — gestão da chave de assinatura avançada do admin logado.
 * Ações: status | criar | validar_pin
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once $rootDir . '/includes/assinatura_avancada_service.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

header('Content-Type: application/json');

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if (!$adminId) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$servico = new AssinaturaAvancadaService($pdo);

try {
    switch ($acao) {
        case 'status':
            echo json_encode([
                'success'   => true,
                'tem_chave' => $servico->temChave($adminId),
            ]);
            break;

        case 'criar':
            $pin  = $_POST['pin'] ?? '';
            $pin2 = $_POST['pin_confirmacao'] ?? '';
            if ($pin !== $pin2) {
                echo json_encode(['success' => false, 'error' => 'Os PINs informados não coincidem.']);
                break;
            }
            $jaTemChave = $servico->temChave($adminId);
            // Recriar chave exige confirmação explícita — invalida o PIN anterior
            if ($jaTemChave && ($_POST['confirmar_recriacao'] ?? '') !== '1') {
                echo json_encode(['success' => false, 'error' => 'Você já possui uma chave. Envie confirmar_recriacao=1 para substituí-la.']);
                break;
            }
            // Redefinição (já existe chave) exige a senha de login como dupla
            // checagem — impede que uma sessão sequestrada troque o PIN à toa.
            if ($jaTemChave) {
                $senhaLogin = $_POST['senha_login'] ?? '';
                $st = $pdo->prepare("SELECT senha FROM administradores WHERE id = ?");
                $st->execute([$adminId]);
                $hashSenha = $st->fetchColumn();
                if (!$hashSenha || !password_verify($senhaLogin, $hashSenha)) {
                    echo json_encode(['success' => false, 'error' => 'Senha de login incorreta.']);
                    break;
                }
            }
            $servico->criarChave($adminId, $pin);

            $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, NULL, ?)")
                ->execute([$adminId, 'Configurou chave de assinatura eletrônica avançada (RSA-2048)']);

            echo json_encode(['success' => true]);
            break;

        case 'validar_pin':
            echo json_encode([
                'success' => true,
                'valido'  => $servico->validarPin($adminId, $_POST['pin'] ?? ''),
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
    }
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[chave_handler] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno ao processar a chave de assinatura.']);
}
