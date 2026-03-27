<?php
$id = filter_input(INPUT_GET, 'requerimento_id', FILTER_VALIDATE_INT);
if ($id) {
    header('Location: documentos/selecionar.php?requerimento_id=' . $id);
} else {
    header('Location: requerimentos.php');
}
exit;
