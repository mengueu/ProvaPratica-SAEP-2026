<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';
require __DIR__ . '/../seguranca.php';

exigir_login();

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

function tratar_tutor($dados)
{
    return [
        'nome' => trim($dados['nome'] ?? ''),
        'cpf' => preg_replace('/\D/', '', $dados['cpf'] ?? ''),
        'telefone' => trim($dados['telefone'] ?? ''),
        'email' => trim($dados['email'] ?? ''),
        'endereco' => ou_nulo($dados['endereco'] ?? ''),
    ];
}

function validar_tutor($tutor)
{
    $erros = [];

    if ($tutor['nome'] === '') {
        $erros[] = 'Informe o nome.';
    } elseif (mb_strlen($tutor['nome']) > 100) {
        $erros[] = 'O nome aceita no máximo 100 caracteres.';
    }

    if (strlen($tutor['cpf']) !== 11) {
        $erros[] = 'O CPF deve ter 11 números.';
    }

    $numeros_telefone = strlen(preg_replace('/\D/', '', $tutor['telefone']));
    if ($numeros_telefone < 10 || $numeros_telefone > 11 || strlen($tutor['telefone']) > 20) {
        $erros[] = 'O telefone deve ter 10 ou 11 números (com DDD).';
    }

    if ($tutor['email'] === '') {
        $erros[] = 'Informe o e-mail.';
    } elseif (!filter_var($tutor['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($tutor['email']) > 100) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if ($tutor['endereco'] !== null && mb_strlen($tutor['endereco']) > 200) {
        $erros[] = 'O endereço aceita no máximo 200 caracteres.';
    }

    return $erros;
}

function tutor_cpf_ja_cadastrado($pdo, $cpf, $ignorar_id)
{
    $comando = $pdo->prepare('SELECT id_tutor FROM tutor WHERE cpf_hash = ? AND id_tutor <> ?');
    $comando->execute([hash_cpf($cpf), $ignorar_id]);

    return $comando->fetch() !== false;
}


if ($metodo === 'GET' && $id !== '') {
    $comando = $pdo->prepare('SELECT id_tutor, nome, cpf, telefone, email, endereco FROM tutor WHERE id_tutor = ?');
    $comando->execute([$id]);
    $tutor = $comando->fetch();

    if (!$tutor) {
        responder_erro('Tutor não encontrado.', 404);
    }

    $tutor['cpf'] = formatar_cpf(descriptografar($tutor['cpf']));
    responder($tutor);
}


if ($metodo === 'GET') {
    $busca = trim($_GET['busca'] ?? '');

    $sql = 'SELECT id_tutor, nome, cpf, telefone, email, endereco FROM tutor';
    $parametros = [];

    if ($busca !== '') {
        $termo = '%' . addcslashes($busca, '%_\\') . '%';
        $cpf_digitado = preg_replace('/\D/', '', $busca);

        $sql .= ' WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ? OR cpf_hash = ?';
        $parametros = [$termo, $termo, $termo, hash_cpf($cpf_digitado)];
    }

    $comando = $pdo->prepare($sql . ' ORDER BY nome');
    $comando->execute($parametros);
    $tutores = $comando->fetchAll();

    foreach ($tutores as $posicao => $tutor) {
        $tutores[$posicao]['cpf'] = cpf_para_listagem($tutor['cpf']);
    }

    responder($tutores);
}


if ($metodo === 'POST') {
    $tutor = tratar_tutor(ler_json());

    $erros = validar_tutor($tutor);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (tutor_cpf_ja_cadastrado($pdo, $tutor['cpf'], 0)) {
        responder_erro('Já existe um tutor cadastrado com este CPF.', 409);
    }

    $comando = $pdo->prepare(
        'INSERT INTO tutor (nome, cpf, cpf_hash, telefone, email, endereco) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $comando->execute([
        $tutor['nome'], criptografar($tutor['cpf']), hash_cpf($tutor['cpf']),
        $tutor['telefone'], $tutor['email'], $tutor['endereco'],
    ]);

    responder(['mensagem' => 'Tutor cadastrado com sucesso.'], 201);
}


if ($metodo === 'PUT') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do tutor.');
    }

    $comando = $pdo->prepare('SELECT id_tutor FROM tutor WHERE id_tutor = ?');
    $comando->execute([$id]);
    if (!$comando->fetch()) {
        responder_erro('Tutor não encontrado.', 404);
    }

    $tutor = tratar_tutor(ler_json());

    $erros = validar_tutor($tutor);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (tutor_cpf_ja_cadastrado($pdo, $tutor['cpf'], $id)) {
        responder_erro('Já existe outro tutor cadastrado com este CPF.', 409);
    }

    $comando = $pdo->prepare(
        'UPDATE tutor
         SET nome = ?, cpf = ?, cpf_hash = ?, telefone = ?, email = ?, endereco = ?
         WHERE id_tutor = ?'
    );
    $comando->execute([
        $tutor['nome'], criptografar($tutor['cpf']), hash_cpf($tutor['cpf']),
        $tutor['telefone'], $tutor['email'], $tutor['endereco'], $id,
    ]);

    responder(['mensagem' => 'Tutor atualizado com sucesso.']);
}


if ($metodo === 'DELETE') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do tutor.');
    }

    try {
        $comando = $pdo->prepare('DELETE FROM tutor WHERE id_tutor = ?');
        $comando->execute([$id]);
    } catch (PDOException $erro) {
        if ($erro->errorInfo[1] == 1451) {
            responder_erro('Não é possível excluir: o tutor possui pets cadastrados.', 409);
        }
        throw $erro;
    }

    if ($comando->rowCount() === 0) {
        responder_erro('Tutor não encontrado.', 404);
    }

    responder(['mensagem' => 'Tutor excluído com sucesso.']);
}


responder_erro('Método não permitido.', 405);
