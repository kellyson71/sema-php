<?php

$data = json_decode($_POST['json'], true);

try {
    // Configura conexão com DB primeiro
    define('DBHOST', 'srv1844.hstgr.io');
    define('DBNAME', 'u492577848_estagiarios');
    define('USER', 'u492577848_pmpfestagio');
    define('DBPASSWORD', 'Kellys0n_123');

    $pdo = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBNAME . ";charset=utf8mb4", USER, DBPASSWORD);

    // Verifica se CPF já existe
    $stmt = $pdo->prepare("SELECT cpf FROM Candidato WHERE cpf = ?");
    $stmt->execute([$data['cpf']]);

    if ($stmt->rowCount() > 0) {
        throw new Exception("CPF já cadastrado no sistema. Cada candidato pode se inscrever apenas uma vez.");
    }

    // Remove caracteres especiais do CPF para usar como nome da pasta
    $cpf_limpo = preg_replace('/[^0-9]/', '', $data['cpf']);
    $dst = '../media/' . $cpf_limpo . '/';

    // Verifica se o diretório já existe
    if (!is_dir($dst)) {
        // Tenta criar o diretório
        if (!mkdir($dst, 0755, true)) {
            throw new Exception("Não foi possível criar o diretório para os arquivos");
        }
    }

    // Verifica se o diretório é gravável
    if (!is_writable($dst)) {
        throw new Exception("O diretório não tem permissão de escrita");
    }

    $upload_success = true;
    foreach ($_FILES as $f) {
        $filename = $f['name'];
        if (!move_uploaded_file($f['tmp_name'], $dst . $filename)) {
            $upload_success = false;
            throw new Exception("Falha ao mover arquivo: " . $filename);
        }
    }

    if (!$upload_success) {
        throw new Exception("Houve um erro ao fazer upload dos arquivos");
    }

    $insert = $pdo->prepare("INSERT INTO Candidato (
        nome_completo,
        email,
        cpf,
        telefone,
        universidade,
        area,
        turno_estudo,
        ano_ingresso,
        ira,
        horario_disponivel,
        graduado,
        deficiente
    ) VALUES (
        '" . $data['nome_completo'] . "',
        '" . $data['email'] . "',
        '" . $data['cpf'] . "',
        '" . $data['telefone'] . "',
        '" . $data['universidade'] . "',
        '" . $data['area'] . "',
        '" . $data['turno_estudo'] . "',
        '" . $data['ano_ingresso'] . "',
        '" . $data['ira'] . "',
        '" . $data['horario_disponivel'] . "',
        '" . $data['graduado'] . "',
        '" . $data['deficiente'] . "'
        )");

    $insert->execute();

    $insert = null;
    $pdo = null;

    echo json_encode(array("message" => "Cadastro bem sucedido"));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(array(
        "message" => $e->getMessage(),
        "error" => true,
        "duplicate" => strpos($e->getMessage(), "CPF já cadastrado") !== false
    ));
    exit(0);
}

?>