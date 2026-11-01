-- Faça backup do banco sgp_ifpa antes de executar.
-- Faça backup do banco sgp_ifpa antes de executar.
USE sgp_ifpa;

-- Pré-valide: projetos abaixo de 7 dias e equipes com carga fora de 1..40.
SELECT id, titulo, data_inicio, data_fim
FROM projetos
WHERE DATEDIFF(data_fim, data_inicio) < 7;

SELECT id, projeto_id, usuario_id, carga_horaria_semanal
FROM equipes
WHERE carga_horaria_semanal NOT BETWEEN 1 AND 40;

-- Mantém as colunas antigas para não descartar dados durante a transição.
ALTER TABLE usuarios
    ADD COLUMN senha_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN matricula VARCHAR(30) NULL AFTER matricula_siape;

UPDATE usuarios SET matricula = matricula_siape WHERE matricula IS NULL;
ALTER TABLE usuarios
    MODIFY COLUMN matricula VARCHAR(30) NOT NULL,
    ADD CONSTRAINT uk_usuarios_matricula UNIQUE (matricula);
ALTER TABLE usuarios MODIFY COLUMN email VARCHAR(190) NOT NULL;

ALTER TABLE relatorios ADD COLUMN arquivo_path VARCHAR(255) NULL AFTER arquivo_url;

ALTER TABLE projetos
    DROP CONSTRAINT chk_datas,
    ADD CONSTRAINT chk_datas CHECK (DATEDIFF(data_fim, data_inicio) >= 7);

ALTER TABLE equipes
    ADD CONSTRAINT chk_carga_horaria CHECK (carga_horaria_semanal BETWEEN 1 AND 40);

DELIMITER //
DROP TRIGGER IF EXISTS trg_validar_coordenador_projeto //
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

DROP TRIGGER IF EXISTS trg_projeto_apos_insert //
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

-- senha_hash permanece NULL para usuarios antigos. Use setup.php localmente para
-- definir a primeira senha docente; depois o docente pode definir as demais em Usuarios.
-- arquivo_path permanece NULL para arquivos legados; eles nao sao baixados por URL.
