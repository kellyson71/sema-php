<?php
// Inclui o arquivo com os tipos de alvará
include_once 'tipos_alvara.php';

// Verifica se o tipo de alvará foi enviado
if (isset($_POST['tipo'])) {
    $tipo = $_POST['tipo'];

    // Exibe os documentos para o tipo de alvará selecionado
    echo exibirDocumentos($tipo);
} else {
    echo '<div class="aviso">Selecione um tipo de alvará para ver os documentos necessários.</div>';
}
