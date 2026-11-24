<?php
require __DIR__ . '/config.php';

// Tempo máximo sem usar o sistema: 30 minutos
const TEMPO_SESSAO = 30 * 60;

ini_set('session.gc_maxlifetime', TEMPO_SESSAO);
session_start();
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function () {
    responder_erro('Erro no servidor. Verifique se o MySQL está ligado.', 500);
});

function responder($dados, $status = 200)
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function responder_erro($mensagem, $status = 400)
{
    responder(['erro' => $mensagem], $status);
}

function ler_json()
{
    $dados = json_decode(file_get_contents('php://input'), true);
    return is_array($dados) ? $dados : [];
}

function ou_nulo($valor)
{
    $valor = trim($valor);
    return $valor === '' ? null : $valor;
}

function exigir_login()
{
    if (!isset($_SESSION['id_usuario'])) {
        responder_erro('Faça login para continuar.', 401);
    }

    if (time() - $_SESSION['ultimo_acesso'] > TEMPO_SESSAO) {
        session_destroy();
        responder_erro('Sua sessão expirou. Faça login novamente.', 401);
    }

    $_SESSION['ultimo_acesso'] = time();
}
