<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../conexao.php';
require __DIR__ . '/../seguranca.php';

exigir_login();

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

const STATUS_VALIDOS = ['agendado', 'concluido', 'cancelado'];


// Pega os campos enviados pelo front-end e deixa tudo pronto para validar
function tratar_agendamento($dados)
{
    return [
        'id_paciente' => trim((string) ($dados['id_paciente'] ?? '')),
        'data_hora' => trim($dados['data_hora'] ?? ''),
        'procedimento' => trim($dados['procedimento'] ?? ''),
        'status' => trim($dados['status'] ?? 'agendado'),
        'observacoes' => ou_nulo($dados['observacoes'] ?? ''),
    ];
}

// Aceita "2026-11-25T09:30" (campo datetime-local) ou "2026-11-25 09:30[:00]".
// Devolve "2026-11-25 09:30:00" ou null se a data/hora não for válida.
function normalizar_data_hora($texto)
{
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $formato) {
        $data = DateTime::createFromFormat($formato, $texto);
        $problemas = DateTime::getLastErrors();

        // getLastErrors() só avisa de datas impossíveis (ex.: 31/02) quando há "warnings"
        if ($data && (!$problemas || ($problemas['warning_count'] === 0 && $problemas['error_count'] === 0))) {
            return $data->format('Y-m-d H:i:00');
        }
    }

    return null;
}

// Devolve uma lista com as mensagens de erro (lista vazia = dados válidos).
// $data_hora_antiga é a data atual do agendamento ao editar (null ao cadastrar).
function validar_agendamento($pdo, &$a, $data_hora_antiga)
{
    $erros = [];

    if (!ctype_digit($a['id_paciente'])) {
        $erros[] = 'Informe o paciente.';
    } else {
        $comando = $pdo->prepare('SELECT id_paciente FROM paciente WHERE id_paciente = ?');
        $comando->execute([$a['id_paciente']]);
        if (!$comando->fetch()) {
            $erros[] = 'O paciente informado não existe.';
        }
    }

    if ($a['data_hora'] === '') {
        $erros[] = 'Informe a data e o horário.';
    } else {
        $data_hora = normalizar_data_hora($a['data_hora']);

        if ($data_hora === null) {
            $erros[] = 'Informe uma data e um horário válidos.';
        } else {
            $a['data_hora'] = $data_hora;

            // Não se marca consulta no passado. Ao editar, só vale se a data foi alterada
            // (assim ainda dá para marcar como concluído ou cancelado um agendamento antigo).
            $mudou_data = $data_hora !== $data_hora_antiga;
            if ($a['status'] === 'agendado' && $mudou_data && $data_hora < date('Y-m-d H:i:00')) {
                $erros[] = 'Não é possível agendar para uma data e horário que já passaram.';
            }
        }
    }

    if ($a['procedimento'] === '') {
        $erros[] = 'Informe o procedimento.';
    } elseif (mb_strlen($a['procedimento']) > 100) {
        $erros[] = 'O procedimento aceita no máximo 100 caracteres.';
    }

    if (!in_array($a['status'], STATUS_VALIDOS, true)) {
        $erros[] = 'Status inválido.';
    }

    if ($a['observacoes'] !== null && mb_strlen($a['observacoes']) > 255) {
        $erros[] = 'As observações aceitam no máximo 255 caracteres.';
    }

    return $erros;
}

// O paciente já tem outro agendamento nesse mesmo dia e horário?
// ($ignorar_id é o agendamento que está sendo editado)
function horario_ocupado($pdo, $a, $ignorar_id)
{
    $comando = $pdo->prepare(
        'SELECT id_agendamento FROM agendamento
         WHERE id_paciente = ? AND data_hora = ? AND id_agendamento <> ?'
    );
    $comando->execute([$a['id_paciente'], $a['data_hora'], $ignorar_id]);

    return $comando->fetch() !== false;
}


if ($metodo === 'GET' && $id !== '') {
    $comando = $pdo->prepare(
        'SELECT id_agendamento, id_paciente, data_hora, procedimento, status, observacoes
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
                   p.id_paciente, p.nome AS paciente_nome, p.data_nascimento AS paciente_nascimento,
                   p.observacoes AS paciente_observacoes,
                   r.id_responsavel, r.nome AS responsavel_nome, r.cpf AS responsavel_cpf,
                   r.telefone AS responsavel_telefone, r.email AS responsavel_email,
                   r.endereco AS responsavel_endereco
            FROM agendamento a
            JOIN paciente p ON p.id_paciente = a.id_paciente
            JOIN responsavel r ON r.id_responsavel = p.id_responsavel
            JOIN usuario u ON u.id_usuario = a.id_usuario';
    $condicoes = [];
    $parametros = [];

    if ($busca !== '') {
        // addcslashes: faz % e _ digitados valerem como texto comum
        $termo = '%' . addcslashes($busca, '%_\\') . '%';
        // O CPF está criptografado, então a busca por CPF compara o hash do número digitado
        $cpf_digitado = preg_replace('/\D/', '', $busca);

        $condicoes[] = '(p.nome LIKE ? OR r.nome LIKE ? OR a.procedimento LIKE ? OR r.cpf_hash = ?)';
        array_push($parametros, $termo, $termo, $termo, hash_cpf($cpf_digitado));
    }

    if ($dia !== '') {
        if (normalizar_data_hora($dia . ' 00:00') === null) {
            responder_erro('Informe um dia válido.');
        }
        // intervalo em vez de DATE(): aproveita o índice de data_hora
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

    // Ordenado por dia e horário do agendamento (o id desempata)
    $comando = $pdo->prepare($sql . ' ORDER BY a.data_hora, a.id_agendamento');
    $comando->execute($parametros);
    $agendamentos = $comando->fetchAll();

    // Na listagem o CPF do responsável aparece parcialmente escondido (LGPD)
    foreach ($agendamentos as $posicao => $agendamento) {
        $agendamentos[$posicao]['responsavel_cpf'] = cpf_para_listagem($agendamento['responsavel_cpf']);
    }

    responder($agendamentos);
}


if ($metodo === 'POST') {
    $a = tratar_agendamento(ler_json());

    $erros = validar_agendamento($pdo, $a, null);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (horario_ocupado($pdo, $a, 0)) {
        responder_erro('Este paciente já possui um agendamento nesse dia e horário.', 409);
    }

    $comando = $pdo->prepare(
        'INSERT INTO agendamento (data_hora, procedimento, status, observacoes, id_paciente, id_usuario)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $comando->execute([
        $a['data_hora'], $a['procedimento'], $a['status'], $a['observacoes'],
        $a['id_paciente'], $_SESSION['id_usuario'],
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

    $a = tratar_agendamento(ler_json());

    $erros = validar_agendamento($pdo, $a, $atual['data_hora']);
    if ($erros) {
        responder_erro(implode("\n", $erros));
    }
    if (horario_ocupado($pdo, $a, $id)) {
        responder_erro('Este paciente já possui outro agendamento nesse dia e horário.', 409);
    }

    $comando = $pdo->prepare(
        'UPDATE agendamento
         SET data_hora = ?, procedimento = ?, status = ?, observacoes = ?, id_paciente = ?
         WHERE id_agendamento = ?'
    );
    $comando->execute([
        $a['data_hora'], $a['procedimento'], $a['status'], $a['observacoes'],
        $a['id_paciente'], $id,
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
