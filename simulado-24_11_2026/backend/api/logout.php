<?php
require __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_erro('Método não permitido.', 405);
}

session_destroy();

responder(['mensagem' => 'Sessão encerrada.']);
