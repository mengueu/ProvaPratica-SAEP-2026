const formTutor = document.getElementById('form-tutor');
const formBusca = document.getElementById('form-busca');
const corpoTabela = document.getElementById('lista-tutores');

const campos = ['nome', 'cpf', 'telefone', 'email', 'endereco'];


async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }
    carregarTutores();
}

async function carregarTutores() {
    const busca = document.getElementById('busca').value.trim();

    try {
        const tutores = await chamarApi('tutores.php?busca=' + encodeURIComponent(busca));
        desenharTabela(tutores);
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function desenharTabela(tutores) {
    document.getElementById('total').textContent = tutores.length;

    if (tutores.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="7">Nenhum tutor encontrado.</td></tr>';
        return;
    }

    let linhas = '';

    tutores.forEach(function (tutor) {
        linhas += `
            <tr>
                <td>${tutor.id_tutor}</td>
                <td>${escapar(tutor.nome)}</td>
                <td>${escapar(tutor.cpf)}</td>
                <td>${escapar(tutor.telefone)}</td>
                <td>${escapar(tutor.email)}</td>
                <td>${escapar(tutor.endereco)}</td>
                <td class="acoes-linha">
                    <button class="btn btn-pequeno" data-acao="editar" data-id="${tutor.id_tutor}">Editar</button>
                    <button class="btn btn-pequeno btn-perigo" data-acao="excluir" data-id="${tutor.id_tutor}">Excluir</button>
                </td>
            </tr>`;
    });

    corpoTabela.innerHTML = linhas;
}


formTutor.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const id = document.getElementById('id_tutor').value;
    const dados = {};
    campos.forEach(function (campo) {
        dados[campo] = document.getElementById(campo).value;
    });

    try {
        let resposta;
        if (id === '') {
            resposta = await chamarApi('tutores.php', 'POST', dados);
        } else {
            resposta = await chamarApi('tutores.php?id=' + id, 'PUT', dados);
        }

        mostrarMensagem(resposta.mensagem);
        limparFormulario();
        carregarTutores();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
});

corpoTabela.addEventListener('click', function (evento) {
    const acao = evento.target.dataset.acao;
    const id = evento.target.dataset.id;

    if (acao === 'editar') {
        editarTutor(id);
    }
    if (acao === 'excluir') {
        excluirTutor(id);
    }
});

async function editarTutor(id) {
    try {
        const tutor = await chamarApi('tutores.php?id=' + id);

        campos.forEach(function (campo) {
            const valor = tutor[campo];
            document.getElementById(campo).value = valor === null ? '' : valor;
        });

        document.getElementById('id_tutor').value = id;
        document.getElementById('titulo-formulario').textContent = 'Editar tutor #' + id;
        document.getElementById('botao-salvar').textContent = 'Salvar alterações';
        document.getElementById('botao-cancelar').hidden = false;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function excluirTutor(id) {
    if (!confirm('Deseja realmente excluir este tutor?')) {
        return;
    }

    try {
        const resposta = await chamarApi('tutores.php?id=' + id, 'DELETE');
        mostrarMensagem(resposta.mensagem);
        carregarTutores();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function limparFormulario() {
    formTutor.reset();
    document.getElementById('id_tutor').value = '';
    document.getElementById('titulo-formulario').textContent = 'Novo tutor';
    document.getElementById('botao-salvar').textContent = 'Cadastrar tutor';
    document.getElementById('botao-cancelar').hidden = true;
}

document.getElementById('botao-cancelar').addEventListener('click', limparFormulario);

formBusca.addEventListener('submit', function (evento) {
    evento.preventDefault();
    carregarTutores();
});

document.getElementById('botao-limpar-busca').addEventListener('click', function () {
    document.getElementById('busca').value = '';
    carregarTutores();
});

iniciar();
