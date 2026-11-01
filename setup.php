<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('A configuracao inicial so pode ser feita localmente.');
}

$pdo = db();
$count = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$pendingTeachers = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'Docente' AND senha_hash IS NULL")->fetchColumn();
$mode = $count === 0 ? 'create' : 'claim';
if ($mode === 'claim' && $pendingTeachers === 0) {
    redirect('login.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $registration = trim((string) ($_POST['matricula'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail valido.';
    if ($registration === '' || mb_strlen($registration) > 30) $errors[] = 'Informe a matricula ou SIAPE.';
    if (strlen($password) < 10) $errors[] = 'A senha deve ter pelo menos 10 caracteres.';

    if ($mode === 'create') {
        $name = trim((string) ($_POST['nome'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Informe um nome com ate 120 caracteres.';
    }

    if (!$errors) {
        try {
            if ($mode === 'create') {
                $statement = $pdo->prepare(
                    'INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil) VALUES (?, ?, ?, ?, \'Docente\')'
                );
                $statement->execute([$name, $email, $registration, password_hash($password, PASSWORD_DEFAULT)]);
                set_flash('Conta docente inicial criada. Entre com seu e-mail e senha.');
            } else {
                $statement = $pdo->prepare(
                    "SELECT id FROM usuarios WHERE email = ? AND matricula = ? AND perfil = 'Docente' AND senha_hash IS NULL LIMIT 1"
                );
                $statement->execute([$email, $registration]);
                $teacherId = $statement->fetchColumn();
                if (!$teacherId) {
                    throw new InvalidArgumentException('E-mail e matricula nao correspondem a uma conta docente pendente.');
                }
                $update = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ? AND senha_hash IS NULL');
                $update->execute([password_hash($password, PASSWORD_DEFAULT), $teacherId]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('A conta ja foi configurada.');
                }
                set_flash('Senha docente configurada. Entre com seu e-mail e senha.');
            }
            redirect('login.php');
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('SGP initial account setup failed: ' . $exception->getMessage());
            $errors[] = 'Nao foi possivel concluir a configuracao. Verifique os dados informados.';
        }
    }
}
$claiming = $mode === 'claim';
$flash = take_flash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuração inicial | SGP IFPA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-panel">
    <p class="eyebrow">SGP · IFPA Campus Tucuruí</p>
    <h1>Configuração inicial</h1>
    <p><?= $claiming ? 'Defina a senha de uma conta docente existente. Este primeiro acesso so funciona localmente.' : 'Crie a primeira conta docente. Esta tela será desativada depois do primeiro cadastro.' ?></p>
    <?php if ($flash): ?><div class="notice <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if (!$claiming): ?><label>Nome<input name="nome" maxlength="120" required value="<?= h($_POST['nome'] ?? '') ?>"></label><?php endif; ?>
        <label>E-mail institucional<input type="email" name="email" maxlength="190" required value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label>Matrícula ou SIAPE<input name="matricula" maxlength="30" required value="<?= h($_POST['matricula'] ?? '') ?>"></label>
        <label>Senha inicial<input type="password" name="senha" minlength="10" autocomplete="new-password" required></label>
        <button class="button primary" type="submit"><?= $claiming ? 'Configurar senha docente' : 'Criar conta docente' ?></button>
    </form>
</main>
</body>
</html>
