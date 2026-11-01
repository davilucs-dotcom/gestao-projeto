# SGP IFPA - ambiente local

Aplicacao didatica em PHP com PDO e MariaDB/MySQL para acompanhar projetos, equipes e relatorios. O campo `progresso` representa a porcentagem de relatorios do projeto que foram entregues (um parcial e um final, gerados pelo banco).

## Requisitos

- XAMPP com Apache, PHP 8.1+ e MariaDB/MySQL.
- Extensoes PHP `pdo_mysql`, `fileinfo` e `mbstring` habilitadas.
- Chart.js e carregado pelo CDN; sem internet, o restante do sistema funciona, mas o grafico nao aparece.

## Instalar no XAMPP

1. Copie a pasta `gestao-projetos` para `C:\xampp\htdocs\gestao-projetos`.
2. Inicie Apache e MySQL no painel do XAMPP.
3. O banco `sgp_ifpa` ja existe neste ambiente. **Nao importe `schema.sql` sobre ele.** Faca backup e use `migrar_banco_existente.sql`. Antes, execute as consultas de pre-validacao do inicio do arquivo separadamente; so prossiga se nao houver projetos com menos de 7 dias nem cargas fora de 1 a 40 horas.
4. Para uma instalacao realmente nova, importe `schema.sql` uma vez pelo phpMyAdmin; ele cria o banco `sgp_ifpa`.
5. Confira as credenciais em `config/db.php`. Por padrao, usa `127.0.0.1`, banco `sgp_ifpa`, usuario `root` e senha vazia, que e o padrao de desenvolvimento de algumas instalacoes XAMPP. Nao use essa conta em producao. As variaveis `SGP_DB_HOST`, `SGP_DB_PORT`, `SGP_DB_NAME`, `SGP_DB_USER` e `SGP_DB_PASSWORD` sobrescrevem os valores locais.
6. Acesse `http://localhost/gestao-projetos/setup.php`. Em banco novo, crie a conta docente inicial. No banco migrado, informe e-mail e matrícula de um docente existente para definir a primeira senha. Depois, entre em `http://localhost/gestao-projetos/login.php`.
7. O docente inicial pode cadastrar usuários e definir senhas pendentes em **Usuários**. Discente e técnico não podem se cadastrar como docente.
8. O primeiro envio de PDF cria `C:\xampp\private_uploads\sgp`, fora de `htdocs`. Se o Apache nao puder gravar nesse diretorio, configure `SGP_UPLOAD_DIR` para outro diretorio fora da raiz publica e conceda permissao de escrita ao processo do Apache.

## Entrega academica

- Modelagem conceitual/logica e DER: `docs/modelagem.md` e `docs/modelo.dbml` (exportar em dbdiagram.io).
- Roteiro de demonstracao para a banca: `docs/roteiro-demonstracao.md`.
- Dados de demonstracao: importe `dados_demo.sql` apos o `schema.sql`. Senha de todos: `SenhaDemo123!` (docente `mauro@ifpa.edu.br`).
- O painel exibe dois graficos: projetos por modalidade e situacao dos relatorios.

## Fluxos incluidos

- Login, encerramento de sessao, troca de senha e criacao inicial do primeiro docente.
- Cadastro de usuarios por docente e cadastro de projetos com prazo minimo de sete dias.
- Trigger que inclui o coordenador na equipe e cria os relatorios parcial e final. Para uma vigencia com numero impar de dias, o prazo parcial usa o dia central anterior.
- Gerenciamento de equipe apenas pelo coordenador do projeto.
- Envio e download de PDF restritos a membros da equipe. O nome armazenado e aleatorio e os arquivos nao ficam acessiveis por URL.
- O envio usa transacao, bloqueio `FOR UPDATE`, atualizacao condicionada por projeto e recalculo do progresso.
- O status do relatorio e calculado em `vw_relatorios_situacao`; o PHP nao grava um status temporal que poderia ficar desatualizado.
- O perfil permanece `ENUM` nesta versao. A associacao de multiplos perfis exigira migrar para tabelas de perfis e usuario-perfis.

## Roteiro de teste manual

1. Tente abrir `setup.php` apos criar a primeira conta: deve redirecionar ao login.
2. Entre com o docente e crie projetos com 6 e 7 dias: o primeiro deve ser recusado e o segundo aceito.
3. Confirme que cada projeto tem coordenador na equipe e dois relatorios, com o parcial na metade da vigencia.
4. Crie um usuario discente, adicione-o a equipe e confira que ele nao consegue abrir a gestao de equipe de outro projeto.
5. Envie um PDF valido e tente enviar novamente o mesmo relatorio; o segundo envio deve ser recusado.
6. Entre com um usuario de outra equipe e tente baixar o arquivo usando o ID do relatorio: a resposta deve ser negada.
7. Confirme que o progresso muda para 50% apos um relatorio e 100% apos os dois.
8. Teste nomes com caracteres HTML e confirme que aparecem como texto, sem executar marcação.

## Observacoes de seguranca

Este projeto e para demonstracao local. Antes de disponibilizar fora do computador, use uma conta SQL com privilegios minimos, HTTPS, configuracao de ambiente fora do document root, politicas de backup e revisao de seguranca. `schema.sql` e `README.md` sao bloqueados pelo `.htaccess`; diretórios de configuracao e includes tambem negam acesso direto.
