SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS saep_db_odontologia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE saep_db_odontologia;

CREATE TABLE IF NOT EXISTS usuario (
  id_usuario  INT NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,          
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB;

-- Logins de teste (e-mail / senha):
--   camila@clinicaodonto.com  / camila123
--   ricardo@clinicaodonto.com / ricardo123
--   beatriz@clinicaodonto.com / beatriz123
INSERT IGNORE INTO usuario (nome, email, senha_hash) VALUES
  ('Dra. Camila Duarte', 'camila@clinicaodonto.com',  '$2y$10$IjNxO30xuxOeUol1eEDMQuTi.zv5n7a..9Xpv.gM2dFYXBZmA4hhy'),
  ('Ricardo Alves',      'ricardo@clinicaodonto.com', '$2y$10$i8GXdFTx0wO/X.wPVECLX.waS83Jns3ebOGUSy2cMbLPb4W.Q9FZS'),
  ('Beatriz Nogueira',   'beatriz@clinicaodonto.com', '$2y$10$HlMw9ni08DACJjpk7yo4Jexxhsg9kYD.vmQuef8iBvieDx0T2684m');

CREATE TABLE IF NOT EXISTS responsavel (
  id_responsavel  INT NOT NULL AUTO_INCREMENT,
  nome            VARCHAR(100) NOT NULL,
  cpf             VARCHAR(255) NOT NULL,
  cpf_hash        CHAR(64)     NOT NULL,
  telefone        VARCHAR(20)  NOT NULL,
  email           VARCHAR(100) NOT NULL,
  endereco        VARCHAR(200) NULL,
  PRIMARY KEY (id_responsavel),
  UNIQUE KEY uq_responsavel_cpf_hash (cpf_hash)
) ENGINE=InnoDB;

INSERT IGNORE INTO responsavel (nome, cpf, cpf_hash, telefone, email, endereco) VALUES
  ('Patrícia Moura',  'r7p7ss127sjhbfht8hNNMf3mzwCgB87ESgmk287pSqY=',
     '1dd985a41dab24a8dae01eaba92984dd212c1f748c3397adc4a33bb8bef8ba3d',
     '(11) 98811-2020', 'patricia.moura@email.com', 'Rua das Acácias, 210 - São Paulo/SP'),
  ('Eduardo Lima',    'eHCJpvr4g5PEK4zSgq8t3v8FSEo5tx+5mkCdl8258o8=',
     'f9ed4e5527a09d9613f384656921df608ec36aabeea3fcbf76f253a6b9d1191d',
     '(11) 97722-3030', 'eduardo.lima@email.com', 'Av. Brasil, 1500 - Guarulhos/SP'),
  ('Sandra Ribeiro',  'YyQBYmTEJVwlH6wZR/bdO58so0Ypu5Jkb1WUKpeghaU=',
     '2fff1668aeacf4738d3c264a211d14900710273e7df4f6e782db8df517039ffc',
     '(11) 96633-4040', 'sandra.ribeiro@email.com', 'Rua das Palmeiras, 88 - Osasco/SP'),
  ('Fábio Carvalho',  'iaL+Yl0S4+hWrpXKpFrNQUbB5Uxzae+mqHRtekbN1yM=',
     '9b111b04c66cbff1112ac5894f134c55a7511763c76883a6ea1e4e4ecf5be5e4',
     '(11) 95544-5050', 'fabio.carvalho@email.com', NULL);

CREATE TABLE IF NOT EXISTS paciente (
  id_paciente      INT NOT NULL AUTO_INCREMENT,
  nome             VARCHAR(100) NOT NULL,
  data_nascimento  DATE         NOT NULL,
  observacoes      VARCHAR(255) NULL,
  id_responsavel   INT          NOT NULL,
  PRIMARY KEY (id_paciente),
  UNIQUE KEY uq_paciente (id_responsavel, nome, data_nascimento),
  CONSTRAINT fk_paciente_responsavel FOREIGN KEY (id_responsavel)
    REFERENCES responsavel (id_responsavel)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO paciente (nome, data_nascimento, observacoes, id_responsavel) VALUES
  ('Lucas Moura',    '2015-03-12', 'Alergia a látex',
     (SELECT id_responsavel FROM responsavel WHERE email = 'patricia.moura@email.com')),
  ('Helena Moura',   '2018-07-30', NULL,
     (SELECT id_responsavel FROM responsavel WHERE email = 'patricia.moura@email.com')),
  ('Pedro Lima',     '2012-11-05', 'Usa aparelho ortodôntico',
     (SELECT id_responsavel FROM responsavel WHERE email = 'eduardo.lima@email.com')),
  ('Sofia Ribeiro',  '2016-01-22', NULL,
     (SELECT id_responsavel FROM responsavel WHERE email = 'sandra.ribeiro@email.com')),
  ('Miguel Carvalho','2010-09-14', 'Ansiedade em consultas',
     (SELECT id_responsavel FROM responsavel WHERE email = 'fabio.carvalho@email.com'));

CREATE TABLE IF NOT EXISTS agendamento (
  id_agendamento  INT NOT NULL AUTO_INCREMENT,
  data_hora       DATETIME     NOT NULL,
  procedimento    VARCHAR(100) NOT NULL,
  status          ENUM('agendado','concluido','cancelado') NOT NULL DEFAULT 'agendado',
  observacoes     VARCHAR(255) NULL,
  id_paciente     INT NOT NULL,
  id_usuario      INT NOT NULL,
  PRIMARY KEY (id_agendamento),
  UNIQUE KEY uq_agendamento (id_paciente, data_hora),
  KEY idx_agendamento_data (data_hora),
  CONSTRAINT fk_agendamento_paciente FOREIGN KEY (id_paciente)
    REFERENCES paciente (id_paciente)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_agendamento_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO agendamento (data_hora, procedimento, status, observacoes, id_paciente, id_usuario) VALUES
  (TIMESTAMP(CURDATE() - INTERVAL 7 DAY, '09:00:00'), 'Limpeza', 'concluido', NULL,
     (SELECT id_paciente FROM paciente WHERE nome = 'Lucas Moura'),
     (SELECT id_usuario FROM usuario WHERE email = 'camila@clinicaodonto.com')),
  (TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '14:30:00'), 'Avaliação ortodôntica', 'cancelado', 'Responsável avisou que não poderia vir',
     (SELECT id_paciente FROM paciente WHERE nome = 'Pedro Lima'),
     (SELECT id_usuario FROM usuario WHERE email = 'beatriz@clinicaodonto.com')),
  (TIMESTAMP(CURDATE(), '10:00:00'), 'Restauração', 'agendado', NULL,
     (SELECT id_paciente FROM paciente WHERE nome = 'Sofia Ribeiro'),
     (SELECT id_usuario FROM usuario WHERE email = 'ricardo@clinicaodonto.com')),
  (TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '08:30:00'), 'Consulta de rotina', 'agendado', NULL,
     (SELECT id_paciente FROM paciente WHERE nome = 'Helena Moura'),
     (SELECT id_usuario FROM usuario WHERE email = 'camila@clinicaodonto.com')),
  (TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '15:00:00'), 'Manutenção do aparelho', 'agendado', 'Trocar borrachinhas',
     (SELECT id_paciente FROM paciente WHERE nome = 'Pedro Lima'),
     (SELECT id_usuario FROM usuario WHERE email = 'beatriz@clinicaodonto.com')),
  (TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '11:00:00'), 'Aplicação de flúor', 'agendado', NULL,
     (SELECT id_paciente FROM paciente WHERE nome = 'Miguel Carvalho'),
     (SELECT id_usuario FROM usuario WHERE email = 'ricardo@clinicaodonto.com'));