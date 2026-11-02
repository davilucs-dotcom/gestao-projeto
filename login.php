<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/rate_limit.php';

if (isset($_SESSION['user'])) {
    if (is_array($_SESSION['user']) && authenticated_session_is_current($_SESSION['user'])) {
        redirect('index.php');
    }
    clear_authenticated_session('Sua sessao foi encerrada porque as credenciais da conta mudaram. Entre novamente.');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');
    $pdo = db();
    $ip = login_client_ip();

    if (login_bloqueado($pdo, $email, $ip)) {
        $errors[] = 'Muitas tentativas de acesso. Aguarde ' . LOGIN_JANELA_MINUTOS . ' minutos e tente novamente.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, nome, email, senha_hash, perfil, session_version
             FROM usuarios WHERE email = ? LIMIT 1'
        );
        $statement->execute([$email]);
        $user = $statement->fetch();

        $hashValido = $user && is_string($user['senha_hash']) && $user['senha_hash'] !== '';
        $senhaConfere = password_verify($password, $hashValido ? $user['senha_hash'] : login_hash_falso());

        if ($hashValido && $senhaConfere) {
            if (password_needs_rehash($user['senha_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }

            login_limpar($pdo, $email, $ip);
            session_regenerate_id(true);
            unset($user['senha_hash'], $_SESSION['csrf_token']);
            $_SESSION['user'] = $user;
            $_SESSION['last_activity'] = time();
            redirect('index.php');
        }

        login_registrar_falha($pdo, $email, $ip);
        $errors[] = 'E-mail ou senha incorretos.';
    }
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
