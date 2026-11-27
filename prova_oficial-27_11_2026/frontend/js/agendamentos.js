const formAgendamento = document.getElementById('form-agendamento');
const formBusca = document.getElementById('form-busca');
const corpoTabela = document.getElementById('lista-agendamentos');
const selectPet = document.getElementById('id_pet');

const campos = ['id_pet', 'data_hora', 'procedimento', 'status', 'observacoes'];

const NOMES_STATUS = { agendado: 'Agendado', concluido: 'Concluído', cancelado: 'Cancelado' };
const CLASSES_STATUS = { agendado: 'selo-neutro', concluido: 'selo-ok', cancelado: 'selo-erro' };


async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }
    await carregarPets();
    carregarAgendamentos();
}

async function carregarPets() {
    try {
        const pets = await chamarApi('pets.php');

        pets.forEach(function (pet) {
            const opcao = document.createElement('option');
            opcao.value = pet.id_pet;
            opcao.textContent = pet.nome + ' (' + pet.especie + ' - tutor: ' + pet.tutor_nome + ')';
            selectPet.appendChild(opcao);
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

function tituloDoDia(dia) {
    const [ano, mes, diaMes] = dia.split('-').map(Number);
    const semana = new Date(ano, mes - 1, diaMes).toLocaleDateString('pt-BR', { weekday: 'long' });
    const titulo = semana + ', ' + formatarData(dia);

    return dia === hoje() ? titulo + ' (hoje)' : titulo;
}

function desenharTabela(agendamentos) {
    if (!Array.isArray(agendamentos)) {
        throw new Error('A API de agendamentos retornou um formato inesperado. Confira a conexão com o banco de dados.');
    }

    document.getElementById('total').textContent = agendamentos.length;

    if (agendamentos.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="6">Nenhum agendamento encontrado.</td></tr>';
        return;
    }

    let linhas = '';
    let diaAtual = '';
    let anoAtual = '';

    agendamentos.forEach(function (agendamento) {
        if (!agendamento || typeof agendamento.data_hora !== 'string') {
            throw new Error('A API retornou um agendamento sem data e horário. Confira se o backend e o banco veterinário foram atualizados.');
        }

        const partesDataHora = agendamento.data_hora.replace('T', ' ').split(' ');
        const dia = partesDataHora[0];
        const hora = partesDataHora[1];
        if (!dia || !hora) {
            throw new Error('A API retornou uma data e horário de agendamento inválidos.');
        }

        const ano = dia.slice(0, 4);
        if (ano !== anoAtual) {
            anoAtual = ano;
            diaAtual = '';
            linhas += `<tr class="linha-ano"><td colspan="6">${ano}</td></tr>`;
        }

        if (dia !== diaAtual) {
            diaAtual = dia;
            linhas += `<tr class="linha-dia"><td colspan="6">${tituloDoDia(dia)}</td></tr>`;
        }

        const peso = Number(agendamento.pet_peso).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        linhas += `
            <tr>
                <td><strong>${hora.slice(0, 5)}</strong></td>
                <td><span class="selo ${CLASSES_STATUS[agendamento.status]}">${NOMES_STATUS[agendamento.status]}</span></td>
                <td>
                    ${escapar(agendamento.procedimento)}
                    ${agendamento.observacoes ? '<br><small>' + escapar(agendamento.observacoes) + '</small>' : ''}
                    <br><small class="suave">Registrado por ${escapar(agendamento.usuario_nome)}</small>
                </td>
                <td>
                    <strong>${escapar(agendamento.pet_nome)}</strong>
                    <br><small>${escapar(agendamento.pet_especie)} - ${escapar(agendamento.pet_raca)}</small>
                    <br><small>Peso: ${peso} kg</small>
                    ${agendamento.pet_observacoes ? '<br><small>' + escapar(agendamento.pet_observacoes) + '</small>' : ''}
                </td>
                <td>
                    <strong>${escapar(agendamento.tutor_nome)}</strong>
                    <br><small>CPF: ${escapar(agendamento.tutor_cpf)}</small>
                    <br><small>Tel.: ${escapar(agendamento.tutor_telefone)}</small>
                    <br><small>${escapar(agendamento.tutor_email)}</small>
                    ${agendamento.tutor_endereco ? '<br><small>' + escapar(agendamento.tutor_endereco) + '</small>' : ''}
                </td>
                <td class="acoes-linha">
                    <button class="btn btn-pequeno" data-acao="editar" data-id="${agendamento.id_agendamento}">Editar</button>
                    <button class="btn btn-pequeno btn-perigo" data-acao="excluir" data-id="${agendamento.id_agendamento}">Excluir</button>
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
        if (!agendamento || typeof agendamento.data_hora !== 'string') {
            throw new Error('A API retornou um agendamento sem data e horário. Confira se o backend e o banco veterinário foram atualizados.');
        }
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
