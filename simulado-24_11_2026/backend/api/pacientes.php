<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';

exigir_login();

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

// Colunas devolvidas nas consultas: o paciente e o nome/telefone do seu responsável
const COLUNAS_PACIENTE = 'p.id_paciente, p.nome, p.data_nascimento, p.observacoes, p.id_responsavel,
                          r.nome AS responsavel_nome, r.telefone AS responsavel_telefone';


// Pega os campos enviados pelo front-end e deixa tudo pronto para validar
function tratar_paciente($dados)
{
    return [
        'nome' => trim($dados['nome'] ?? ''),
        'data_nascimento' => trim($dados['data_nascimento'] ?? ''),
        'observacoes' => ou_nulo($dados['observacoes'] ?? ''),
        'id_responsavel' => trim((string) ($dados['id_responsavel'] ?? '')),
    ];
}

// Devolve uma lista com as mensagens de erro (lista vazia = dados válidos)
function validar_paciente($pdo, $p)
{
    $erros = [];

    if ($p['nome'] === '') {
        $erros[] = 'Informe o nome.';
    } elseif (mb_strlen($p['nome']) > 100) {
        $erros[] = 'O nome aceita no máximo 100 caracteres.';
    }

    // A data vem como AAAA-MM-DD; checkdate() recusa datas que não existem (ex.: 31/02)
    $partes = explode('-', $p['data_nascimento']);
    if ($p['data_nascimento'] === '') {
        $erros[] = 'Informe a data de nascimento.';
    } elseif (count($partes) !== 3 || !checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
        $erros[] = 'Informe uma data de nascimento válida.';
    } elseif ($p['data_nascimento'] > date('Y-m-d')) {
        $erros[] = 'A data de nascimento não pode ser no futuro.';
    }

    if ($p['observacoes'] !== null && mb_strlen($p['observacoes']) > 255) {
        $erros[] = 'As observações aceitam no máximo 255 caracteres.';
    }

    if (!ctype_digit($p['id_responsavel'])) {
        $erros[] = 'Informe o responsável pelo paciente.';
    } else {
        $comando = $pdo->prepare('SELECT id_responsavel FROM responsavel WHERE id_responsavel = ?');
        $comando->execute([$p['id_responsavel']]);
        if (!$comando->fetch()) {
            $erros[] = 'O responsável informado não existe.';
        }
    }

    return $erros;
}

// Já existe outro paciente com o mesmo nome e nascimento para este responsável?
// ($ignorar_id é o paciente que está sendo editado)
function paciente_ja_cadastrado($pdo, $p, $ignorar_id)
{
    $comando = $pdo->prepare(
        'SELECT id_paciente FROM paciente
         WHERE id_responsavel = ? AND nome = ? AND data_nascimento = ? AND id_paciente <> ?'
    );
    $comando->execute([$p['id_responsavel'], $p['nome'], $p['data_nascimento'], $ignorar_id]);

    return $comando->fetch() !== false;
}


if ($metodo === 'GET' && $id !== '') {
    $comando = $pdo->prepare(
        'SELECT ' . COLUNAS_PACIENTE . '
         FROM paciente p
         JOIN responsavel r ON r.id_responsavel = p.id_responsavel
         WHERE p.id_paciente = ?'
    );
    $comando->execute([$id]);
    $paciente = $comando->fetch();

    if (!$paciente) {
        responder_erro('Paciente não encontrado.', 404);
    }

    responder($paciente);
}


if ($metodo === 'GET') {
    $busca = trim($_GET['busca'] ?? '');

    $sql = 'SELECT ' . COLUNAS_PACIENTE . '
            FROM paciente p
            JOIN responsavel r ON r.id_responsavel = p.id_responsavel';
    $parametros = [];

    if ($busca !== '') {
        // addcslashes: faz % e _ digitados valerem como texto comum
        $termo = '%' . addcslashes($busca, '%_\\') . '%';

        $sql .= ' WHERE p.nome LIKE ? OR r.nome LIKE ? OR p.observacoes LIKE ?';
        $parametros = [$termo, $termo, $termo];
    }

    $comando = $pdo->prepare($sql . ' ORDER BY p.nome');
    $comando->execute($parametros);

    responder($comando->fetchAll());
}


if ($metodo === 'POST') {
    $p = tratar_paciente(ler_json());

    $erros = validar_paciente($pdo, $p);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (paciente_ja_cadastrado($pdo, $p, 0)) {
        responder_erro('Este paciente já está cadastrado para o responsável escolhido.', 409);
    }

    $comando = $pdo->prepare(
        'INSERT INTO paciente (nome, data_nascimento, observacoes, id_responsavel) VALUES (?, ?, ?, ?)'
    );
    $comando->execute([$p['nome'], $p['data_nascimento'], $p['observacoes'], $p['id_responsavel']]);

    responder(['mensagem' => 'Paciente cadastrado com sucesso.'], 201);
}


if ($metodo === 'PUT') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do paciente.');
    }

    $comando = $pdo->prepare('SELECT id_paciente FROM paciente WHERE id_paciente = ?');
    $comando->execute([$id]);
    if (!$comando->fetch()) {
        responder_erro('Paciente não encontrado.', 404);
    }

    $p = tratar_paciente(ler_json());

    $erros = validar_paciente($pdo, $p);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (paciente_ja_cadastrado($pdo, $p, $id)) {
        responder_erro('Este paciente já está cadastrado para o responsável escolhido.', 409);
    }

    $comando = $pdo->prepare(
        'UPDATE paciente
         SET nome = ?, data_nascimento = ?, observacoes = ?, id_responsavel = ?
         WHERE id_paciente = ?'
    );
    $comando->execute([$p['nome'], $p['data_nascimento'], $p['observacoes'], $p['id_responsavel'], $id]);

    responder(['mensagem' => 'Paciente atualizado com sucesso.']);
}


if ($metodo === 'DELETE') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do paciente.');
    }

    try {
        $comando = $pdo->prepare('DELETE FROM paciente WHERE id_paciente = ?');
        $comando->execute([$id]);
    } catch (PDOException $erro) {
        // Código 1451: o paciente ainda tem agendamentos e não pode ser apagado
        if ($erro->errorInfo[1] == 1451) {
            responder_erro('Não é possível excluir: o paciente possui agendamentos.', 409);
        }
        throw $erro;
    }

    if ($comando->rowCount() === 0) {
        responder_erro('Paciente não encontrado.', 404);
    }

    responder(['mensagem' => 'Paciente excluído com sucesso.']);
}


responder_erro('Método não permitido.', 405);
