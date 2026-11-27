<?php
require __DIR__ . '/../helpers.php';

exigir_login();

responder([
    'id_usuario' => $_SESSION['id_usuario'],
    'nome' => $_SESSION['nome_usuario'],
]);
