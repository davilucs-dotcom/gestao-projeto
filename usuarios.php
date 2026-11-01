<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_auth();
require_docente($user);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['acao'] ?? 'criar');
    if ($action === 'definir_senha') {
        $targetId = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
        $password = (string) ($_POST['senha_nova'] ?? '');
        if (!$targetId || strlen($password) < 10) {
            $errors[] = 'Escolha o usuario e informe uma senha com pelo menos 10 caracteres.';
        } else {
            $statement = db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
            $statement->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
            if ($statement->rowCount() === 1) {
                set_flash('Senha definida para o usuario.');
                redirect('usuarios.php');
            }
            $errors[] = 'Usuario nao encontrado ou senha inalterada.';
        }
    } else {
        $name = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $registration = trim((string) ($_POST['matricula'] ?? ''));
        $profile = (string) ($_POST['perfil'] ?? '');
        $password = (string) ($_POST['senha'] ?? '');
        $profiles = ['Docente', 'Discente', 'Tecnico'];

        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Informe um nome com ate 120 caracteres.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = 'Informe um e-mail valido.';
        if ($registration === '' || mb_strlen($registration) > 30) $errors[] = 'Informe a matricula ou SIAPE.';
        if (!in_array($profile, $profiles, true)) $errors[] = 'Perfil invalido.';
        if (strlen($password) < 10) $errors[] = 'A senha deve ter pelo menos 10 caracteres.';

        if (!$errors) {
            try {
                $statement = db()->prepare(
                    'INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil) VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$name, $email, $registration, password_hash($password, PASSWORD_DEFAULT), $profile]);
                set_flash('Usuario cadastrado.');
                redirect('usuarios.php');
            } catch (PDOException $exception) {
                error_log('SGP user creation failed: ' . $exception->getMessage());
                $errors[] = 'Nao foi possivel cadastrar. E-mail ou matricula podem ja estar em uso.';
            }
        }
    }
}
$users = db()->query(
    'SELECT id, nome, email, matricula, perfil, criado_em, (senha_hash IS NULL) AS senha_pendente FROM usuarios ORDER BY nome'
)->fetchAll();
$pageTitle = 'Usuários';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Administração</p><h1>Usuários</h1></div></section>
<?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
<div class="content-grid">
    <section class="panel">
        <h2>Novo usuário</h2>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="acao" value="criar">
            <label>Nome<input name="nome" maxlength="120" required value="<?= h($_POST['nome'] ?? '') ?>"></label>
            <label>E-mail<input type="email" name="email" maxlength="190" required value="<?= h($_POST['email'] ?? '') ?>"></label>
            <label>Matrícula acadêmica ou SIAPE<input name="matricula" maxlength="30" required value="<?= h($_POST['matricula'] ?? '') ?>"></label>
            <label>Perfil<select name="perfil" required><option value="">Selecione</option><?php foreach (['Docente', 'Discente', 'Tecnico'] as $profile): ?><option value="<?= h($profile) ?>" <?= (($_POST['perfil'] ?? '') === $profile) ? 'selected' : '' ?>><?= h($profile) ?></option><?php endforeach; ?></select></label>
            <label>Senha inicial<input type="password" name="senha" minlength="10" autocomplete="new-password" required></label>
            <button class="button primary" type="submit">Cadastrar usuário</button>
        </form>
    </section>
    <section class="panel panel-wide">
        <h2>Usuários cadastrados <span class="count"><?= count($users) ?></span></h2>
        <div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Matrícula</th><th>Perfil</th><th>Senha</th></tr></thead><tbody>
        <?php foreach ($users as $row): ?><tr>
            <td><?= h($row['nome']) ?></td><td><?= h($row['email']) ?></td><td><?= h($row['matricula']) ?></td><td><span class="tag"><?= h($row['perfil']) ?></span></td>
            <td><?php if ((int) $row['senha_pendente'] === 1): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="acao" value="definir_senha"><input type="hidden" name="usuario_id" value="<?= (int) $row['id'] ?>"><input type="password" name="senha_nova" minlength="10" placeholder="Nova senha" autocomplete="new-password" required><button class="button quiet-dark" type="submit">Definir</button></form><?php else: ?><span class="muted">Configurada</span><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
