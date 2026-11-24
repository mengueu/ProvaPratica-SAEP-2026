<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_erro('Método não permitido.', 405);
}

$dados = ler_json();
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

if ($email === '' || $senha === '') {
    responder_erro('Informe o e-mail e a senha.');
}

$pdo = conectar();
$comando = $pdo->prepare('SELECT id_usuario, nome, senha_hash FROM usuario WHERE email = ?');
$comando->execute([$email]);
$usuario = $comando->fetch();

if (!$usuario) {
    responder_erro('Usuário não encontrado.', 401);
}

if (!password_verify($senha, $usuario['senha_hash'])) {
    responder_erro('Senha incorreta.', 401);
}

// Login correto: cria a sessão
session_regenerate_id(true);
$_SESSION['id_usuario'] = $usuario['id_usuario'];
$_SESSION['nome_usuario'] = $usuario['nome'];
$_SESSION['ultimo_acesso'] = time();

responder(['nome' => $usuario['nome']]);
