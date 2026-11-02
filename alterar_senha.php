<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_auth();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = (string) ($_POST['senha_atual'] ?? '');
    $newPassword = (string) ($_POST['senha_nova'] ?? '');
    $confirmation = (string) ($_POST['confirmar_senha'] ?? '');
    $statement = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $statement->execute([$user['id']]);
    $passwordHash = $statement->fetchColumn();

    if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
        $errors[] = 'A senha atual nao confere.';
    } elseif (strlen($newPassword) < 10) {
        $errors[] = 'A nova senha deve ter pelo menos 10 caracteres.';
    } elseif ($newPassword !== $confirmation) {
        $errors[] = 'A confirmacao da senha nao confere.';
    } else {
        $update = db()->prepare(
            'UPDATE usuarios SET senha_hash = ?, session_version = session_version + 1 WHERE id = ?'
        );
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        $_SESSION['user']['session_version'] = (int) $user['session_version'] + 1;
        unset($_SESSION['csrf_token']);
        session_regenerate_id(true);
        set_flash('Senha alterada.');
        redirect('index.php');
    }
}
$pageTitle = 'Alterar senha';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Conta</p><h1>Alterar senha</h1></div></section>
<?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
<section class="panel narrow-panel">
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password" required></label>
        <label>Nova senha<input type="password" name="senha_nova" minlength="10" autocomplete="new-password" required></label>
        <label>Confirmar nova senha<input type="password" name="confirmar_senha" minlength="10" autocomplete="new-password" required></label>
        <button class="button primary" type="submit">Salvar senha</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
