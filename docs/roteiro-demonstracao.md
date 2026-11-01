# Roteiro de demonstração — SGP IFPA

Duração sugerida: **4 a 6 minutos**. Use os dados de `dados_demo.sql` (senha de todos: `SenhaDemo123!`).

## Antes de começar

1. Apache e MySQL ligados no XAMPP
2. Banco `sgp_ifpa` criado (`schema.sql`) e, se quiser, `dados_demo.sql` importado
3. Acesse `http://localhost/gestao-projetos/login.php`
4. Tenha um PDF pequeno pronto para upload (ex.: relatório de 1 página)

## Script falado

### 1. Login e painel (40 s)

- Entre com `mauro@ifpa.edu.br` / `SenhaDemo123!`
- Mostre os indicadores (projetos, andamento, progresso)
- Aponte os **dois gráficos**: projetos por modalidade e situação dos relatórios
- Mostre a lista de prazos com Pendente / Entregue / Atrasado

> “O sistema acompanha projetos de ensino, pesquisa e extensão, com gráficos e prazos de relatório parcial e final.”

### 2. Cadastro de projeto + prazos automáticos (60 s)

- Em **Novo projeto**, cadastre algo curto, vigência ≥ 7 dias
- Volte ao painel e abra **Relatórios**
- Mostre que já existem **Parcial** (metade da vigência) e **Final** (data de término)

> “Ao salvar o projeto, o banco gera automaticamente a equipe do coordenador e os dois prazos.”

### 3. Gestão de equipe (50 s)

- No projeto, abra **Equipe**
- Adicione um discente (ex.: Pedro) como Bolsista
- Mostre a lista de membros e a carga horária

> “Só o coordenador gerencia a equipe; cada pessoa entra uma vez por projeto.”

### 4. Entrega de relatório (70 s)

- Em **Relatórios**, envie um PDF no parcial
- Confirme status **Entregue** e progresso do projeto em 50%
- (Opcional) baixe o arquivo para mostrar o armazenamento privado

> “O upload é PDF, restrito à equipe, e o progresso reflete os relatórios entregues.”

### 5. Fechamento / modelagem (40 s)

- Mostre o DER (`docs/modelagem.md` ou print do dbdiagram)
- Cite as 4 tabelas: usuários, projetos, equipes, relatórios
- Mencione triggers de prazo e a view de situação

> “A modelagem cobre projetos, equipes, gráficos e prazos automáticos pedidos no tema.”

## Credenciais de demonstração

| Perfil | E-mail | Senha |
|---|---|---|
| Docente (coord.) | mauro@ifpa.edu.br | SenhaDemo123! |
| Discente | pedro@discente.ifpa.edu.br | SenhaDemo123! |
| Discente | gabriel@discente.ifpa.edu.br | SenhaDemo123! |
| Discente | davi@discente.ifpa.edu.br | SenhaDemo123! |
| Técnico | tecnico@ifpa.edu.br | SenhaDemo123! |

## Se algo falhar na hora

- Sem gráfico: internet/CDN do Chart.js; o restante funciona
- Erro de banco: confira MySQL e `config/db.php`
- Setup pedindo conta: importe `dados_demo.sql` ou use `setup.php` localmente
