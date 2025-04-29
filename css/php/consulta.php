<?php

define('DBHOST', 'srv1844.hstgr.io');
define('DBNAME', 'u492577848_estagiarios');
define('USER', 'u492577848_pmpfestagio');
define('DBPASSWORD', 'Kellys0n_123');

$pdo = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBNAME . ";charset=utf8mb4", USER, DBPASSWORD);

try {

    $get = $pdo->query("SELECT * FROM `Candidato` WHERE cpf = '" . $_GET['cpf'] . "'");

    $data = $get->fetch();

    $message = '';

    if ($data === false) {
        $message = 'CPF não cadastrado';
    } else {
        $message = 'CPF cadastrado';
    }

    echo json_encode(array('message' => $message));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array('message' => $e->getMessage()));
}