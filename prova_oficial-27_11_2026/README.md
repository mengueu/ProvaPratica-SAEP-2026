# Sistema da Clínica Veterinária

Aplicação web para autenticação de usuários e gestão de tutores, pets e agendamentos de uma clínica veterinária.

O projeto utiliza PHP no backend, JavaScript no frontend e MySQL como banco de dados. Foi desenvolvido para execução no ambiente XAMPP.

## Funcionalidades

- Login por e-mail e senha com sessão PHP.
- Gestão de tutores:
  - cadastro, edição, exclusão e busca;
  - validação de CPF, telefone e e-mail;
  - proteção contra CPF duplicado;
  - CPF armazenado criptografado e exibido parcialmente mascarado nas listas.
- Gestão de pets:
  - cadastro, edição, exclusão e busca;
  - vínculo obrigatório com um tutor;
  - consulta por nome, espécie, raça, tutor ou observações.
- Gestão de agendamentos:
  - cadastro, edição, exclusão e filtros;
  - filtros por texto, dia e status;
  - status agendado, concluído ou cancelado;
  - prevenção de agendamento no passado e de dois horários iguais para o mesmo pet.
- Sessão com timeout de 30 minutos.
- Controle de exclusão por integridade referencial:
  - tutores com pets não podem ser excluídos;
  - pets com agendamentos não podem ser excluídos.

## Tecnologias

- PHP com PDO
- MySQL/MariaDB
- HTML5 e CSS3
- JavaScript
- Bootstrap Icons
- Apache pelo XAMPP

## Estrutura do projeto

```text
.
|-- backend/
|   |-- api/
|   |   |-- agendamentos.php
|   |   |-- login.php
|   |   |-- logout.php
|   |   |-- pets.php
|   |   |-- sessao.php
|   |   `-- tutores.php
|   |-- config.php
|   |-- conexao.php
|   |-- helpers.php
|   `-- seguranca.php
|-- frontend/
|   |-- css/
|   |   `-- style.css
|   |-- js/
|   |   |-- agendamentos.js
|   |   |-- comum.js
|   |   |-- login.js
|   |   |-- pets.js
|   |   |-- principal.js
|   |   `-- tutores.js
|   |-- agendamentos.html
|   |-- index.html
|   |-- pets.html
|   |-- principal.html
|   `-- tutores.html
`-- saep_db.sql
```

## Pré-requisitos

- XAMPP com Apache e MySQL/MariaDB.
- PHP compatível com PDO MySQL e OpenSSL.
- Navegador web moderno.

## Instalação e execução

1. Copie ou mantenha a pasta do projeto em `C:\xampp\htdocs\`.
2. Inicie Apache e MySQL pelo painel do XAMPP.
3. Abra o phpMyAdmin em `http://localhost/phpmyadmin`.
4. Importe o arquivo `saep_db.sql`.

O script cria o banco `saep_db_veterinaria`, as tabelas e dados de demonstração.

Depois, acesse:

```text
http://localhost/ProvaPraticaSAEP-2026-OFICIAL/frontend/index.html
```

## Configuração do banco

As configurações da conexão estão em `backend/conexao.php`:

```php
host: 127.0.0.1
porta: 3306
banco: saep_db_veterinaria
usuário: root
senha: vazia
```

Se o ambiente XAMPP estiver com usuário ou senha diferentes, ajuste o método `conectar()` neste arquivo.

## Usuários de demonstração

Os usuários abaixo são informados no arquivo `saep_db.sql`:

| Nome | E-mail | Senha |
|---|---|---|
| Dra. Mariana Costa | mariana@clinicavet | mariana1234 |
| Dr. Felipe Martins | felipe@clinicavet | felipe1234 |
| Dra. Aline Ferreira | aline@clinicavet | aline1234 |

Caso o acesso da Mariana não funcione, recrie o hash da senha no MySQL ou atualize o registro com `password_hash()` em PHP.

## Banco de dados

### usuário

Representa profissionais da clínica que podem entrar no sistema.

### tutor

Armazena os responsáveis pelos pets. O CPF é criptografado com AES-256-CBC e possui um hash HMAC usado em buscas e para impedir duplicidade.

### pet

Contém nome, espécie, raça, peso e observações. Cada pet pertence a um tutor.

### agendamento

Relaciona um pet a um usuário responsável pelo registro. Também guarda data, horário, procedimento, status e observações.

Relacões principais:

- um tutor pode possuir vários pets;
- um pet pode possuir vários agendamentos;
- um usuário pode registrar vários agendamentos.

## API PHP

Todas as APIs exigem login, exceto a autenticação. As respostas usam JSON.

| Recurso | Operações |
|---|---|
| `backend/api/login.php` | `POST` para autenticar |
| `backend/api/logout.php` | `POST` para encerrar a sessão |
| `backend/api/sessao.php` | `GET` para consultar o usuário autenticado |
| `backend/api/tutores.php` | `GET`, `POST`, `PUT` e `DELETE` |
| `backend/api/pets.php` | `GET`, `POST`, `PUT` e `DELETE` |
| `backend/api/agendamentos.php` | `GET`, `POST`, `PUT` e `DELETE` |

Os parâmetros `id`, `busca`, `dia` e `status` são usados conforme o recurso. Todos os dados enviados em cadastros e edições são enviados em JSON.

## Segurança

- As páginas protegidas consultam a sessão antes de carregar os dados.
- A API verifica o método HTTP e exige autenticação nas operações protegidas.
- Senhas de usuários são validadas com `password_verify()`.
- Consultas ao banco utilizam PDO com prepared statements.
- CPF possui criptografia AES-256-CBC e hash HMAC para buscas.
- Dados exibidos no HTML são escapados no frontend.
- Sessões expiram após 30 minutos de inatividade.

Antes de publicar em outro ambiente, substitua a chave definida em `backend/config.php` e ajuste as credenciais do banco.
