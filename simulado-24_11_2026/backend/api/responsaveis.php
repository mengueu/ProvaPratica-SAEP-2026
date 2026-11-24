<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';
require __DIR__ . '/../seguranca.php';

exigir_login();

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';


// Pega os campos enviados pelo front-end e deixa tudo pronto para validar
function tratar_responsavel($dados)
{
    return [
        'nome' => trim($dados['nome'] ?? ''),
        'cpf' => preg_replace('/\D/', '', $dados['cpf'] ?? ''), // só os números
        'telefone' => trim($dados['telefone'] ?? ''),
        'email' => trim($dados['email'] ?? ''),
        'endereco' => ou_nulo($dados['endereco'] ?? ''),
    ];
}

// Devolve uma lista com as mensagens de erro (lista vazia = dados válidos)
function validar_responsavel($r)
{
    $erros = [];

    if ($r['nome'] === '') {
        $erros[] = 'Informe o nome.';
    } elseif (mb_strlen($r['nome']) > 100) {
        $erros[] = 'O nome aceita no máximo 100 caracteres.';
    }

    if (strlen($r['cpf']) !== 11) {
        $erros[] = 'O CPF deve ter 11 números.';
    }

    // O telefone pode ser digitado com parênteses e traço
    $numeros_telefone = strlen(preg_replace('/\D/', '', $r['telefone']));
    if ($numeros_telefone < 10 || $numeros_telefone > 11 || strlen($r['telefone']) > 20) {
        $erros[] = 'O telefone deve ter 10 ou 11 números (com DDD).';
    }

    if ($r['email'] === '') {
        $erros[] = 'Informe o e-mail.';
    } elseif (!filter_var($r['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($r['email']) > 100) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if ($r['endereco'] !== null && mb_strlen($r['endereco']) > 200) {
        $erros[] = 'O endereço aceita no máximo 200 caracteres.';
    }

    return $erros;
}

// Já existe outro responsável com este CPF? ($ignorar_id é o responsável que está sendo editado)
function cpf_ja_cadastrado($pdo, $cpf, $ignorar_id)
{
    $comando = $pdo->prepare('SELECT id_responsavel FROM responsavel WHERE cpf_hash = ? AND id_responsavel <> ?');
    $comando->execute([hash_cpf($cpf), $ignorar_id]);

    return $comando->fetch() !== false;
}


if ($metodo === 'GET' && $id !== '') {
    $comando = $pdo->prepare('SELECT id_responsavel, nome, cpf, telefone, email, endereco FROM responsavel WHERE id_responsavel = ?');
    $comando->execute([$id]);
    $responsavel = $comando->fetch();

    if (!$responsavel) {
        responder_erro('Responsável não encontrado.', 404);
    }

    $responsavel['cpf'] = formatar_cpf(descriptografar($responsavel['cpf']));
    responder($responsavel);
}


if ($metodo === 'GET') {
    $busca = trim($_GET['busca'] ?? '');

    $sql = 'SELECT id_responsavel, nome, cpf, telefone, email, endereco FROM responsavel';
    $parametros = [];

    if ($busca !== '') {
        // addcslashes: faz % e _ digitados valerem como texto comum
        $termo = '%' . addcslashes($busca, '%_\\') . '%';
        // O CPF está criptografado, então a busca por CPF compara o hash do número digitado
        $cpf_digitado = preg_replace('/\D/', '', $busca);

        $sql .= ' WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ? OR cpf_hash = ?';
        $parametros = [$termo, $termo, $termo, hash_cpf($cpf_digitado)];
    }

    $comando = $pdo->prepare($sql . ' ORDER BY nome');
    $comando->execute($parametros);
    $responsaveis = $comando->fetchAll();

    // Na listagem o CPF aparece parcialmente escondido
    foreach ($responsaveis as $posicao => $responsavel) {
        $responsaveis[$posicao]['cpf'] = cpf_para_listagem($responsavel['cpf']);
    }

    responder($responsaveis);
}


if ($metodo === 'POST') {
    $r = tratar_responsavel(ler_json());

    $erros = validar_responsavel($r);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (cpf_ja_cadastrado($pdo, $r['cpf'], 0)) {
        responder_erro('Já existe um responsável cadastrado com este CPF.', 409);
    }

    $comando = $pdo->prepare(
        'INSERT INTO responsavel (nome, cpf, cpf_hash, telefone, email, endereco) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $comando->execute([
        $r['nome'], criptografar($r['cpf']), hash_cpf($r['cpf']),
        $r['telefone'], $r['email'], $r['endereco'],
    ]);

    responder(['mensagem' => 'Responsável cadastrado com sucesso.'], 201);
}


if ($metodo === 'PUT') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do responsável.');
    }

    $comando = $pdo->prepare('SELECT id_responsavel FROM responsavel WHERE id_responsavel = ?');
    $comando->execute([$id]);
    if (!$comando->fetch()) {
        responder_erro('Responsável não encontrado.', 404);
    }

    $r = tratar_responsavel(ler_json());

    $erros = validar_responsavel($r);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (cpf_ja_cadastrado($pdo, $r['cpf'], $id)) {
        responder_erro('Já existe outro responsável cadastrado com este CPF.', 409);
    }

    $comando = $pdo->prepare(
        'UPDATE responsavel
         SET nome = ?, cpf = ?, cpf_hash = ?, telefone = ?, email = ?, endereco = ?
         WHERE id_responsavel = ?'
    );
    $comando->execute([
        $r['nome'], criptografar($r['cpf']), hash_cpf($r['cpf']),
        $r['telefone'], $r['email'], $r['endereco'], $id,
    ]);

    responder(['mensagem' => 'Responsável atualizado com sucesso.']);
}


if ($metodo === 'DELETE') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do responsável.');
    }

    try {
        $comando = $pdo->prepare('DELETE FROM responsavel WHERE id_responsavel = ?');
        $comando->execute([$id]);
    } catch (PDOException $erro) {
        // Código 1451: o responsável ainda tem pacientes e não pode ser apagado
        if ($erro->errorInfo[1] == 1451) {
            responder_erro('Não é possível excluir: o responsável possui pacientes cadastrados.', 409);
        }
        throw $erro;
    }

    if ($comando->rowCount() === 0) {
        responder_erro('Responsável não encontrado.', 404);
    }

    responder(['mensagem' => 'Responsável excluído com sucesso.']);
}


responder_erro('Método não permitido.', 405);
