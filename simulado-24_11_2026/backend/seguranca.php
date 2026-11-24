<?php
function chave_aes()
{
    return hash('sha256', CHAVE_SECRETA, true);
}

function criptografar($texto)
{
    $iv = random_bytes(16);
    $cifrado = openssl_encrypt($texto, 'aes-256-cbc', chave_aes(), OPENSSL_RAW_DATA, $iv);

    return base64_encode($iv . $cifrado);
}

function descriptografar($valor)
{
    $dados = base64_decode($valor);
    $iv = substr($dados, 0, 16);
    $cifrado = substr($dados, 16);

    return openssl_decrypt($cifrado, 'aes-256-cbc', chave_aes(), OPENSSL_RAW_DATA, $iv);
}

function hash_cpf($cpf)
{
    return hash_hmac('sha256', $cpf, CHAVE_SECRETA);
}

// 12345678909 -> 123.456.789-09
function formatar_cpf($cpf)
{
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

// Nas listagens o CPF aparece parcialmente escondido: ***.456.789-**
function cpf_para_listagem($cpf_criptografado)
{
    $cpf = descriptografar($cpf_criptografado);

    if ($cpf === false) {
        return '***.***.***-**';
    }

    return '***.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
}
