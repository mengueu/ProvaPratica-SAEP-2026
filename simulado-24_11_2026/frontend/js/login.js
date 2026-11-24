const formLogin = document.getElementById('form-login');

const aviso = new URLSearchParams(window.location.search).get('aviso');
if (aviso) {
    mostrarMensagem(aviso, 'alerta');
}

formLogin.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    const dados = {
        email: document.getElementById('email').value,
        senha: document.getElementById('senha').value,
    };

    try {
        await chamarApi('login.php', 'POST', dados);
        window.location.href = 'principal.html';
    } catch (erro) {
        mostrarMensagem(erro.message, 'erro');
        document.getElementById('senha').value = '';
    }
});

// Quem já está logado vai direto para a tela principal
chamarApi('sessao.php')
    .then(function () {
        window.location.href = 'principal.html';
    })
    .catch(function () {
        // não está logado: continua na tela de login
    });
