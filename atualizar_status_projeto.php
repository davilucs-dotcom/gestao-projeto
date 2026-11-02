<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
require_docente($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo nao permitido.');
}
verify_csrf();

$projectId = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT);
$status = (string) ($_POST['status'] ?? '');
$allowedStatuses = ['Em andamento', 'Concluido', 'Suspenso'];

if (!$projectId || !in_array($status, $allowedStatuses, true)) {
    set_flash('Projeto ou status invalido.', 'error');
    redirect('index.php#projetos');
}

$pdo = db();
$projectQuery = $pdo->prepare(
    'SELECT status FROM projetos WHERE id = ? AND coordenador_id = ?'
);
$projectQuery->execute([$projectId, $user['id']]);
$currentStatus = $projectQuery->fetchColumn();

if ($currentStatus === false) {
    http_response_code(403);
    exit('Somente o coordenador do projeto pode alterar seu status.');
}

if ($currentStatus !== $status) {
    $update = $pdo->prepare(
        'UPDATE projetos SET status = ? WHERE id = ? AND coordenador_id = ?'
    );
    $update->execute([$status, $projectId, $user['id']]);
}

set_flash('Status do projeto atualizado.');
redirect('index.php#projetos');
