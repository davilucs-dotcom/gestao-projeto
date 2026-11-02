-- Cria o armazenamento das falhas recentes de login.
USE sgp_ifpa;

CREATE TABLE IF NOT EXISTS login_tentativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    tentou_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_ip_tempo (email_hash, ip, tentou_em),
    INDEX idx_ip_tempo (ip, tentou_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @session_version_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'session_version'
);
SET @session_version_sql = IF(
    @session_version_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER senha_hash',
    'SELECT 1'
);
PREPARE session_version_statement FROM @session_version_sql;
EXECUTE session_version_statement;
DEALLOCATE PREPARE session_version_statement;

CREATE OR REPLACE VIEW vw_projetos_progresso AS
SELECT
    p.id AS projeto_id,
    COALESCE(
        ROUND(100 * SUM(r.data_entrega IS NOT NULL) / NULLIF(COUNT(r.id), 0)),
        0
    ) AS progresso
FROM projetos AS p
LEFT JOIN relatorios AS r ON r.projeto_id = p.id
GROUP BY p.id;

-- Bancos migrados podem manter projetos.progresso por compatibilidade; o valor
-- persistido fica obsoleto e a aplicacao consulta somente esta view.
