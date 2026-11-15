# SGP IFPA - ambiente local

Aplicacao didatica em PHP com PDO e MariaDB/MySQL para acompanhar projetos, equipes e relatorios. O progresso e calculado pela view `vw_projetos_progresso` a partir dos relatorios entregues (um parcial e um final, gerados pelo banco).

## Requisitos

- XAMPP com Apache, PHP 8.0+ e MariaDB/MySQL.
- Extensoes PHP `pdo_mysql`, `fileinfo`, `mbstring` e `iconv` habilitadas.
- Chart.js e carregado pelo CDN; sem internet, o restante do sistema funciona, mas o grafico nao aparece.

## Instalar no XAMPP

1. Copie a pasta `gestao-projetos` para `C:\xampp\htdocs\gestao-projetos`.
2. Inicie Apache e MySQL no painel do XAMPP.
3. Se o banco ja existir, **nao importe `schema.sql` nem execute uma migracao sem conferir as colunas atuais**. `migrar_banco_existente.sql` e apenas para o esquema legado que tem `usuarios.matricula_siape` e `relatorios.arquivo_url`, mas ainda nao tem `usuarios.senha_hash`, `usuarios.matricula` ou `relatorios.arquivo_path`. Rode primeiro o SELECT de pre-validacao do proprio script; se seu banco ja tiver as colunas novas ou nao tiver as colunas legadas, nao execute esse script. O banco SGP deste ambiente ja usa o esquema novo.
4. Para uma instalacao realmente nova, importe `schema.sql` uma vez pelo phpMyAdmin; ele cria o banco `sgp_ifpa`.
5. Confira as credenciais em `config/db.php`. Por padrao, usa `127.0.0.1`, banco `sgp_ifpa`, usuario `root` e senha vazia, que e o padrao de desenvolvimento de algumas instalacoes XAMPP. Nao use essa conta em producao. As variaveis `SGP_DB_HOST`, `SGP_DB_PORT`, `SGP_DB_NAME`, `SGP_DB_USER` e `SGP_DB_PASSWORD` sobrescrevem os valores locais.
6. Acesse `http://localhost/gestao-projetos/setup.php`. Em banco novo, crie a conta docente inicial. No banco migrado, informe e-mail e matrícula de um docente existente para definir a primeira senha. Depois, entre em `http://localhost/gestao-projetos/login.php`.
7. O docente inicial pode cadastrar usuários e definir senhas pendentes em **Usuários**. Discente e técnico não podem se cadastrar como docente.
8. O primeiro envio de PDF cria `C:\xampp\private_uploads\sgp`, fora de `htdocs`. Se o Apache nao puder gravar nesse diretorio, configure `SGP_UPLOAD_DIR` para outro diretorio fora da raiz publica e conceda permissao de escrita ao processo do Apache.
9. Antes de usar esta versao do codigo, faca backup do banco e execute `sql/migracao_melhorias.sql` no phpMyAdmin, selecionando o banco `sgp_ifpa`. Essa migracao e propria do esquema novo e cria a tabela de tentativas, a versao de credenciais necessaria para invalidar sessoes e a view de progresso.
10. Para exibir erros durante o desenvolvimento local, configure `SetEnv SGP_ENV dev` no Apache e reinicie o Apache. Nao habilite isso em um servidor acessivel a outras pessoas.

O caminho do cookie de sessao assume `/gestao-projetos/`; se a aplicacao for publicada em outro caminho, defina `SGP_COOKIE_PATH` com o novo caminho. A inatividade encerra a sessao apos 30 minutos. O limite de login bloqueia cinco falhas para o mesmo e-mail e IP, ou 30 falhas por IP, em uma janela de 15 minutos. O PHP e a conexao MySQL usam o fuso de Belem (`UTC-03:00`). O projeto foi ajustado para PHP 8.0 ou superior; confira a versao em uso no painel do XAMPP antes de testar.
Cada sessao autenticada confere a versao das credenciais no banco. Ao alterar ou redefinir uma senha, as outras sessoes daquela conta sao encerradas na proxima requisicao. A migracao de melhorias adiciona essa coluna sem duplicar a alteracao se for executada novamente.

## Entrega academica

- Modelagem conceitual/logica e DER: `docs/modelagem.md` e `docs/modelo.dbml` (exportar em dbdiagram.io).
- Roteiro de demonstracao para a banca: `docs/roteiro-demonstracao.md`.
- Dados de demonstracao: importe `dados_demo.sql` apos o `schema.sql`. Senha de todos: `SenhaDemo123!` (docente `mauro@ifpa.edu.br`).
- O painel exibe dois graficos: projetos por modalidade e situacao dos relatorios.
- O painel corrige titulos legados com mojibake CP437 ao exibir projetos e consolida titulos repetidos, mantendo a copia com texto UTF-8 integro. Os scripts SQL tambem definem a conexao de importacao como `utf8mb4`.

## Fluxos incluidos

- Login, encerramento de sessao, troca de senha e criacao inicial do primeiro docente.
- Cadastro de usuarios por docente e cadastro de projetos com prazo minimo de sete dias.
- O docente coordenador pode atualizar o status do proprio projeto para Em andamento, Concluido ou Suspenso.
- Trigger que inclui o coordenador na equipe e cria os relatorios parcial e final. Para uma vigencia com numero impar de dias, o prazo parcial usa o dia central anterior.
- Gerenciamento de equipe apenas pelo coordenador do projeto.
- Envio e download de PDF restritos a membros da equipe. O nome armazenado e aleatorio e os arquivos nao ficam acessiveis por URL.
- O envio usa transacao e bloqueio `FOR UPDATE` para impedir entregas concorrentes; o progresso e calculado pela view `vw_projetos_progresso`.
- O progresso exibido e derivado diretamente dos relatorios; em bancos antigos a coluna persistida `projetos.progresso` e ignorada.
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
9. Com a migracao de melhorias aplicada, erre a senha cinco vezes usando o mesmo e-mail e computador: novas tentativas devem ser limitadas por 15 minutos. Um login correto deve continuar funcionando.
10. Depois de entrar, fique 30 minutos sem atividade e faca uma nova requisicao: o sistema deve pedir login novamente e informar que a sessao expirou.
11. Envie um PDF valido, um arquivo que nao seja PDF, um arquivo acima de 10 MB e tente entregar o mesmo relatorio duas vezes.
12. Como coordenador, altere o status do projeto; como outro docente ou discente, confirme que a acao nao e permitida.
13. No painel, confirme que os dois graficos carregam e que o console do navegador nao registra bloqueios da CSP.

Para executar os testes automatizados simples dos helpers e controles de sessao, rode `C:\xampp\php\php.exe tests\run.php` no PowerShell a partir da pasta do projeto.
Com o XAMPP e a migracao de melhorias aplicados, tambem e possivel validar a view de progresso e o limite de tentativas no banco local, sem persistir dados de teste:

```powershell
$env:SGP_RUN_DB_TESTS = '1'
C:\xampp\php\php.exe tests\run.php
Remove-Item Env:\SGP_RUN_DB_TESTS
```

## Observacoes de seguranca

Este projeto e para demonstracao local. A CSP bloqueante permite scripts apenas da propria aplicacao e do CDN usado pelo Chart.js; os dados dos graficos sao passados por atributos HTML escapados, sem script inline. HSTS nao e ativado automaticamente, pois este projeto e configurado para XAMPP local. Antes de disponibilizar fora do computador, use uma conta SQL com privilegios minimos, HTTPS, configuracao de ambiente fora do document root, politicas de backup e revisao de seguranca. `schema.sql` e `README.md` sao bloqueados pelo `.htaccess`; diretorios de configuracao e includes tambem negam acesso direto.
