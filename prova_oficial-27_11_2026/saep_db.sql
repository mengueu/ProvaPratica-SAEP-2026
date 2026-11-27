SET NAMES utf8mb4;

DROP DATABASE IF EXISTS saep_db_veterinaria;
CREATE DATABASE saep_db_veterinaria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE saep_db_veterinaria;

CREATE TABLE usuario (
  id_usuario  INT NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB;

-- Logins de teste:
--   mariana@clinicavet // mariana1234
--   felipe@clinicavet // felipe1234
--   aline@clinicavet // aline1234
INSERT INTO usuario (nome, email, senha_hash) VALUES
  ('Dra. Mariana Costa', 'mariana@clinicavet', 'a$2y$10$v/n2zBO.Hhh5aN38U3.y5OK4KRGPHoJqX4rggN5bYGm7mqN4vlhZm'),
  ('Dr. Felipe Martins', 'felipe@clinicavet', '$2y$10$4lXW8WmBVk2C5n65s18i0uRclvale8NK6Q39CWOt2RYQpqzs8.V7u'),
  ('Dra. Aline Ferreira', 'aline@clinicavet', '$2y$10$el2IjYeTUc6u.4SA4ctqTeKo6RsRK1dl894eVpy0eD3k8aI.5sVMu');

CREATE TABLE tutor (
  id_tutor  INT NOT NULL AUTO_INCREMENT,
  nome      VARCHAR(100) NOT NULL,
  cpf       VARCHAR(255) NOT NULL,
  cpf_hash  CHAR(64) NOT NULL,
  telefone  VARCHAR(20) NOT NULL,
  email     VARCHAR(100) NOT NULL,
  endereco  VARCHAR(200) NULL,
  PRIMARY KEY (id_tutor),
  UNIQUE KEY uq_tutor_cpf_hash (cpf_hash)
) ENGINE=InnoDB;

INSERT INTO tutor (nome, cpf, cpf_hash, telefone, email, endereco) VALUES
  ('Patrícia Moura', 'r7p7ss127sjhbfht8hNNMf3mzwCgB87ESgmk287pSqY=',
   '1dd985a41dab24a8dae01eaba92984dd212c1f748c3397adc4a33bb8bef8ba3d',
   '(11) 98811-2020', 'patricia.moura@email.com', 'Rua das Acácias, 210 - São Paulo/SP'),
  ('Eduardo Lima', 'eHCJpvr4g5PEK4zSgq8t3v8FSEo5tx+5mkCdl8258o8=',
   'f9ed4e5527a09d9613f384656921df608ec36aabeea3fcbf76f253a6b9d1191d',
   '(11) 97722-3030', 'eduardo.lima@email.com', 'Av. Brasil, 1500 - Guarulhos/SP'),
  ('Sandra Ribeiro', 'YyQBYmTEJVwlH6wZR/bdO58so0Ypu5Jkb1WUKpeghaU=',
   '2fff1668aeacf4738d3c264a211d14900710273e7df4f6e782db8df517039ffc',
   '(11) 96633-4040', 'sandra.ribeiro@email.com', 'Rua das Palmeiras, 88 - Osasco/SP'),
  ('Fábio Carvalho', 'iaL+Yl0S4+hWrpXKpFrNQUbB5Uxzae+mqHRtekbN1yM=',
   '9b111b04c66cbff1112ac5894f134c55a7511763c76883a6ea1e4e4ecf5be5e4',
   '(11) 95544-5050', 'fabio.carvalho@email.com', NULL);

CREATE TABLE pet (
  id_pet       INT NOT NULL AUTO_INCREMENT,
  nome         VARCHAR(100) NOT NULL,
  especie      VARCHAR(50) NOT NULL,
  raca         VARCHAR(100) NOT NULL,
  peso         DECIMAL(7,2) NOT NULL,
  observacoes  VARCHAR(255) NULL,
  id_tutor     INT NOT NULL,
  PRIMARY KEY (id_pet),
  KEY idx_pet_tutor (id_tutor),
  CONSTRAINT fk_pet_tutor FOREIGN KEY (id_tutor)
    REFERENCES tutor (id_tutor)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO pet (nome, especie, raca, peso, observacoes, id_tutor) VALUES
  ('Bidu', 'Cachorro', 'Labrador', 28.50, 'Alergia a alguns alimentos',
   (SELECT id_tutor FROM tutor WHERE email = 'patricia.moura@email.com')),
  ('Mel', 'Gato', 'Siamês', 4.20, NULL,
   (SELECT id_tutor FROM tutor WHERE email = 'patricia.moura@email.com')),
  ('Thor', 'Cachorro', 'Golden Retriever', 31.00, 'Usa medicação contínua',
   (SELECT id_tutor FROM tutor WHERE email = 'eduardo.lima@email.com')),
  ('Luna', 'Gato', 'SRD', 3.80, NULL,
   (SELECT id_tutor FROM tutor WHERE email = 'sandra.ribeiro@email.com')),
  ('Nina', 'Coelho', 'Mini Lop', 1.65, 'Fica agitada durante o transporte',
   (SELECT id_tutor FROM tutor WHERE email = 'fabio.carvalho@email.com'));

CREATE TABLE agendamento (
  id_agendamento  INT NOT NULL AUTO_INCREMENT,
  data_hora       DATETIME NOT NULL,
  procedimento    VARCHAR(100) NOT NULL,
  status          ENUM('agendado','concluido','cancelado') NOT NULL DEFAULT 'agendado',
  observacoes     VARCHAR(255) NULL,
  id_pet          INT NOT NULL,
  id_usuario      INT NOT NULL,
  PRIMARY KEY (id_agendamento),
  UNIQUE KEY uq_agendamento (id_pet, data_hora),
  KEY idx_agendamento_data (data_hora),
  CONSTRAINT fk_agendamento_pet FOREIGN KEY (id_pet)
    REFERENCES pet (id_pet)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_agendamento_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO agendamento (data_hora, procedimento, status, observacoes, id_pet, id_usuario) VALUES
  ('2023-04-15 09:00:00', 'Consulta de rotina', 'concluido', NULL,
   (SELECT id_pet FROM pet WHERE nome = 'Bidu'),
   (SELECT id_usuario FROM usuario WHERE email = 'mariana@clinicavet')),
  ('2023-10-21 14:30:00', 'Avaliação veterinária', 'concluido', 'Retorno de acompanhamento',
   (SELECT id_pet FROM pet WHERE nome = 'Thor'),
   (SELECT id_usuario FROM usuario WHERE email = 'aline@clinicavet')),
  ('2024-02-12 08:30:00', 'Vacinação', 'concluido', NULL,
   (SELECT id_pet FROM pet WHERE nome = 'Luna'),
   (SELECT id_usuario FROM usuario WHERE email = 'felipe@clinicavet')),
  ('2024-09-05 13:15:00', 'Consulta de rotina', 'concluido', NULL,
   (SELECT id_pet FROM pet WHERE nome = 'Mel'),
   (SELECT id_usuario FROM usuario WHERE email = 'mariana@clinicavet')),
  ('2025-03-20 10:00:00', 'Exame clínico', 'concluido', 'Acompanhar evolução do peso',
   (SELECT id_pet FROM pet WHERE nome = 'Thor'),
   (SELECT id_usuario FROM usuario WHERE email = 'aline@clinicavet')),
  ('2025-11-18 15:00:00', 'Avaliação veterinária', 'concluido', 'Retorno de acompanhamento',
   (SELECT id_pet FROM pet WHERE nome = 'Nina'),
   (SELECT id_usuario FROM usuario WHERE email = 'felipe@clinicavet')),
  ('2026-03-14 09:30:00', 'Consulta de rotina', 'concluido', NULL,
   (SELECT id_pet FROM pet WHERE nome = 'Bidu'),
   (SELECT id_usuario FROM usuario WHERE email = 'mariana@clinicavet')),
  ('2026-11-28 10:00:00', 'Vacinação', 'agendado', NULL,
   (SELECT id_pet FROM pet WHERE nome = 'Luna'),
   (SELECT id_usuario FROM usuario WHERE email = 'felipe@clinicavet')),
  ('2027-01-12 11:00:00', 'Consulta de rotina', 'agendado', NULL,
   (SELECT id_pet FROM pet WHERE nome = 'Mel'),
   (SELECT id_usuario FROM usuario WHERE email = 'mariana@clinicavet')),
  ('2027-06-24 16:00:00', 'Avaliação veterinária', 'agendado', 'Revisão clínica anual',
   (SELECT id_pet FROM pet WHERE nome = 'Nina'),
   (SELECT id_usuario FROM usuario WHERE email = 'aline@clinicavet'));
