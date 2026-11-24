const formAgendamento = document.getElementById('form-agendamento');
const formBusca = document.getElementById('form-busca');
const corpoTabela = document.getElementById('lista-agendamentos');
const selectPaciente = document.getElementById('id_paciente');

const campos = ['id_paciente', 'data_hora', 'procedimento', 'status', 'observacoes'];

const NOMES_STATUS = { agendado: 'Agendado', concluido: 'Concluído', cancelado: 'Cancelado' };
const CLASSES_STATUS = { agendado: 'selo-neutro', concluido: 'selo-ok', cancelado: 'selo-erro' };


async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }
    await carregarPacientes();
    carregarAgendamentos();
}

async function carregarPacientes() {
    try {
        const pacientes = await chamarApi('pacientes.php');

        pacientes.forEach(function (p) {
            const opcao = document.createElement('option');
            opcao.value = p.id_paciente;
            opcao.textContent = p.nome + ' (resp.: ' + p.responsavel_nome + ')';
            selectPaciente.appendChild(opcao);
        });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function carregarAgendamentos() {
    const filtros = new URLSearchParams({
        busca: document.getElementById('busca').value.trim(),
        dia: document.getElementById('filtro-dia').value,
        status: document.getElementById('filtro-status').value,
    });

    try {
        const agendamentos = await chamarApi('agendamentos.php?' + filtros.toString());
        desenharTabela(agendamentos);
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function hoje() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// 2026-11-24 -> "terça-feira, 24/11/2026"
function tituloDoDia(dia) {
    const [ano, mes, dia_mes] = dia.split('-').map(Number);
    const semana = new Date(ano, mes - 1, dia_mes).toLocaleDateString('pt-BR', { weekday: 'long' });
    const titulo = semana + ', ' + formatarData(dia);

    return dia === hoje() ? titulo + ' (hoje)' : titulo;
}

function desenharTabela(agendamentos) {
    document.getElementById('total').textContent = agendamentos.length;

    if (agendamentos.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="6">Nenhum agendamento encontrado.</td></tr>';
        return;
    }

    let linhas = '';
    let diaAtual = '';

    agendamentos.forEach(function (a) {
        const [dia, hora] = a.data_hora.split(' ');

        if (dia !== diaAtual) {
            diaAtual = dia;
            linhas += `<tr class="linha-dia"><td colspan="6">${tituloDoDia(dia)}</td></tr>`;
        }

        linhas += `
            <tr>
                <td><strong>${hora.slice(0, 5)}</strong></td>
                <td><span class="selo ${CLASSES_STATUS[a.status]}">${NOMES_STATUS[a.status]}</span></td>
                <td>
                    ${escapar(a.procedimento)}
                    ${a.observacoes ? '<br><small>' + escapar(a.observacoes) + '</small>' : ''}
                    <br><small class="suave">Registrado por ${escapar(a.usuario_nome)}</small>
                </td>
                <td>
                    <strong>${escapar(a.paciente_nome)}</strong>
                    <br><small>Nascimento: ${formatarData(a.paciente_nascimento)}</small>
                    ${a.paciente_observacoes ? '<br><small>' + escapar(a.paciente_observacoes) + '</small>' : ''}
                </td>
                <td>
                    <strong>${escapar(a.responsavel_nome)}</strong>
                    <br><small>CPF: ${escapar(a.responsavel_cpf)}</small>
                    <br><small>Tel.: ${escapar(a.responsavel_telefone)}</small>
                    <br><small>${escapar(a.responsavel_email)}</small>
                    ${a.responsavel_endereco ? '<br><small>' + escapar(a.responsavel_endereco) + '</small>' : ''}
                </td>
                <td class="acoes-linha">
                    <button class="btn btn-pequeno" data-acao="editar" data-id="${a.id_agendamento}">Editar</button>
                    <button class="btn btn-pequeno btn-perigo" data-acao="excluir" data-id="${a.id_agendamento}">Excluir</button>
                </td>
            </tr>`;
    });

    corpoTabela.innerHTML = linhas;
}

formAgendamento.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const id = document.getElementById('id_agendamento').value;

    const dados = {};
    campos.forEach(function (campo) {
        dados[campo] = document.getElementById(campo).value;
    });

    try {
        let resposta;

        if (id === '') {
            resposta = await chamarApi('agendamentos.php', 'POST', dados);
        } else {
            resposta = await chamarApi('agendamentos.php?id=' + id, 'PUT', dados);
        }

        mostrarMensagem(resposta.mensagem);
        limparFormulario();
        carregarAgendamentos();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
});

corpoTabela.addEventListener('click', function (evento) {
    const acao = evento.target.dataset.acao;
    const id = evento.target.dataset.id;

    if (acao === 'editar') {
        editarAgendamento(id);
    }
    if (acao === 'excluir') {
        excluirAgendamento(id);
    }
});

async function editarAgendamento(id) {
    try {
        const agendamento = await chamarApi('agendamentos.php?id=' + id);
        agendamento.data_hora = agendamento.data_hora.slice(0, 16).replace(' ', 'T');

        campos.forEach(function (campo) {
            const valor = agendamento[campo];
            document.getElementById(campo).value = valor === null ? '' : valor;
        });

        document.getElementById('id_agendamento').value = id;
        document.getElementById('titulo-formulario').textContent = 'Editar agendamento #' + id;
        document.getElementById('botao-salvar').textContent = 'Salvar alterações';
        document.getElementById('botao-cancelar').hidden = false;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function excluirAgendamento(id) {
    if (!confirm('Deseja realmente excluir este agendamento?')) {
        return;
    }

    try {
        const resposta = await chamarApi('agendamentos.php?id=' + id, 'DELETE');
        mostrarMensagem(resposta.mensagem);
        carregarAgendamentos();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function limparFormulario() {
    formAgendamento.reset();
    document.getElementById('id_agendamento').value = '';
    document.getElementById('titulo-formulario').textContent = 'Novo agendamento';
    document.getElementById('botao-salvar').textContent = 'Cadastrar agendamento';
    document.getElementById('botao-cancelar').hidden = true;
}

document.getElementById('botao-cancelar').addEventListener('click', limparFormulario);

formBusca.addEventListener('submit', function (evento) {
    evento.preventDefault();
    carregarAgendamentos();
});

document.getElementById('botao-limpar-busca').addEventListener('click', function () {
    formBusca.reset();
    carregarAgendamentos();
});

iniciar();
