<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';
require __DIR__ . '/../seguranca.php';

exigir_login();

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

const STATUS_VALIDOS = ['agendado', 'concluido', 'cancelado'];

function tratar_agendamento($dados)
{
    return [
        'id_pet' => trim((string) ($dados['id_pet'] ?? '')),
        'data_hora' => trim($dados['data_hora'] ?? ''),
        'procedimento' => trim($dados['procedimento'] ?? ''),
        'status' => trim($dados['status'] ?? 'agendado'),
        'observacoes' => ou_nulo($dados['observacoes'] ?? ''),
    ];
}

function normalizar_data_hora($texto)
{
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $formato) {
        $data = DateTime::createFromFormat($formato, $texto);
        $problemas = DateTime::getLastErrors();

        if ($data && (!$problemas || ($problemas['warning_count'] === 0 && $problemas['error_count'] === 0))) {
            return $data->format('Y-m-d H:i:00');
        }
    }

    return null;
}

function validar_agendamento($pdo, &$agendamento, $data_hora_antiga)
{
    $erros = [];

    if (!ctype_digit($agendamento['id_pet'])) {
        $erros[] = 'Informe o pet.';
    } else {
        $comando = $pdo->prepare('SELECT id_pet FROM pet WHERE id_pet = ?');
        $comando->execute([$agendamento['id_pet']]);
        if (!$comando->fetch()) {
            $erros[] = 'O pet informado não existe.';
        }
    }

    if ($agendamento['data_hora'] === '') {
        $erros[] = 'Informe a data e o horário.';
    } else {
        $data_hora = normalizar_data_hora($agendamento['data_hora']);

        if ($data_hora === null) {
            $erros[] = 'Informe uma data e um horário válidos.';
        } else {
            $agendamento['data_hora'] = $data_hora;
            $mudou_data = $data_hora !== $data_hora_antiga;
            if ($agendamento['status'] === 'agendado' && $mudou_data && $data_hora < date('Y-m-d H:i:00')) {
                $erros[] = 'Não é possível agendar para uma data e horário que já passaram.';
            }
        }
    }

    if ($agendamento['procedimento'] === '') {
        $erros[] = 'Informe o procedimento.';
    } elseif (mb_strlen($agendamento['procedimento']) > 100) {
        $erros[] = 'O procedimento aceita no máximo 100 caracteres.';
    }

    if (!in_array($agendamento['status'], STATUS_VALIDOS, true)) {
        $erros[] = 'Status inválido.';
    }

    if ($agendamento['observacoes'] !== null && mb_strlen($agendamento['observacoes']) > 255) {
        $erros[] = 'As observações aceitam no máximo 255 caracteres.';
    }

    return $erros;
}

function horario_ocupado($pdo, $agendamento, $ignorar_id)
{
    $comando = $pdo->prepare(
        'SELECT id_agendamento FROM agendamento
         WHERE id_pet = ? AND data_hora = ? AND id_agendamento <> ?'
    );
    $comando->execute([$agendamento['id_pet'], $agendamento['data_hora'], $ignorar_id]);

    return $comando->fetch() !== false;
}


if ($metodo === 'GET' && $id !== '') {
    $comando = $pdo->prepare(
        'SELECT id_agendamento, id_pet, data_hora, procedimento, status, observacoes
         FROM agendamento WHERE id_agendamento = ?'
    );
    $comando->execute([$id]);
    $agendamento = $comando->fetch();

    if (!$agendamento) {
        responder_erro('Agendamento não encontrado.', 404);
    }

    responder($agendamento);
}


if ($metodo === 'GET') {
    $busca = trim($_GET['busca'] ?? '');
    $dia = trim($_GET['dia'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = 'SELECT a.id_agendamento, a.data_hora, a.procedimento, a.status, a.observacoes,
                   u.nome AS usuario_nome,
                   p.id_pet, p.nome AS pet_nome, p.especie AS pet_especie, p.raca AS pet_raca,
                   p.peso AS pet_peso, p.observacoes AS pet_observacoes,
                   t.id_tutor, t.nome AS tutor_nome, t.cpf AS tutor_cpf,
                   t.telefone AS tutor_telefone, t.email AS tutor_email, t.endereco AS tutor_endereco
            FROM agendamento a
            JOIN pet p ON p.id_pet = a.id_pet
            JOIN tutor t ON t.id_tutor = p.id_tutor
            JOIN usuario u ON u.id_usuario = a.id_usuario';
    $condicoes = [];
    $parametros = [];

    if ($busca !== '') {
        $termo = '%' . addcslashes($busca, '%_\\') . '%';
        $cpf_digitado = preg_replace('/\D/', '', $busca);

        $condicoes[] = '(p.nome LIKE ? OR t.nome LIKE ? OR a.procedimento LIKE ? OR t.cpf_hash = ?)';
        array_push($parametros, $termo, $termo, $termo, hash_cpf($cpf_digitado));
    }

    if ($dia !== '') {
        if (normalizar_data_hora($dia . ' 00:00') === null) {
            responder_erro('Informe um dia válido.');
        }
        $condicoes[] = 'a.data_hora >= ? AND a.data_hora < DATE_ADD(?, INTERVAL 1 DAY)';
        array_push($parametros, $dia, $dia);
    }

    if ($status !== '') {
        if (!in_array($status, STATUS_VALIDOS, true)) {
            responder_erro('Status inválido.');
        }
        $condicoes[] = 'a.status = ?';
        $parametros[] = $status;
    }

    if ($condicoes) {
        $sql .= ' WHERE ' . implode(' AND ', $condicoes);
    }

    $comando = $pdo->prepare($sql . ' ORDER BY a.data_hora, a.id_agendamento');
    $comando->execute($parametros);
    $agendamentos = $comando->fetchAll();

    foreach ($agendamentos as $posicao => $agendamento) {
        $agendamentos[$posicao]['tutor_cpf'] = cpf_para_listagem($agendamento['tutor_cpf']);
    }

    responder($agendamentos);
}


if ($metodo === 'POST') {
    $agendamento = tratar_agendamento(ler_json());

    $erros = validar_agendamento($pdo, $agendamento, null);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (horario_ocupado($pdo, $agendamento, 0)) {
        responder_erro('Este pet já possui um agendamento nesse dia e horário.', 409);
    }

    $comando = $pdo->prepare(
        'INSERT INTO agendamento (data_hora, procedimento, status, observacoes, id_pet, id_usuario)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $comando->execute([
        $agendamento['data_hora'], $agendamento['procedimento'], $agendamento['status'],
        $agendamento['observacoes'], $agendamento['id_pet'], $_SESSION['id_usuario'],
    ]);

    responder(['mensagem' => 'Agendamento cadastrado com sucesso.'], 201);
}


if ($metodo === 'PUT') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do agendamento.');
    }

    $comando = $pdo->prepare('SELECT data_hora FROM agendamento WHERE id_agendamento = ?');
    $comando->execute([$id]);
    $atual = $comando->fetch();
    if (!$atual) {
        responder_erro('Agendamento não encontrado.', 404);
    }

    $agendamento = tratar_agendamento(ler_json());

    $erros = validar_agendamento($pdo, $agendamento, $atual['data_hora']);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (horario_ocupado($pdo, $agendamento, $id)) {
        responder_erro('Este pet já possui outro agendamento nesse dia e horário.', 409);
    }

    $comando = $pdo->prepare(
        'UPDATE agendamento
         SET data_hora = ?, procedimento = ?, status = ?, observacoes = ?, id_pet = ?
         WHERE id_agendamento = ?'
    );
    $comando->execute([
        $agendamento['data_hora'], $agendamento['procedimento'], $agendamento['status'],
        $agendamento['observacoes'], $agendamento['id_pet'], $id,
    ]);

    responder(['mensagem' => 'Agendamento atualizado com sucesso.']);
}


if ($metodo === 'DELETE') {
    if (!ctype_digit($id)) {
        responder_erro('Informe o id do agendamento.');
    }

    $comando = $pdo->prepare('DELETE FROM agendamento WHERE id_agendamento = ?');
    $comando->execute([$id]);

    if ($comando->rowCount() === 0) {
        responder_erro('Agendamento não encontrado.', 404);
    }

    responder(['mensagem' => 'Agendamento excluído com sucesso.']);
}


responder_erro('Método não permitido.', 405);
