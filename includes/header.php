<?php
$user = require_auth();
$pageTitle = $pageTitle ?? 'SGP IFPA';
$flash = take_flash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | SGP IFPA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark">SGP</span><span>IFPA <small>Campus Tucuruí</small></span></a>
    <nav class="main-nav" aria-label="Navegação principal">
        <a href="index.php">Painel</a>
        <?php if ($user['perfil'] === 'Docente'): ?><a href="usuarios.php">Usuários</a><?php endif; ?>
        <?php if ($user['perfil'] === 'Docente'): ?><a href="usuarios.php">Usuários</a><?php endif; ?>
        <a href="entregar_relatorio.php">Relatórios</a><a href="alterar_senha.php">Senha</a>
    </nav>
    <div class="account">
        <span><?= h($user['nome']) ?><small><?= h($user['perfil']) ?></small></span>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button class="button quiet" type="submit">Sair</button>
        </form>
    </div>
</header>
<main class="page-shell">
    <?php if ($flash): ?><div class="notice <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div><?php endif; ?>
