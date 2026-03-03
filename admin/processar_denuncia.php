<?php
require_once 'conexao.php';
verificaLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: denuncias.php");
    exit;
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'cadastrar') {
    $infrator_nome = trim($_POST['infrator_nome'] ?? '');
    $infrator_cpf_cnpj = trim($_POST['infrator_cpf_cnpj'] ?? '');
    $infrator_endereco = trim($_POST['infrator_endereco'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $admin_id = $_SESSION['admin_id'];

    if (empty($infrator_nome) || empty($observacoes)) {
        header("Location: nova_denuncia.php?error=vazio");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO denuncias (infrator_nome, infrator_cpf_cnpj, infrator_endereco, observacoes, admin_id) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$infrator_nome, $infrator_cpf_cnpj, $infrator_endereco, $observacoes, $admin_id]);
        
        $denunciaId = $pdo->lastInsertId();

        // Processar uploads
        if (!empty($_FILES['anexos']['name'][0])) {
            $uploadDir = '../uploads/denuncias/' . date('Y/m/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $arquivos = $_FILES['anexos'];
            for ($i = 0; $i < count($arquivos['name']); $i++) {
                if ($arquivos['error'][$i] === UPLOAD_ERR_OK) {
                    $nomeOriginal = basename($arquivos['name'][$i]);
                    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
                    
                    // Tipos permitidos
                    $tiposPermitidos = ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov'];
                    if (!in_array($extensao, $tiposPermitidos)) continue;
                    
                    $nomeSeguro = md5(uniqid(time())) . '.' . $extensao;
                    $caminhoFisico = $uploadDir . $nomeSeguro;
                    $caminhoDB = 'uploads/denuncias/' . date('Y/m/') . $nomeSeguro;

                    if (move_uploaded_file($arquivos['tmp_name'][$i], $caminhoFisico)) {
                        $sqlAnexo = "INSERT INTO denuncia_anexos (denuncia_id, nome_arquivo, caminho_arquivo, tipo_arquivo) VALUES (?, ?, ?, ?)";
                        $stmtAnexo = $pdo->prepare($sqlAnexo);
                        $stmtAnexo->execute([$denunciaId, $nomeOriginal, $caminhoDB, $extensao]);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: denuncias.php?success=registrada");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: denuncias.php?error=criacao");
        exit;
    }
} elseif ($acao === 'alterar_status') {
    $denunciaId = (int)$_POST['id'];
    $novoStatus = $_POST['status'];
    
    $statusValidos = ['Pendente', 'Em Análise', 'Concluída'];
    
    if (in_array($novoStatus, $statusValidos)) {
        $stmt = $pdo->prepare("UPDATE denuncias SET status = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $denunciaId]);
        header("Location: visualizar_denuncia.php?id=$denunciaId&success=atualizada");
    } else {
        header("Location: visualizar_denuncia.php?id=$denunciaId&error=invalido");
    }
    exit;
}

header("Location: denuncias.php");
exit;
