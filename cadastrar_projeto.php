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
$title = trim((string) ($_POST['titulo'] ?? ''));
$summary = trim((string) ($_POST['resumo'] ?? ''));
$modality = (string) ($_POST['modalidade'] ?? '');
$startInput = (string) ($_POST['data_inicio'] ?? '');
$endInput = (string) ($_POST['data_fim'] ?? '');
$modalities = ['Ensino', 'Pesquisa', 'Extensao'];
$start = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
$end = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);

if ($title === '' || mb_strlen($title) > 200) {
    set_flash('O titulo e obrigatorio e deve ter ate 200 caracteres.', 'error');
    redirect('index.php');
}
if (!in_array($modality, $modalities, true)) {
    set_flash('Modalidade invalida.', 'error');
    redirect('index.php');
}
if (!$start || !$end || $start->format('Y-m-d') !== $startInput || $end->format('Y-m-d') !== $endInput || $end->diff($start)->days < 7 || $end <= $start) {
    set_flash('Informe datas validas e uma vigencia minima de 7 dias.', 'error');
    redirect('index.php');
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'INSERT INTO projetos (titulo, resumo, modalidade, coordenador_id, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([$title, $summary !== '' ? $summary : null, $modality, $user['id'], $startInput, $endInput]);
    $pdo->commit();
    set_flash('Projeto cadastrado. Equipe do coordenador e relatórios foram gerados pelo banco.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SGP project creation failed: ' . $exception->getMessage());
    set_flash('Nao foi possivel cadastrar o projeto. Confira os dados e tente novamente.', 'error');
}
redirect('index.php');
