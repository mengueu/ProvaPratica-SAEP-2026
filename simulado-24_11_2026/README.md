# Clínica Odontológica – Código base

Este pacote traz o **login**, o **cadastro de responsáveis**, o **cadastro de pacientes** e os **agendamentos**, todos com CRUD completo.

## Como executar (XAMPP)

1. Copie esta pasta para dentro de `C:\xampp\htdocs\`.
2. Inicie **Apache** e **MySQL** no painel do XAMPP.
3. Em `http://localhost/phpmyadmin`, aba **Importar**, escolha `banco_base.sql`.
   Ele cria o banco `saep_db_odontologia` com as tabelas `usuario`, `responsavel`, `paciente` e `agendamento`.
   O script pode ser importado mais de uma vez sem duplicar os dados de teste.
4. Abra a pasta `frontend` pelo navegador, por exemplo:
   `http://localhost/<nome-da-pasta>/frontend/`

Confira a porta do MySQL no painel do XAMPP. Se for diferente da que está em
`backend/conexao.php`, ajuste a linha da conexão.

### Logins de teste

| E-mail | Senha |
|---|---|
| camila@clinicaodonto.com | camila123 |
| ricardo@clinicaodonto.com | ricardo123 |
| beatriz@clinicaodonto.com | beatriz123 |

## Estrutura

```
backend/
  config.php        chave usada para criptografar o CPF
  conexao.php       conexão PDO com o banco saep_db_odontologia
  helpers.php       sessão, respostas JSON, ler_json(), exigir_login()
  seguranca.php     criptografia e máscara do CPF (LGPD)
  api/
    login.php         POST {email, senha}: abre a sessão
    sessao.php        GET: informa o usuário logado (ou 401)
    logout.php        POST: encerra a sessão
    responsaveis.php  GET (lista e busca), GET ?id=, POST, PUT ?id=, DELETE ?id=
    pacientes.php     GET (lista e busca), GET ?id=, POST, PUT ?id=, DELETE ?id=
    agendamentos.php  GET (lista ordenada por data/horário; ?busca= ?dia= ?status=),
                      GET ?id=, POST, PUT ?id=, DELETE ?id=
frontend/
  index.html          tela de login
  principal.html      tela inicial, com atalhos
  responsaveis.html   cadastro, busca, edição e exclusão de responsáveis
  pacientes.html      cadastro, busca, edição e exclusão de pacientes
  agendamentos.html   agenda: cadastro, filtros, remarcação, edição e exclusão
  css/style.css       estilos do sistema
  js/                 comum.js, login.js, principal.js, responsaveis.js,
                      pacientes.js e agendamentos.js
banco_base.sql        tabelas usuario, responsavel, paciente e agendamento, com dados de teste
```

## Banco de dados

```
usuario 1 ──< agendamento >── 1 paciente >── 1 responsavel
```

- **paciente**: `nome`, `data_nascimento`, `observacoes`, `id_responsavel` (obrigatório).
  Não repete o mesmo nome + nascimento para o mesmo responsável.
- **agendamento**: `data_hora` (data e horário), `procedimento`, `status`
  (`agendado`, `concluido`, `cancelado`), `observacoes`, `id_paciente`, `id_usuario`
  (quem registrou, vem do login). O mesmo paciente não pode ter dois agendamentos no mesmo horário.
- As chaves estrangeiras usam `ON DELETE RESTRICT`: não dá para excluir um responsável que
  tem pacientes, nem um paciente que tem agendamentos (a API devolve uma mensagem explicando).

## Regras implementadas

- Paciente: nome e data de nascimento obrigatórios; nascimento não pode ser no futuro; responsável obrigatório e existente.
- Agendamento: paciente, data/horário, procedimento e status obrigatórios; não se agenda (nem se remarca)
  para um horário que já passou, mas dá para marcar como concluído/cancelado um agendamento antigo.
- **Remarcar** = editar o agendamento e trocar o campo "Data e horário" (`UPDATE` de `data_hora`).
- A listagem de agendamentos vem ordenada por `data_hora`, agrupada por dia, com todos os dados do
  responsável e do paciente. O CPF do responsável aparece parcialmente escondido (LGPD), como na tela de responsáveis.
