<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_SESSION['user'])) redirect('index.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');
    $statement = db()->prepare('SELECT id, nome, email, senha_hash, perfil FROM usuarios WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();

    if ($user && is_string($user['senha_hash']) && password_verify($password, $user['senha_hash'])) {
        session_regenerate_id(true);
        unset($user['senha_hash']);
        $_SESSION['user'] = $user;
        redirect('index.php');
    }
    $errors[] = 'E-mail ou senha incorretos.';
}
$flash = take_flash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | SGP IFPA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-panel">
    <p class="eyebrow">SGP · IFPA Campus Tucuruí</p>
    <h1>Acessar o sistema</h1>
    <?php if ($flash): ?><div class="notice <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>E-mail<input type="email" name="email" autocomplete="username" required value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label>Senha<input type="password" name="senha" autocomplete="current-password" required></label>
        <button class="button primary" type="submit">Entrar</button>
    </form>
    <p class="muted">Primeiro acesso? <a href="setup.php">Configurar conta docente</a></p>
</main>
</body>
</html>
