-- ============================================================
--  banco.sql — Script do banco de dados do Dr. Zequel Montor
--  Execute este script UMA VEZ para criar as tabelas
--  Compatível com MySQL 5.7+ e MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS auditor_fiscal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE auditor_fiscal;

-- ------------------------------------------------------------
-- Sessões de auditoria
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessoes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token         VARCHAR(64)  NOT NULL UNIQUE,          -- token único da sessão (cookie)
  ip            VARCHAR(45)  NOT NULL,                 -- IPv4 ou IPv6
  criado_em     DATETIME     NOT NULL DEFAULT NOW(),
  atualizado_em DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
  encerrado_em  DATETIME     NULL,
  risco         ENUM('verde','amarelo','vermelho') NOT NULL DEFAULT 'verde',
  resumo        TEXT         NULL                      -- resumo gerado ao encerrar
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Mensagens de cada sessão
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mensagens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sessao_id  INT UNSIGNED NOT NULL,
  papel      ENUM('user','assistant') NOT NULL,
  conteudo   MEDIUMTEXT   NOT NULL,                    -- texto da mensagem
  tem_arquivo TINYINT(1)  NOT NULL DEFAULT 0,
  arquivo_nome VARCHAR(255) NULL,
  arquivo_tipo VARCHAR(100) NULL,
  criado_em  DATETIME     NOT NULL DEFAULT NOW(),
  FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
  INDEX idx_sessao (sessao_id),
  INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Rate limiting por IP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limit (
  ip           VARCHAR(45)  NOT NULL,
  janela       INT UNSIGNED NOT NULL,                  -- timestamp arredondado por hora
  requisicoes  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (ip, janela)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Log de acessos (opcional — para monitoramento)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS log_acesso (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip        VARCHAR(45) NOT NULL,
  sessao_id INT UNSIGNED NULL,
  acao      VARCHAR(50) NOT NULL,
  criado_em DATETIME    NOT NULL DEFAULT NOW(),
  INDEX idx_ip (ip),
  INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Limpeza automática (event agendado — opcional)
-- Remove sessões antigas e rate_limit expirado
-- Descomente se o seu servidor tiver event scheduler habilitado
-- ------------------------------------------------------------
-- SET GLOBAL event_scheduler = ON;
-- 
-- CREATE EVENT IF NOT EXISTS limpar_dados_antigos
--   ON SCHEDULE EVERY 1 DAY
--   DO BEGIN
--     DELETE FROM sessoes    WHERE criado_em  < NOW() - INTERVAL 90 DAY;
--     DELETE FROM rate_limit WHERE janela     < UNIX_TIMESTAMP() - 7200;
--     DELETE FROM log_acesso WHERE criado_em  < NOW() - INTERVAL 30 DAY;
--   END;

SELECT 'Banco de dados criado com sucesso!' AS status;
