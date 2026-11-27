<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';

exigir_login();

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

const COLUNAS_PET = 'p.id_pet, p.nome, p.especie, p.raca, p.peso, p.observacoes, p.id_tutor,
                     t.nome AS tutor_nome, t.telefone AS tutor_telefone';

function tratar_pet($dados)
{
    return [
        'nome' => trim($dados['nome'] ?? ''),
        'especie' => trim($dados['especie'] ?? ''),
        'raca' => trim($dados['raca'] ?? ''),
        'peso' => trim((string) ($dados['peso'] ?? '')),
        'observacoes' => ou_nulo($dados['observacoes'] ?? ''),
        'id_tutor' => trim((string) ($dados['id_tutor'] ?? '')),
    ];
}

function validar_pet($pdo, $pet)
{
    $erros = [];

    if ($pet['nome'] === '') {
        $erros[] = 'Informe o nome do pet.';
    } elseif (mb_strlen($pet['nome']) > 100) {
        $erros[] = 'O nome aceita no máximo 100 caracteres.';
    }

    if ($pet['especie'] === '') {
        $erros[] = 'Informe a espécie.';
    } elseif (mb_strlen($pet['especie']) > 50) {
        $erros[] = 'A espécie aceita no máximo 50 caracteres.';
    }

    if ($pet['raca'] === '') {
        $erros[] = 'Informe a raça.';
    } elseif (mb_strlen($pet['raca']) > 100) {
        $erros[] = 'A raça aceita no máximo 100 caracteres.';
    }

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $pet['peso']) || (float) $pet['peso'] <= 0 || (float) $pet['peso'] > 99999.99) {
        $erros[] = 'Informe um peso maior que zero, com até duas casas decimais.';
    }

    if ($pet['observacoes'] !== null && mb_strlen($pet['observacoes']) > 255) {
        $erros[] = 'As observações aceitam no máximo 255 caracteres.';
    }

    if (!ctype_digit($pet['id_tutor'])) {
        $erros[] = 'Informe o tutor do pet.';
    } else {
        $comando = $pdo->prepare('SELECT id_tutor FROM tutor WHERE id_tutor = ?');
        $comando->execute([$pet['id_tutor']]);
        if (!$comando->fetch()) {
            $erros[] = 'O tutor informado não existe.';
        }
    }

    return $erros;
}


if ($metodo === 'GET' && $id !== '') {
    $comando = $pdo->prepare(
        'SELECT ' . COLUNAS_PET . '
         FROM pet p
         JOIN tutor t ON t.id_tutor = p.id_tutor
         WHERE p.id_pet = ?'
    );
    $comando->execute([$id]);
    $pet = $comando->fetch();

    if (!$pet) {
        responder_erro('Pet não encontrado.', 404);
    }

    responder($pet);
}


if ($metodo === 'GET') {
    $busca = trim($_GET['busca'] ?? '');

    $sql = 'SELECT ' . COLUNAS_PET . '
            FROM pet p
            JOIN tutor t ON t.id_tutor = p.id_tutor';
    $parametros = [];

    if ($busca !== '') {
        $termo = '%' . addcslashes($busca, '%_\\') . '%';
        $sql .= ' WHERE p.nome LIKE ? OR p.especie LIKE ? OR p.raca LIKE ?
                  OR t.nome LIKE ? OR p.observacoes LIKE ?';
        $parametros = [$termo, $termo, $termo, $termo, $termo];
    }

    $comando = $pdo->prepare($sql . ' ORDER BY p.nome');
    $comando->execute($parametros);

    responder($comando->fetchAll());
}


if ($metodo === 'POST') {
    $pet = tratar_pet(ler_json());

    $erros = validar_pet($pdo, $pet);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }

    $comando = $pdo->prepare(
        'INSERT INTO pet (nome, especie, raca, peso, observacoes, id_tutor) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $comando->execute([
        $pet['nome'], $pet['especie'], $pet['raca'], $pet['peso'], $pet['observacoes'], $pet['id_tutor'],
    ]);

    responder(['mensagem' => 'Pet cadastrado com sucesso.'], 201);
}


if ($metodo === 'PUT') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do pet.');
    }

    $comando = $pdo->prepare('SELECT id_pet FROM pet WHERE id_pet = ?');
    $comando->execute([$id]);
    if (!$comando->fetch()) {
        responder_erro('Pet não encontrado.', 404);
    }

    $pet = tratar_pet(ler_json());

    $erros = validar_pet($pdo, $pet);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }

    $comando = $pdo->prepare(
        'UPDATE pet
         SET nome = ?, especie = ?, raca = ?, peso = ?, observacoes = ?, id_tutor = ?
         WHERE id_pet = ?'
    );
    $comando->execute([
        $pet['nome'], $pet['especie'], $pet['raca'], $pet['peso'],
        $pet['observacoes'], $pet['id_tutor'], $id,
    ]);

    responder(['mensagem' => 'Pet atualizado com sucesso.']);
}


if ($metodo === 'DELETE') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do pet.');
    }

    try {
        $comando = $pdo->prepare('DELETE FROM pet WHERE id_pet = ?');
        $comando->execute([$id]);
    } catch (PDOException $erro) {
        if ($erro->errorInfo[1] == 1451) {
            responder_erro('Não é possível excluir: o pet possui agendamentos.', 409);
        }
        throw $erro;
    }

    if ($comando->rowCount() === 0) {
        responder_erro('Pet não encontrado.', 404);
    }

    responder(['mensagem' => 'Pet excluído com sucesso.']);
}


responder_erro('Método não permitido.', 405);
