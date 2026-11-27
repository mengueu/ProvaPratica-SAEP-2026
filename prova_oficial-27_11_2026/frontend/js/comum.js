const API = '../backend/api/';

async function chamarApi(arquivo, metodo = 'GET', dados = null) {
    const opcoes = {
        method: metodo,
        headers: { 'Content-Type': 'application/json' },
    };

    if (dados !== null) {
        opcoes.body = JSON.stringify(dados);
    }

    const resposta = await fetch(API + arquivo, opcoes);
    const json = await resposta.json();

    if (resposta.status === 401 && arquivo !== 'login.php' && arquivo !== 'sessao.php') {
        voltarParaLogin(json.erro);
    }

    if (!resposta.ok) {
        throw new Error(json.erro || 'Erro na requisição.');
    }

    return json;
}

function voltarParaLogin(mensagem) {
    window.location.href = 'index.html?aviso=' + encodeURIComponent(mensagem);
}

function mostrarMensagem(texto, tipo = 'sucesso') {
    const caixa = document.getElementById('mensagem');
    caixa.textContent = texto;
    caixa.className = 'aviso aviso-' + tipo;
    caixa.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapar(texto) {
    if (texto === null) {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

function formatarData(data) {
    if (data === null) {
        return '–';
    }
    return data.split('-').reverse().join('/');
}

function formatarDataHora(texto) {
    const partes = texto.split(' ');
    return formatarData(partes[0]) + ' ' + partes[1].slice(0, 5);
}

async function iniciarPagina() {
    let usuario;

    try {
        usuario = await chamarApi('sessao.php');
    } catch (erro) {
        voltarParaLogin(erro.message);
        return null;
    }

    document.getElementById('nome-usuario').textContent = usuario.nome;
    document.getElementById('botao-sair').addEventListener('click', sair);

    return usuario;
}

async function sair() {
    await chamarApi('logout.php', 'POST');
    window.location.href = 'index.html';
}
