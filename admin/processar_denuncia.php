<?php
require_once 'conexao.php';
verificaLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['acao'])) {
    header("Location: denuncias.php");
    exit;
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// Função auxiliar para registrar histórico de denúncia
function registrarHistoricoDenuncia($pdo, $denunciaId, $acao, $detalhes = null) {
    $adminId = $_SESSION['admin_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO denuncia_historico (denuncia_id, admin_id, acao, detalhes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$denunciaId, $adminId, $acao, $detalhes]);
}


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

        $sql = "INSERT INTO denuncias (infrator_nome, infrator_cpf_cnpj, infrator_endereco, observacoes, admin_id, origem)
                VALUES (?, ?, ?, ?, ?, 'admin')";
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

        // Registrar Histórico
        registrarHistoricoDenuncia($pdo, $denunciaId, 'Criação', 'Denúncia registrada no sistema.');

        $pdo->commit();
        
        header("Location: denuncias.php?success=registrada");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao salvar denúncia: " . $e->getMessage());
        header("Location: denuncias.php?error=criacao");
        exit;
    }
} elseif ($acao === 'editar') {
    $denunciaId = (int)$_POST['id'];
    $infrator_nome    = trim($_POST['infrator_nome'] ?? '');
    $infrator_cpf_cnpj = trim($_POST['infrator_cpf_cnpj'] ?? '');
    $infrator_endereco = trim($_POST['infrator_endereco'] ?? '');
    $observacoes      = trim($_POST['observacoes'] ?? '');

    if (empty($infrator_nome) || empty($observacoes)) {
        header("Location: visualizar_denuncia.php?id=$denunciaId&error=vazio");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE denuncias SET infrator_nome=?, infrator_cpf_cnpj=?, infrator_endereco=?, observacoes=? WHERE id=?");
    $stmt->execute([$infrator_nome, $infrator_cpf_cnpj, $infrator_endereco, $observacoes, $denunciaId]);

    registrarHistoricoDenuncia($pdo, $denunciaId, 'Edição', 'Dados da denúncia atualizados.');

    header("Location: visualizar_denuncia.php?id=$denunciaId&success=editada");
    exit;
} elseif ($acao === 'alterar_status') {
    $denunciaId = (int)$_POST['id'];
    $novoStatus = $_POST['status'];
    $medidas    = trim($_POST['detalhes'] ?? '');
    // Padrão: a medida escrita pelo fiscal é visível ao denunciante (é o objetivo).
    // A caixa vem marcada; desmarcar deixa o registro só interno.
    $visivel    = isset($_POST['visivel_denunciante']) ? 1 : 0;

    $statusValidos = ['Pendente', 'Em Análise', 'Concluída'];

    if (in_array($novoStatus, $statusValidos)) {
        $stmt = $pdo->prepare("UPDATE denuncias SET status = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $denunciaId]);

        // O texto das medidas vai no histórico; visivel_denunciante decide se aparece
        // no acompanhamento público. Sem texto, registra apenas a troca de status.
        $detalhes = $medidas !== '' ? $medidas : "Status alterado para: $novoStatus";
        $stmt = $pdo->prepare("INSERT INTO denuncia_historico (denuncia_id, admin_id, acao, detalhes, visivel_denunciante) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$denunciaId, $_SESSION['admin_id'] ?? null, 'Alteração de Status', $detalhes, $visivel]);

        header("Location: visualizar_denuncia.php?id=$denunciaId&success=atualizada");
    } else {
        header("Location: visualizar_denuncia.php?id=$denunciaId&error=invalido");
    }
    exit;
} elseif ($acao === 'adicionar_anexo') {
    // Upload de arquivos pela fiscalização (fotos da vistoria, documentos).
    // Cada envio carrega uma marcação de visibilidade ao denunciante.
    $denunciaId = (int)$_POST['id'];
    $visivel    = isset($_POST['visivel_denunciante']) ? 1 : 0;
    $descricao  = trim($_POST['descricao'] ?? '') ?: null;
    $adminId    = $_SESSION['admin_id'] ?? null;

    $tiposPermitidos = ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov'];
    $maxBytes        = 10 * 1024 * 1024;
    $enviados        = 0;

    if (!empty($_FILES['anexos']['name'][0])) {
        $uploadDirRel = 'uploads/denuncias/fiscal/' . $denunciaId . '/';
        $uploadDir    = '../' . $uploadDirRel;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $arquivos = $_FILES['anexos'];
        for ($i = 0; $i < count($arquivos['name']); $i++) {
            if ($arquivos['error'][$i] !== UPLOAD_ERR_OK) continue;
            $nomeOriginal = basename($arquivos['name'][$i]);
            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, $tiposPermitidos)) continue;
            if ($arquivos['size'][$i] > $maxBytes) continue;

            $nomeSeguro    = md5(uniqid((string)$i, true)) . '.' . $ext;
            $caminhoFisico = $uploadDir . $nomeSeguro;
            $caminhoDB     = $uploadDirRel . $nomeSeguro;

            if (move_uploaded_file($arquivos['tmp_name'][$i], $caminhoFisico)) {
                $pdo->prepare("
                    INSERT INTO denuncia_anexos
                        (denuncia_id, origem, admin_id, nome_arquivo, caminho_arquivo, tipo_arquivo, visivel_denunciante, descricao)
                    VALUES (?, 'fiscal', ?, ?, ?, ?, ?, ?)
                ")->execute([$denunciaId, $adminId, $nomeOriginal, $caminhoDB, $ext, $visivel, $descricao]);
                $enviados++;
            }
        }
    }

    if ($enviados > 0) {
        registrarHistoricoDenuncia($pdo, $denunciaId, 'Anexo adicionado',
            $enviados . ' arquivo(s) anexado(s) pela fiscalização' . ($visivel ? ' — visível ao denunciante' : ' — interno'));
    }
    header("Location: visualizar_denuncia.php?id=$denunciaId&success=anexo#anexos");
    exit;
} elseif ($acao === 'toggle_anexo_visivel') {
    // Alterna a visibilidade de um anexo ao denunciante.
    $denunciaId = (int)$_POST['id'];
    $anexoId    = (int)$_POST['anexo_id'];
    $pdo->prepare("UPDATE denuncia_anexos SET visivel_denunciante = 1 - visivel_denunciante WHERE id = ? AND denuncia_id = ?")
        ->execute([$anexoId, $denunciaId]);
    header("Location: visualizar_denuncia.php?id=$denunciaId&success=anexo#anexos");
    exit;
} elseif ($acao === 'excluir') {
    $denunciaId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($denunciaId > 0) {
        // Buscar anexos para deletar arquivos físicos antes de apagar do banco
        $stmt = $pdo->prepare("SELECT caminho_arquivo FROM denuncia_anexos WHERE denuncia_id = ?");
        $stmt->execute([$denunciaId]);
        $anexos = $stmt->fetchAll();
        
        foreach ($anexos as $anexo) {
            $caminhoCompleto = '../' . $anexo['caminho_arquivo'];
            if (file_exists($caminhoCompleto)) {
                unlink($caminhoCompleto);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM denuncias WHERE id = ?");
        $stmt->execute([$denunciaId]);
        header("Location: denuncias.php?success=excluida");
    } else {
        header("Location: denuncias.php?error=permissao");
    }
    exit;
}

header("Location: denuncias.php");
exit;
