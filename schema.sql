CREATE DATABASE IF NOT EXISTS sgp_ifpa
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
SET NAMES utf8mb4;
USE sgp_ifpa;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    matricula VARCHAR(30) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    session_version INT UNSIGNED NOT NULL DEFAULT 0,
    perfil ENUM('Docente', 'Discente', 'Tecnico') NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE projetos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    resumo TEXT NULL,
    modalidade ENUM('Ensino', 'Pesquisa', 'Extensao') NOT NULL,
    coordenador_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status ENUM('Em andamento', 'Concluido', 'Suspenso') NOT NULL DEFAULT 'Em andamento',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_datas CHECK (DATEDIFF(data_fim, data_inicio) >= 7),
    CONSTRAINT fk_proj_coord FOREIGN KEY (coordenador_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_projetos_modalidade (modalidade),
    INDEX idx_projetos_status (status)
) ENGINE=InnoDB;

CREATE TABLE equipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    papel ENUM('Coordenador', 'Bolsista', 'Voluntario', 'Colaborador') NOT NULL,
    carga_horaria_semanal TINYINT UNSIGNED NOT NULL DEFAULT 4,
    data_entrada DATE NOT NULL,
    CONSTRAINT chk_carga_horaria CHECK (carga_horaria_semanal BETWEEN 1 AND 40),
    CONSTRAINT fk_eq_proj FOREIGN KEY (projeto_id)
        REFERENCES projetos(id) ON DELETE CASCADE,
    CONSTRAINT fk_eq_user FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT uk_membro_projeto UNIQUE (projeto_id, usuario_id),
    INDEX idx_equipes_usuario (usuario_id)
) ENGINE=InnoDB;

CREATE TABLE relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    tipo ENUM('Parcial', 'Final') NOT NULL,
    data_limite DATE NOT NULL,
    data_entrega DATE NULL,
    arquivo_path VARCHAR(255) NULL,
    CONSTRAINT fk_rel_proj FOREIGN KEY (projeto_id)
        REFERENCES projetos(id) ON DELETE CASCADE,
    CONSTRAINT uk_relatorio_tipo UNIQUE (projeto_id, tipo),
    INDEX idx_relatorios_prazo (data_limite)
) ENGINE=InnoDB;

DELIMITER //
CREATE TRIGGER trg_validar_coordenador_projeto
BEFORE INSERT ON projetos
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM usuarios
        WHERE id = NEW.coordenador_id AND perfil = 'Docente'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'O coordenador do projeto deve ser um usuario Docente';
    END IF;
END //

CREATE TRIGGER trg_projeto_apos_insert
AFTER INSERT ON projetos
FOR EACH ROW
BEGIN
    INSERT INTO equipes (projeto_id, usuario_id, papel, carga_horaria_semanal, data_entrada)
    VALUES (NEW.id, NEW.coordenador_id, 'Coordenador', 8, NEW.data_inicio);

    INSERT INTO relatorios (projeto_id, tipo, data_limite)
    VALUES (
        NEW.id,
        'Parcial',
        DATE_ADD(NEW.data_inicio, INTERVAL FLOOR(DATEDIFF(NEW.data_fim, NEW.data_inicio) / 2) DAY)
    );

    INSERT INTO relatorios (projeto_id, tipo, data_limite)
    VALUES (NEW.id, 'Final', NEW.data_fim);
END //
DELIMITER ;

CREATE OR REPLACE VIEW vw_projetos_por_modalidade AS
SELECT modalidade, COUNT(*) AS total
FROM projetos
GROUP BY modalidade;

CREATE OR REPLACE VIEW vw_relatorios_situacao AS
SELECT
    r.id,
    r.projeto_id,
    r.tipo,
    r.data_limite,
    r.data_entrega,
    r.arquivo_path,
    CASE
        WHEN r.data_entrega IS NOT NULL THEN 'Entregue'
        WHEN r.data_limite < CURDATE() THEN 'Atrasado'
        ELSE 'Pendente'
    END AS situacao
FROM relatorios AS r;

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
