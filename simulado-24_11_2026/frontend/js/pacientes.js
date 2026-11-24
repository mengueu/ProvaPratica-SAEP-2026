const formPaciente = document.getElementById('form-paciente');
const formBusca = document.getElementById('form-busca');
const corpoTabela = document.getElementById('lista-pacientes');
const selectResponsavel = document.getElementById('id_responsavel');

const campos = ['nome', 'data_nascimento', 'id_responsavel', 'observacoes'];


async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }
    await carregarResponsaveis();
    carregarPacientes();
}

async function carregarResponsaveis() {
    try {
        const responsaveis = await chamarApi('responsaveis.php');

        responsaveis.forEach(function (r) {
            const opcao = document.createElement('option');
            opcao.value = r.id_responsavel;
            opcao.textContent = r.nome + ' – CPF ' + r.cpf;
            selectResponsavel.appendChild(opcao);
        });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function carregarPacientes() {
    const busca = document.getElementById('busca').value.trim();

    try {
        const pacientes = await chamarApi('pacientes.php?busca=' + encodeURIComponent(busca));
        desenharTabela(pacientes);
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function calcularIdade(nascimento) {
    const [ano, mes, dia] = nascimento.split('-').map(Number);
    const hoje = new Date();

    let anos = hoje.getFullYear() - ano;
    const aindaNaoFezAniversario = hoje.getMonth() + 1 < mes || (hoje.getMonth() + 1 === mes && hoje.getDate() < dia);
    if (aindaNaoFezAniversario) {
        anos--;
    }

    if (anos < 1) {
        const meses = (hoje.getFullYear() - ano) * 12 + (hoje.getMonth() + 1 - mes) - (hoje.getDate() < dia ? 1 : 0);
        return meses + (meses === 1 ? ' mês' : ' meses');
    }
    return anos + (anos === 1 ? ' ano' : ' anos');
}

function desenharTabela(pacientes) {
    document.getElementById('total').textContent = pacientes.length;

    if (pacientes.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="7">Nenhum paciente encontrado.</td></tr>';
        return;
    }

    let linhas = '';

    pacientes.forEach(function (p) {
        linhas += `
            <tr>
                <td>${p.id_paciente}</td>
                <td>${escapar(p.nome)}</td>
                <td>${formatarData(p.data_nascimento)}</td>
                <td>${calcularIdade(p.data_nascimento)}</td>
                <td>${escapar(p.observacoes)}</td>
                <td>${escapar(p.responsavel_nome)}<br><small>${escapar(p.responsavel_telefone)}</small></td>
                <td class="acoes-linha">
                    <button class="btn btn-pequeno" data-acao="editar" data-id="${p.id_paciente}">Editar</button>
                    <button class="btn btn-pequeno btn-perigo" data-acao="excluir" data-id="${p.id_paciente}">Excluir</button>
                </td>
            </tr>`;
    });

    corpoTabela.innerHTML = linhas;
}

formPaciente.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const id = document.getElementById('id_paciente').value;

    const dados = {};
    campos.forEach(function (campo) {
        dados[campo] = document.getElementById(campo).value;
    });

    try {
        let resposta;

        if (id === '') {
            resposta = await chamarApi('pacientes.php', 'POST', dados);
        } else {
            resposta = await chamarApi('pacientes.php?id=' + id, 'PUT', dados);
        }

        mostrarMensagem(resposta.mensagem);
        limparFormulario();
        carregarPacientes();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
});

corpoTabela.addEventListener('click', function (evento) {
    const acao = evento.target.dataset.acao;
    const id = evento.target.dataset.id;

    if (acao === 'editar') {
        editarPaciente(id);
    }
    if (acao === 'excluir') {
        excluirPaciente(id);
    }
});

async function editarPaciente(id) {
    try {
        const paciente = await chamarApi('pacientes.php?id=' + id);

        campos.forEach(function (campo) {
            const valor = paciente[campo];
            document.getElementById(campo).value = valor === null ? '' : valor;
        });

        document.getElementById('id_paciente').value = id;
        document.getElementById('titulo-formulario').textContent = 'Editar paciente #' + id;
        document.getElementById('botao-salvar').textContent = 'Salvar alterações';
        document.getElementById('botao-cancelar').hidden = false;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function excluirPaciente(id) {
    if (!confirm('Deseja realmente excluir este paciente?')) {
        return;
    }

    try {
        const resposta = await chamarApi('pacientes.php?id=' + id, 'DELETE');
        mostrarMensagem(resposta.mensagem);
        carregarPacientes();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function limparFormulario() {
    formPaciente.reset();
    document.getElementById('id_paciente').value = '';
    document.getElementById('titulo-formulario').textContent = 'Novo paciente';
    document.getElementById('botao-salvar').textContent = 'Cadastrar paciente';
    document.getElementById('botao-cancelar').hidden = true;
}

document.getElementById('botao-cancelar').addEventListener('click', limparFormulario);

formBusca.addEventListener('submit', function (evento) {
    evento.preventDefault();
    carregarPacientes();
});

document.getElementById('botao-limpar-busca').addEventListener('click', function () {
    document.getElementById('busca').value = '';
    carregarPacientes();
});

iniciar();
