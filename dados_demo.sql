-- Dados de demonstração — SGP IFPA
-- Pré-requisito: schema.sql já importado (banco vazio ou sem esses e-mails).
-- Senha de todos os usuários: SenhaDemo123!
-- Hash gerado com password_hash(..., PASSWORD_DEFAULT) no PHP do XAMPP.

USE sgp_ifpa;

SET @demo_hash := '$2y$10$/Wuy3cbhnKbE6I.H02wigeaVTwAE40ddN5CvhOVsfa0Rl5FhwC6AO';

INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil) VALUES
('Prof. Mauro', 'mauro@ifpa.edu.br', '1122334', @demo_hash, 'Docente'),
('Pedro', 'pedro@discente.ifpa.edu.br', '2024001', @demo_hash, 'Discente'),
('Gabriel', 'gabriel@discente.ifpa.edu.br', '2024002', @demo_hash, 'Discente'),
('Davi', 'davi@discente.ifpa.edu.br', '2024003', @demo_hash, 'Discente'),
('Maria Técnica Silva', 'tecnico@ifpa.edu.br', 'TEC9001', @demo_hash, 'Tecnico');

SET @coord := (SELECT id FROM usuarios WHERE email = 'mauro@ifpa.edu.br');
SET @pedro := (SELECT id FROM usuarios WHERE email = 'pedro@discente.ifpa.edu.br');
SET @gabriel := (SELECT id FROM usuarios WHERE email = 'gabriel@discente.ifpa.edu.br');
SET @davi := (SELECT id FROM usuarios WHERE email = 'davi@discente.ifpa.edu.br');
SET @tec   := (SELECT id FROM usuarios WHERE email = 'tecnico@ifpa.edu.br');

-- Datas relativas a CURDATE() para a demo funcionar em qualquer dia
-- Projeto 1: Pesquisa (vigência 60 dias) — parcial no meio, final no fim
INSERT INTO projetos (titulo, resumo, modalidade, coordenador_id, data_inicio, data_fim, status)
VALUES (
    'Robótica Educacional nas Escolas Públicas',
    'Oficinas de robótica para estudantes do ensino médio da região de Tucuruí.',
    'Extensao',
    @coord,
    DATE_SUB(CURDATE(), INTERVAL 20 DAY),
    DATE_ADD(CURDATE(), INTERVAL 40 DAY),
    'Em andamento'
);
SET @p1 := LAST_INSERT_ID();

-- Projeto 2: Ensino (vigência 90 dias)
INSERT INTO projetos (titulo, resumo, modalidade, coordenador_id, data_inicio, data_fim, status)
VALUES (
    'Monitoria de Algoritmos e Programação',
    'Apoio pedagógico a discentes ingressantes do BCC.',
    'Ensino',
    @coord,
    DATE_SUB(CURDATE(), INTERVAL 45 DAY),
    DATE_ADD(CURDATE(), INTERVAL 45 DAY),
    'Em andamento'
);
SET @p2 := LAST_INSERT_ID();

-- Projeto 3: Pesquisa (vigência 30 dias) — parcial já vencido para mostrar "Atrasado"
INSERT INTO projetos (titulo, resumo, modalidade, coordenador_id, data_inicio, data_fim, status)
VALUES (
    'Sensoriamento Remoto na Amazônia Oriental',
    'Levantamento de dados ambientais com imagens de satélite.',
    'Pesquisa',
    @coord,
    DATE_SUB(CURDATE(), INTERVAL 25 DAY),
    DATE_ADD(CURDATE(), INTERVAL 5 DAY),
    'Em andamento'
);
SET @p3 := LAST_INSERT_ID();

-- Membros adicionais (coordenador já entrou pelo trigger)
INSERT INTO equipes (projeto_id, usuario_id, papel, carga_horaria_semanal, data_entrada) VALUES
(@p1, @pedro, 'Bolsista', 12, DATE_SUB(CURDATE(), INTERVAL 18 DAY)),
(@p1, @gabriel, 'Voluntario', 6, DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
(@p2, @davi, 'Bolsista', 8, DATE_SUB(CURDATE(), INTERVAL 40 DAY)),
(@p2, @tec, 'Colaborador', 4, DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
(@p3, @pedro, 'Bolsista', 10, DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(@p3, @davi, 'Voluntario', 4, DATE_SUB(CURDATE(), INTERVAL 18 DAY));

-- Marca o parcial do projeto 1 como entregue (progresso 50%)
UPDATE relatorios
SET data_entrega = DATE_SUB(CURDATE(), INTERVAL 2 DAY),
    arquivo_path = 'demo_parcial_p1.pdf'
WHERE projeto_id = @p1 AND tipo = 'Parcial';

UPDATE projetos
SET progresso = (
    SELECT ROUND(100 * SUM(data_entrega IS NOT NULL) / COUNT(*))
    FROM relatorios
    WHERE projeto_id = @p1
)
WHERE id = @p1;
