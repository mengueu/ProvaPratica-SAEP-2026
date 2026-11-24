async function iniciar() {
    const usuario = await iniciarPagina();
    if (usuario === null) {
        return;
    }

    document.getElementById('saudacao').textContent = 'Olá, ' + usuario.nome;
}

iniciar();
