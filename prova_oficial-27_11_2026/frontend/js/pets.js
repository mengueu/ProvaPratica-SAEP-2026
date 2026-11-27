const formPet = document.getElementById('form-pet');
const formBusca = document.getElementById('form-busca');
const corpoTabela = document.getElementById('lista-pets');
const selectTutor = document.getElementById('id_tutor');

const campos = ['nome', 'especie', 'raca', 'peso', 'id_tutor', 'observacoes'];


async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }
    await carregarTutores();
    carregarPets();
}

async function carregarTutores() {
    try {
        const tutores = await chamarApi('tutores.php');

        tutores.forEach(function (tutor) {
            const opcao = document.createElement('option');
            opcao.value = tutor.id_tutor;
            opcao.textContent = tutor.nome + ' - CPF ' + tutor.cpf;
            selectTutor.appendChild(opcao);
        });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function carregarPets() {
    const busca = document.getElementById('busca').value.trim();

    try {
        const pets = await chamarApi('pets.php?busca=' + encodeURIComponent(busca));
        desenharTabela(pets);
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function desenharTabela(pets) {
    document.getElementById('total').textContent = pets.length;

    if (pets.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="8">Nenhum pet encontrado.</td></tr>';
        return;
    }

    let linhas = '';

    pets.forEach(function (pet) {
        const peso = Number(pet.peso).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        linhas += `
            <tr>
                <td>${pet.id_pet}</td>
                <td>${escapar(pet.nome)}</td>
                <td>${escapar(pet.especie)}</td>
                <td>${escapar(pet.raca)}</td>
                <td>${peso} kg</td>
                <td>${escapar(pet.observacoes)}</td>
                <td>${escapar(pet.tutor_nome)}<br><small>${escapar(pet.tutor_telefone)}</small></td>
                <td class="acoes-linha">
                    <button class="btn btn-pequeno" data-acao="editar" data-id="${pet.id_pet}">Editar</button>
                    <button class="btn btn-pequeno btn-perigo" data-acao="excluir" data-id="${pet.id_pet}">Excluir</button>
                </td>
            </tr>`;
    });

    corpoTabela.innerHTML = linhas;
}


formPet.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const id = document.getElementById('id_pet').value;
    const dados = {};
    campos.forEach(function (campo) {
        dados[campo] = document.getElementById(campo).value;
    });

    try {
        let resposta;
        if (id === '') {
            resposta = await chamarApi('pets.php', 'POST', dados);
        } else {
            resposta = await chamarApi('pets.php?id=' + id, 'PUT', dados);
        }

        mostrarMensagem(resposta.mensagem);
        limparFormulario();
        carregarPets();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
});

corpoTabela.addEventListener('click', function (evento) {
    const acao = evento.target.dataset.acao;
    const id = evento.target.dataset.id;

    if (acao === 'editar') {
        editarPet(id);
    }
    if (acao === 'excluir') {
        excluirPet(id);
    }
});

async function editarPet(id) {
    try {
        const pet = await chamarApi('pets.php?id=' + id);

        campos.forEach(function (campo) {
            const valor = pet[campo];
            document.getElementById(campo).value = valor === null ? '' : valor;
        });

        document.getElementById('id_pet').value = id;
        document.getElementById('titulo-formulario').textContent = 'Editar pet #' + id;
        document.getElementById('botao-salvar').textContent = 'Salvar alterações';
        document.getElementById('botao-cancelar').hidden = false;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

async function excluirPet(id) {
    if (!confirm('Deseja realmente excluir este pet?')) {
        return;
    }

    try {
        const resposta = await chamarApi('pets.php?id=' + id, 'DELETE');
        mostrarMensagem(resposta.mensagem);
        carregarPets();
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
    }
}

function limparFormulario() {
    formPet.reset();
    document.getElementById('id_pet').value = '';
    document.getElementById('titulo-formulario').textContent = 'Novo pet';
    document.getElementById('botao-salvar').textContent = 'Cadastrar pet';
    document.getElementById('botao-cancelar').hidden = true;
}

document.getElementById('botao-cancelar').addEventListener('click', limparFormulario);

formBusca.addEventListener('submit', function (evento) {
    evento.preventDefault();
    carregarPets();
});

document.getElementById('botao-limpar-busca').addEventListener('click', function () {
    document.getElementById('busca').value = '';
    carregarPets();
});

iniciar();
