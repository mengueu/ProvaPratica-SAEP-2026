const formResponsavel = document.getElementById('form-responsavel');
const formBusca = document.getElementById('form-busca');
const corpoTabela = document.getElementById('lista-responsaveis');

const campos = ['nome', 'cpf', 'telefone', 'email', 'endereco'];


async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }
    carregarResponsaveis();
}

async function carregarResponsaveis() {
    const busca = document.getElementById('busca').value.trim();

    try {
        const responsaveis = await chamarApi('responsaveis.php?busca=' + encodeURIComponent(busca));
        desenharTabela(responsaveis);
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function desenharTabela(responsaveis) {
    document.getElementById('total').textContent = responsaveis.length;

    if (responsaveis.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="7">Nenhum responsável encontrado.</td></tr>';
        return;
    }

    let linhas = '';

    responsaveis.forEach(function (t) {
        linhas += `
            <tr>
                <td>${t.id_responsavel}</td>
                <td>${escapar(t.nome)}</td>
                <td>${escapar(t.cpf)}</td>
                <td>${escapar(t.telefone)}</td>
                <td>${escapar(t.email)}</td>
                <td>${escapar(t.endereco)}</td>
                <td class="acoes-linha">
                    <button class="btn btn-pequeno" data-acao="editar" data-id="${t.id_responsavel}">Editar</button>
                    <button class="btn btn-pequeno btn-perigo" data-acao="excluir" data-id="${t.id_responsavel}">Excluir</button>
                </td>
            </tr>`;
    });

    corpoTabela.innerHTML = linhas;
}


// Cadastrar ou atualizar (depende de existir um id no campo escondido)
formResponsavel.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const id = document.getElementById('id_responsavel').value;

    const dados = {};
    campos.forEach(function (campo) {
        dados[campo] = document.getElementById(campo).value;
    });

    try {
        let resposta;

        if (id === '') {
            resposta = await chamarApi('responsaveis.php', 'POST', dados);
        } else {
            resposta = await chamarApi('responsaveis.php?id=' + id, 'PUT', dados);
        }

        mostrarMensagem(resposta.mensagem);
        limparFormulario();
        carregarResponsaveis();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
});

corpoTabela.addEventListener('click', function (evento) {
    const acao = evento.target.dataset.acao;
    const id = evento.target.dataset.id;

    if (acao === 'editar') {
        editarResponsavel(id);
    }
    if (acao === 'excluir') {
        excluirResponsavel(id);
    }
});

async function editarResponsavel(id) {
    try {
        const responsavel = await chamarApi('responsaveis.php?id=' + id);

        campos.forEach(function (campo) {
            const valor = responsavel[campo];
            document.getElementById(campo).value = valor === null ? '' : valor;
        });

        document.getElementById('id_responsavel').value = id;
        document.getElementById('titulo-formulario').textContent = 'Editar responsável #' + id;
        document.getElementById('botao-salvar').textContent = 'Salvar alterações';
        document.getElementById('botao-cancelar').hidden = false;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function excluirResponsavel(id) {
    if (!confirm('Deseja realmente excluir este responsável?')) {
        return;
    }

    try {
        const resposta = await chamarApi('responsaveis.php?id=' + id, 'DELETE');
        mostrarMensagem(resposta.mensagem);
        carregarResponsaveis();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function limparFormulario() {
    formResponsavel.reset();
    document.getElementById('id_responsavel').value = '';
    document.getElementById('titulo-formulario').textContent = 'Novo responsável';
    document.getElementById('botao-salvar').textContent = 'Cadastrar responsável';
    document.getElementById('botao-cancelar').hidden = true;
}

document.getElementById('botao-cancelar').addEventListener('click', limparFormulario);

formBusca.addEventListener('submit', function (evento) {
    evento.preventDefault();
    carregarResponsaveis();
});

document.getElementById('botao-limpar-busca').addEventListener('click', function () {
    document.getElementById('busca').value = '';
    carregarResponsaveis();
});

iniciar();
